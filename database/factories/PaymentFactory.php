<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'type' => 'charge',
            'channel' => 'offline',
            'gateway' => null,
            'gateway_reference' => strtoupper(fake()->bothify('REF-########')),
            'amount' => fake()->randomElement([500, 1000, 1500, 2500]),
            'currency' => 'USD',
            'method' => fake()->randomElement(['bank_transfer', 'card_offline', 'cash']),
            'status' => 'succeeded',
            'paid_at' => now(),
            'note' => null,
        ];
    }

    public function refund(): static
    {
        return $this->state(fn () => ['type' => 'refund', 'note' => 'Refund']);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed', 'paid_at' => null, 'failure_code' => 'card_declined']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'paid_at' => null]);
    }
}
