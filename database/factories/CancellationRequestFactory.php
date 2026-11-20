<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\CancellationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CancellationRequest> */
class CancellationRequestFactory extends Factory
{
    protected $model = CancellationRequest::class;

    public function definition(): array
    {
        return [
            'reference' => CancellationRequest::generateReference(),
            'booking_id' => Booking::factory(),
            'source' => 'staff',
            'reason' => fake()->randomElement(['Change of plans', 'Medical reason', 'Visa refused', null]),
            'requested_on' => now()->toDateString(),
            'status' => 'pending',
            'currency' => 'USD',
            'calculated_refund' => fake()->randomElement([0, 725, 1450, 2500]),
            'calculation' => null,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
