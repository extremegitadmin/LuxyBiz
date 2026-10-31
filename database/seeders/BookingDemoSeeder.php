<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Asha - demo bookings, travelers and payments so every screen has data to show.
 * Run:  php artisan db:seed --class=BookingDemoSeeder
 * Safe to run twice: it does nothing if demo bookings already exist.
 * tour_id values 1-3 are placeholders until Sini's tours are merged and seeded.
 */
class BookingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::where('notes', 'like', '[demo]%')->exists()) {
            $this->command?->line('Demo bookings already exist. Nothing changed.');

            return;
        }

        // Demo customers
        $customers = collect(['Emma Carter', 'Liam Walker', 'Olivia Bennett', 'Noah Fischer', 'Ava Thomas', 'Ethan Brooks'])
            ->map(fn ($name) => User::firstOrCreate(
                ['email' => strtolower(str_replace(' ', '.', $name)) . '@example.com'],
                ['name' => $name, 'password' => bcrypt('demo-password-2026')]
            ));

        // [booking status, payment scenario]
        $scenarios = [
            ['pending', 'unpaid'], ['pending', 'unpaid'], ['pending', 'failed'],
            ['confirmed', 'partial'], ['confirmed', 'partial'],
            ['confirmed', 'paid'], ['confirmed', 'paid'], ['confirmed', 'paid'], ['confirmed', 'paid'],
            ['completed', 'paid'], ['completed', 'paid'],
            ['cancelled', 'refunded'], ['cancelled', 'refunded'],
            ['cancellation_requested', 'paid'], ['confirmed', 'paid'],
        ];

        foreach ($scenarios as $i => [$status, $scenario]) {
            $booking = Booking::factory()->create([
                'user_id' => $customers[$i % $customers->count()]->id,
                'status' => $status,
                'notes' => '[demo] seeded booking',
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            // Travelers: the first one is the lead traveler
            for ($t = 0; $t < $booking->travelers_count; $t++) {
                $factory = BookingTraveler::factory();
                if ($t === 0) {
                    $factory = $factory->lead();
                }
                if ($i % 5 === 1 && $t === 0) {
                    $factory = $factory->withHealthNote();
                }
                if ($i % 7 === 3 && $t === 0) {
                    $factory = $factory->needsAssistance();
                }
                $factory->create(['booking_id' => $booking->id]);
            }

            // Payments for the scenario
            $total = (float) $booking->total_amount;
            match ($scenario) {
                'partial' => Payment::factory()->create(['booking_id' => $booking->id, 'amount' => round($total * 0.4, 2)]),
                'paid' => Payment::factory()->create(['booking_id' => $booking->id, 'amount' => $total]),
                'failed' => Payment::factory()->failed()->create(['booking_id' => $booking->id, 'amount' => $total]),
                'refunded' => $this->chargeAndRefund($booking, $total),
                default => null,
            };

            $booking->syncPaymentStatus();
        }

        $this->command?->info('Demo data created: ' . Booking::count() . ' bookings, '
            . BookingTraveler::count() . ' travelers, ' . Payment::count() . ' payments.');
    }

    private function chargeAndRefund(Booking $booking, float $total): void
    {
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => $total]);
        Payment::factory()->refund()->create(['booking_id' => $booking->id, 'amount' => $total]);
    }
}
