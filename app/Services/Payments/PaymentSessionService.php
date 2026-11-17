<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;

/**
 * Starts an online payment for a booking: saves a PENDING payment, asks the provider for a hosted
 * payment page and returns its link. The payment becomes succeeded or failed only when the provider's
 * webhook arrives (Day 12). Visiting the return page proves nothing and changes nothing.
 */
class PaymentSessionService
{
    /** Bookings that can still collect money. */
    public const PAYABLE_STATUSES = ['pending', 'confirmed'];

    /**
     * @return array{payment: Payment, url: string}
     *
     * @throws GatewayException  with a message that is safe to show to staff
     */
    public function start(Booking $booking, float $amount, ?User $staff = null, string $channel = 'csr_link'): array
    {
        $gateway = Gateway::resolve();
        if (! $gateway->supportsPaymentSessions()) {
            throw new GatewayException('No online payment provider is set up. Choose one under Settings, Payment gateway.');
        }
        $gateway->assertReady();   // a settings problem must not leave a failed payment row behind

        $amount = round($amount, 2);
        $balance = self::balanceDue($booking);

        if (! in_array($booking->status, self::PAYABLE_STATUSES, true)) {
            throw new GatewayException('This booking is ' . str_replace('_', ' ', $booking->status) . ' and cannot take a payment.');
        }
        if ($amount <= 0) {
            throw new GatewayException('The amount must be more than zero.');
        }
        if ($amount > $balance) {
            throw new GatewayException('The amount is more than the balance due (' . $booking->currency . ' ' . number_format($balance, 2) . ').');
        }

        // Double click or double submit: the same booking and amount within a minute is almost surely a repeat.
        $repeat = Payment::query()->where('booking_id', $booking->id)->where('type', 'charge')->where('status', 'pending')
            ->whereNotNull('gateway_session_id')->where('amount', $amount)->where('created_at', '>=', now()->subMinute())->exists();
        if ($repeat) {
            throw new GatewayException('A payment link for this amount was just created. Check the Payments table below before creating another.');
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'type' => 'charge',
            'channel' => $channel,
            'gateway' => $gateway->name(),
            'amount' => $amount,
            'currency' => $booking->currency,
            'method' => 'card',
            'status' => 'pending',
            'recorded_by' => $staff?->id,
        ]);

        try {
            $session = $gateway->createPaymentSession(new PaymentSessionRequest(
                bookingReference: $booking->reference,
                bookingId: $booking->id,
                paymentId: $payment->id,
                amount: $amount,
                currency: $booking->currency,
                description: 'LuxyBiz tour booking ' . $booking->reference . ' (travel services)',
                customerEmail: $booking->user?->email,
                successUrl: route('payment.return'),
                cancelUrl: route('payment.cancelled'),
                idempotencyKey: 'payment-' . $payment->id,
            ));
        } catch (GatewayException $e) {
            $payment->update(['status' => 'failed', 'failure_code' => 'session_error']);

            throw $e;
        }

        $payment->update(['gateway_session_id' => $session->id]);

        return ['payment' => $payment->fresh(), 'url' => $session->url];
    }

    /** What is still unpaid: total minus money actually received (net of refunds). */
    public static function balanceDue(Booking $booking): float
    {
        return max(0, round((float) $booking->total_amount - $booking->paidAmount(), 2));
    }

    /**
     * Payment links created in the last 23 hours that nobody has paid or cancelled yet.
     * Two paid links for the same balance would overpay, so the booking page warns about these.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    public static function openLinks(Booking $booking)
    {
        return Payment::query()->where('booking_id', $booking->id)->where('type', 'charge')->where('status', 'pending')
            ->whereNotNull('gateway_session_id')->where('created_at', '>=', now()->subHours(23))->get();
    }
}
