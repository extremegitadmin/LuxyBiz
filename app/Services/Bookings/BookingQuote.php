<?php

namespace App\Services\Bookings;

/** The price and seat check for one booking request. Money is in the tour's currency. */
final class BookingQuote
{
    public function __construct(
        public readonly string $currency,
        public readonly float $unitPrice,
        public readonly int $travelers,
        public readonly float $subtotal,
        public readonly float $insuranceAmount,
        public readonly float $total,
        public readonly int $seatsLeft,
    ) {
    }

    /** @return array<string, int|float|string> */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency, 'unit_price' => $this->unitPrice, 'travelers' => $this->travelers,
            'subtotal' => $this->subtotal, 'insurance_amount' => $this->insuranceAmount, 'total' => $this->total, 'seats_left' => $this->seatsLeft,
        ];
    }
}
