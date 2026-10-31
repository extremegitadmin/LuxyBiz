<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $travelers = fake()->numberBetween(1, 4);
        $subtotal = $travelers * fake()->randomElement([1450, 1890, 2250, 2800, 3400]);
        $insurance = fake()->boolean(40) ? $travelers * 85 : 0;

        return [
            'reference' => Booking::generateReference(),
            'user_id' => User::factory(),
            'tour_id' => fake()->numberBetween(1, 3),   // real tours arrive with Sini's module
            'tour_departure_id' => null,
            'travelers_count' => $travelers,
            'subtotal' => $subtotal,
            'insurance_amount' => $insurance,
            'total_amount' => $subtotal + $insurance,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'source' => 'web',
            'insurance_selected' => $insurance > 0,
            'notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function csr(User $staff): static
    {
        return $this->state(fn () => ['source' => 'csr', 'created_by' => $staff->id]);
    }
}
