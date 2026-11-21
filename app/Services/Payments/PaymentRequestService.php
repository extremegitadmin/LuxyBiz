<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Notifications\PaymentLinkNotification;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\Notification;

/**
 * Customer payment links for staff-made bookings (Day 29).
 *  - The link carries a random token. Only its SHA-256 fingerprint is stored, so the database holds no working link.
 *  - Opening the link shows a small LuxyBiz page. Only when the customer presses "Pay" is the provider's hosted page created,
 *    so a link can live for days while provider pages live for hours, and the amount is checked against the balance at that moment.
 *  - Card details are only ever typed on the provider's page.
 * Rule failures throw a DomainException whose message is safe to show to staff.
 */
class PaymentRequestService
{
    public const MAX_OPEN_PER_BOOKING = 5;
    public const RESTART_AFTER_MINUTES = 5;

    /**
     * Make a link for part or all of the balance. Returns the request and the full link (shown once; it cannot be recovered later).
     *
     * @return array{request: PaymentRequest, url: string}
     */
    public function create(Booking $booking, float $amount, User $staff): array
    {
        $amount = round($amount, 2);
        $balance = PaymentSessionService::balanceDue($booking);

        if (! in_array($booking->status, PaymentSessionService::PAYABLE_STATUSES, true)) {
            throw new \DomainException('This booking is ' . str_replace('_', ' ', $booking->status) . ' and cannot take a payment.');
        }
        if ($amount <= 0) {
            throw new \DomainException('The amount must be more than zero.');
        }
        if ($amount > $balance) {
            throw new \DomainException('The amount is more than the balance due (' . $booking->currency . ' ' . number_format($balance, 2) . ').');
        }

        // A payment that cannot be made must not be sent to a customer: check the provider is ready now.
        $gateway = Gateway::forCurrency($booking->currency);
        if (! $gateway->supportsPaymentSessions()) {
            throw new \DomainException('No online payment provider is set up for ' . $booking->currency . '. Choose and switch one on under Settings, Payment gateways.');
        }
        try {
            $gateway->assertReady();
        } catch (GatewayException $e) {
            throw new \DomainException($e->getMessage());
        }

        $open = PaymentRequest::query()->where('booking_id', $booking->id)->whereNull('cancelled_at')->whereNull('paid_at')->where('expires_at', '>', now())->get();
        if ($open->count() >= self::MAX_OPEN_PER_BOOKING) {
            throw new \DomainException('This booking already has ' . self::MAX_OPEN_PER_BOOKING . ' open payment links. Cancel one before making another.');
        }
        if ($open->contains(fn (PaymentRequest $r) => round((float) $r->amount, 2) === $amount && $r->created_at->gte(now()->subMinute()))) {
            throw new \DomainException('A payment link for this amount was just created. Check the list below before making another.');
        }

        $token = $this->newToken();
        $request = PaymentRequest::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'currency' => $booking->currency,
            'token_hash' => PaymentRequest::hashToken($token),
            'expires_at' => now()->addHours(max(1, (int) config('booking.payment_link_hours', 72))),
            'created_by' => $staff->id,
        ]);

        SafeAudit::log('payment.link_created', 'Customer payment link for booking ' . $booking->reference, [
            'subject_id' => $request->id, 'subject_type' => 'PaymentRequest', 'booking_id' => $booking->id, 'booking_reference' => $booking->reference,
            'amount' => $amount, 'currency' => $booking->currency, 'expires_at' => $request->expires_at->toIso8601String(),
        ]);

        return ['request' => $request, 'url' => route('pay.show', $token)];
    }

    /** Email the link to the customer on file. Never throws: the result is saved on the request for staff to see. */
    public function send(PaymentRequest $request, string $url): bool
    {
        $booking = $request->booking()->with(['user', 'leadTraveler'])->first();
        $email = collect([$booking->user?->email, $booking->leadTraveler?->email])->first(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL));

        if (! $email) {
            $request->update(['sent_to' => null, 'sent_at' => null, 'send_error' => 'The booking has no valid email address. Copy the link and give it to the customer another way.']);

            return false;
        }

        try {
            Notification::route('mail', [$email => $booking->user?->name ?? $email])->notifyNow(new PaymentLinkNotification($request, $url));
        } catch (\Throwable $e) {
            report($e);
            $request->update(['sent_to' => $email, 'sent_at' => null, 'send_error' => 'The email could not be sent. Check the mail settings, or copy the link and give it to the customer another way.']);

            return false;
        }

        $request->update(['sent_to' => $email, 'sent_at' => now(), 'send_error' => null]);
        SafeAudit::log('payment.link_sent', 'Payment link emailed for booking ' . $booking->reference, [
            'subject_id' => $request->id, 'subject_type' => 'PaymentRequest', 'booking_id' => $booking->id,
        ]);

        return true;
    }

    /** Make a new link for an open request (the old one stops working) and email it. Returns the new link. */
    public function resend(PaymentRequest $request, User $by): string
    {
        if ($request->status() !== 'open') {
            throw new \DomainException('This link is ' . $request->status() . ' and cannot be sent again. Make a new link.');
        }

        $token = $this->newToken();
        $request->update(['token_hash' => PaymentRequest::hashToken($token)]);
        $url = route('pay.show', $token);

        SafeAudit::log('payment.link_renewed', 'Payment link renewed for booking ' . $request->booking_id, [
            'subject_id' => $request->id, 'subject_type' => 'PaymentRequest', 'booking_id' => $request->booking_id, 'renewed_by' => $by->id,
        ]);
        $this->send($request->fresh(), $url);

        return $url;
    }

    public function cancel(PaymentRequest $request, User $by): void
    {
        if ($request->status() !== 'open') {
            throw new \DomainException('This link is already ' . $request->status() . '.');
        }

        $request->update(['cancelled_at' => now()]);
        SafeAudit::log('payment.link_cancelled', 'Payment link cancelled for booking ' . $request->booking_id, [
            'subject_id' => $request->id, 'subject_type' => 'PaymentRequest', 'booking_id' => $request->booking_id, 'cancelled_by' => $by->id,
        ]);
    }

    // ------------------------------------------------------------------ the customer side

    public function findByToken(string $token): ?PaymentRequest
    {
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }

        return PaymentRequest::query()->where('token_hash', PaymentRequest::hashToken($token))->first();
    }

    /**
     * What the customer sees: 'open' (can pay), 'paid', 'cancelled', 'expired' or 'unavailable'.
     * A link whose payment was confirmed by the provider is marked paid here.
     */
    public function state(PaymentRequest $request): string
    {
        if (! $request->paid_at && Payment::query()->where('payment_request_id', $request->id)->where('type', 'charge')->where('status', 'succeeded')->exists()) {
            $request->update(['paid_at' => now()]);
        }

        $status = $request->status();
        if ($status !== 'open') {
            return $status;
        }

        $booking = $request->booking;
        if (! $booking || ! in_array($booking->status, PaymentSessionService::PAYABLE_STATUSES, true) || PaymentSessionService::balanceDue($booking) <= 0) {
            return 'unavailable';
        }

        return 'open';
    }

    /** The customer never pays more than is still due, even if the link was made for more. */
    public function amountDue(PaymentRequest $request): float
    {
        return min(round((float) $request->amount, 2), PaymentSessionService::balanceDue($request->booking));
    }

    public function recordOpen(PaymentRequest $request): void
    {
        $request->forceFill(['last_opened_at' => now(), 'open_count' => $request->open_count + 1])->save();
    }

    /**
     * The customer pressed "Pay": create the provider's hosted page for what is still due and return its address.
     *
     * @throws \DomainException  with wording that is safe to show to the customer
     */
    public function startPayment(PaymentRequest $request, PaymentSessionService $sessions): string
    {
        if ($this->state($request) !== 'open') {
            throw new \DomainException('This payment link can no longer be used.');
        }

        // Two payment pages open for one link could both be paid. Wait a few minutes before a second one is made.
        $recent = Payment::query()->where('payment_request_id', $request->id)->where('type', 'charge')->where('status', 'pending')
            ->whereNotNull('gateway_session_id')->where('created_at', '>=', now()->subMinutes(self::RESTART_AFTER_MINUTES))->exists();
        if ($recent) {
            throw new \DomainException('A payment page was already opened for this link a moment ago. Please finish it there, or wait a few minutes and open this link again.');
        }

        try {
            $result = $sessions->start($request->booking, $this->amountDue($request), $request->creator, 'csr_link');
        } catch (GatewayException $e) {
            report($e);   // the real reason goes to the log, not to the customer
            throw new \DomainException('We could not start the payment just now. Please wait a minute and try again, or contact us.');
        }

        $result['payment']->update(['payment_request_id' => $request->id]);

        return $result['url'];
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
