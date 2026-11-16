<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingTraveler> */
class BookingTravelerFactory extends Factory
{
    protected $model = BookingTraveler::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'is_lead' => false,
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'),
            'nationality' => fake()->randomElement(['American', 'British', 'Canadian', 'Australian', 'Indian', 'German']),
            'passport_number' => strtoupper(fake()->bothify('?#######')),
            'passport_expiry' => fake()->dateTimeBetween('+1 year', '+8 years')->format('Y-m-d'),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'arrival_date' => fake()->dateTimeBetween('+30 days', '+120 days')->format('Y-m-d'),
            'arrival_flight' => strtoupper(fake()->bothify('??###')),
            'arrival_time' => fake()->time('H:i:s'),
            'health_notes' => null,
            'flag_note' => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(fn () => ['is_lead' => true]);
    }

    public function withHealthNote(string $note = 'Mild back pain, avoid long treks'): static
    {
        return $this->state(fn () => ['health_notes' => $note]);
    }

    public function needsAssistance(): static
    {
        return $this->state(fn () => ['flag_note' => 'Needs wheelchair assistance on arrival']);
    }
}
