<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared rules for every provider's webhooks. The webhook is the ONLY thing that can mark an online payment
 * succeeded, failed or refunded: the customer's browser never decides that.
 *
 * A provider class only says how to read its own messages (signature, event name, event id, fields).
 * The money rules live here, so Stripe and Razorpay can never drift apart:
 *  - the amount and currency the provider collected must equal what we asked for;
 *  - the booking (and session) in the message must match our payment;
 *  - a failure message never undoes money that arrived;
 *  - the same event, or two events about one payment, change it only once.
 */
abstract class WebhookHandler
{
    /** The provider name saved in payments.gateway. */
    abstract public function gateway(): string;

    /** The event names to tick in the provider's dashboard. Nothing else is needed. @return list<string> */
    abstract public function events(): array;

    abstract public function signatureValid(string $rawBody, Request $request, string $secret): bool;

    abstract public function eventName(array $event): string;

    /** The provider's unique id for this message (makes retries harmless). */
    abstract public function eventId(array $event, Request $request): ?string;

    /** Only what we need from the message. Customer name, email and address are left out on purpose. */
    abstract public function summary(array $event): array;

    /**
     * Apply one verified event. Mode is the saved mode ('sandbox' or 'live').
     *
     * @return array{0: string, 1: ?string, 2: ?Payment, 3: ?string}  outcome, note, payment, provider reference
     */
    abstract protected function dispatch(array $event, string $mode): array;

    /**
     * Process one verified event and log it. Returns the log row.
     * Throws only for a real fault (database down...), so the controller can answer 500 and the provider retries.
     */
    public function handle(array $event, string $mode, ?string $eventId): PaymentEvent
    {
        $log = PaymentEvent::record([
            'gateway' => $this->gateway(),
            'external_id' => $eventId,
            'event' => mb_substr($this->eventName($event), 0, 80),
            'outcome' => 'received',
        ], $this->summary($event));

        // Same event delivered again and already dealt with: nothing to do.
        if (! $log->wasRecentlyCreated && in_array($log->outcome, ['processed', 'ignored'], true)) {
            return $log;
        }

        [$outcome, $note, $payment, $reference] = DB::transaction(fn () => $this->dispatch($event, $mode));

        $log->update([
            'outcome' => $outcome,
            'note' => $note === null ? null : mb_substr($note, 0, 255),
            'payment_id' => $payment?->id,
            'gateway_reference' => $reference ? mb_substr($reference, 0, 120) : null,
        ]);

        return $log;
    }

    // ---- charges ----------------------------------------------------------------------

    /**
     * Find our payment row. First by the provider's session / link id we saved. If that is missing (for example
     * the call to the provider timed out after it had created the page), by the payment id we put in the notes.
     * Also checks that the message belongs to this payment.
     *
     * @return array{0: ?Payment, 1: ?array}  the locked payment, or a ready result to return instead
     */
    protected function matchCharge(string $sessionId, mixed $metaPaymentId, mixed $metaBookingId, string $reference): array
    {
        $base = Payment::query()->where('type', 'charge')->where('gateway', $this->gateway());

        $payment = $sessionId !== '' ? (clone $base)->where('gateway_session_id', $sessionId)->first() : null;
        if (! $payment && $metaPaymentId !== null && ctype_digit((string) $metaPaymentId)) {
            $payment = (clone $base)->whereKey((int) $metaPaymentId)->first();
        }
        if (! $payment) {
            return [null, ['ignored', 'No matching payment in this system.', null, $reference]];
        }

        if ($metaBookingId !== null && (int) $metaBookingId !== (int) $payment->booking_id) {
            return [null, ['failed', 'Booking in the event does not match the payment. Check it by hand.', $payment, $reference]];
        }
        if ($payment->gateway_session_id && $sessionId !== '' && $payment->gateway_session_id !== $sessionId) {
            return [null, ['failed', 'Session in the event does not match the payment. Check it by hand.', $payment, $reference]];
        }

        // Lock the row so two deliveries at the same moment cannot both book it.
        return [Payment::query()->lockForUpdate()->find($payment->id), null];
    }

    protected function markSucceeded(Payment $payment, ?int $paidCents, string $currency, string $reference): array
    {
        if ($payment->status === 'succeeded') {
            return ['ignored', 'Payment was already marked succeeded.', $payment, $reference];
        }

        // The amount and currency collected must be what we asked for. If not, do not book it: a person must look.
        $expectedCents = (int) round(((float) $payment->amount) * 100);
        if ($paidCents !== $expectedCents || strtolower($currency) !== strtolower($payment->currency)) {
            return ['failed', 'Amount or currency from the provider does not match this payment. Not booked: check in the provider dashboard.', $payment, $reference];
        }

        $payment->update([
            'status' => 'succeeded',
            'failure_code' => null,
            'gateway_reference' => mb_substr($reference, 0, 120),
            'paid_at' => now(),
        ]);
        $payment->booking->syncPaymentStatus();

        return ['processed', 'Payment succeeded.', $payment, $reference];
    }

    protected function markFailed(Payment $payment, string $code, string $note, string $reference): array
    {
        // Only a payment still waiting can fail. Money that already arrived is never taken back by a failure message.
        if ($payment->status !== 'pending') {
            return ['ignored', 'Payment is already ' . $payment->status . '. Left as it is.', $payment, $reference];
        }

        $payment->update(['status' => 'failed', 'failure_code' => $code]);

        return ['processed', $note, $payment, $reference];
    }

    // ---- refunds ----------------------------------------------------------------------

    /**
     * One refund row per provider refund id. Created on the first message, updated by later ones.
     * $mapped is pending | succeeded | failed. $chargeReference is what we saved on the charge when it succeeded.
     */
    protected function applyRefund(string $refundId, ?string $chargeReference, int $cents, string $currency, string $mapped, ?string $failureReason): array
    {
        $row = Payment::query()->where('type', 'refund')->where('gateway', $this->gateway())->where('gateway_reference', $refundId)->lockForUpdate()->first();

        if ($row) {
            if ($row->status === $mapped) {
                return ['ignored', 'Refund was already ' . $mapped . '.', $row, $refundId];
            }
            if ($row->status === 'succeeded') {
                return ['ignored', 'Refund already succeeded. Left as it is.', $row, $refundId];
            }
            $row->update([
                'status' => $mapped,
                'failure_code' => $mapped === 'failed' ? mb_substr($failureReason ?: 'refund_failed', 0, 80) : null,
                'paid_at' => $mapped === 'succeeded' ? now() : $row->paid_at,
            ]);
            $row->booking->syncPaymentStatus();

            return ['processed', 'Refund is now ' . $mapped . '.', $row, $refundId];
        }

        // A refund we have not seen yet (made in the provider's dashboard). Find the charge it belongs to.
        $charge = $chargeReference
            ? Payment::query()->where('type', 'charge')->where('gateway', $this->gateway())->where('gateway_reference', $chargeReference)->first()
            : null;
        if (! $charge) {
            return ['ignored', 'No matching payment for this refund in this system.', null, $refundId];
        }
        if ($cents <= 0) {
            return ['failed', 'Refund event has no amount.', $charge, $refundId];
        }

        $row = Payment::create([
            'booking_id' => $charge->booking_id,
            'type' => 'refund',
            'channel' => $charge->channel,
            'gateway' => $this->gateway(),
            'gateway_reference' => mb_substr($refundId, 0, 120),
            'amount' => round($cents / 100, 2),
            'currency' => strtoupper($currency ?: $charge->currency),
            'method' => $charge->method,
            'status' => $mapped,
            'failure_code' => $mapped === 'failed' ? mb_substr($failureReason ?: 'refund_failed', 0, 80) : null,
            'paid_at' => $mapped === 'succeeded' ? now() : null,
            'note' => 'Refund made at the payment provider',
        ]);
        $charge->booking->syncPaymentStatus();

        return ['processed', 'Refund recorded (' . $mapped . ').', $row, $refundId];
    }
}
