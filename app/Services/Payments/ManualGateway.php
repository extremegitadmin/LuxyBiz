<?php

namespace App\Services\Payments;

/** Default adapter: no online provider. Staff record payments that happened outside the system. */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function supportsPaymentSessions(): bool
    {
        return false;
    }

    public function assertReady(): void
    {
        throw new GatewayException('No online payment provider is set up. Choose one under Settings, Payment gateway.');
    }

    public function createPaymentSession(PaymentSessionRequest $request): PaymentSession
    {
        throw new GatewayException('No online payment provider is set up. Choose one under Settings, Payment gateway.');
    }

    public function checkConnection(): string
    {
        throw new GatewayException('Manual needs no connection. Choose an online provider to test it.');
    }
}
