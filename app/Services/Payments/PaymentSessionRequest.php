<?php

namespace App\Services\Payments;

/** Everything a provider needs to start a hosted payment. No card data, ever. */
final class PaymentSessionRequest
{
    public function __construct(
        public readonly string $bookingReference,
        public readonly int $bookingId,
        public readonly int $paymentId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly ?string $customerEmail,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly string $idempotencyKey,
        public readonly ?string $customerName = null,
    ) {
    }
}
