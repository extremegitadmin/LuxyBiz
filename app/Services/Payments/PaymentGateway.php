<?php

namespace App\Services\Payments;

/**
 * What every payment provider adapter must do. The admin screens only talk to this interface,
 * so Stripe, Razorpay or PayPal can be added without changing any screen.
 * Refunds are added to this interface on the refund days.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Can this provider create a hosted payment page for a customer? */
    public function supportsPaymentSessions(): bool;

    /**
     * Check the saved settings BEFORE anything is created: keys present and matching the mode.
     * Makes no request to the provider.
     *
     * @throws GatewayException
     */
    public function assertReady(): void;

    /** @throws GatewayException */
    public function createPaymentSession(PaymentSessionRequest $request): PaymentSession;

    /**
     * Check that the saved keys work. Returns a short message for the screen.
     *
     * @throws GatewayException
     */
    public function checkConnection(): string;
}
