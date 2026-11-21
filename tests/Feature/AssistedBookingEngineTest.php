<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Bookings\BookingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Days 26-27: the shared booking engine (rules, price, seats). */
class AssistedBookingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tour(array $a = []): Tour
    {
        return Tour::create($a + ['title' => 'Kerala Wellness', 'slug' => 'kerala-' . uniqid(), 'base_price' => 1000, 'currency' => 'USD', 'status' => 'published']);
    }

    protected function departure(Tour $t, array $a = []): TourDeparture
    {
        return TourDeparture::create($a + ['tour_id' => $t->id, 'departs_on' => now()->addDays(60)->toDateString(), 'capacity' => 6, 'status' => 'open']);
    }

    protected function customer(): User
    {
        return User::query()->where('email', 'olivia@example.com')->first() ?? User::forceCreate(['name' => 'Olivia Bennett', 'email' => 'olivia@example.com', 'password' => bcrypt('x'), 'phone' => '+1 555 010 4242']);
    }

    protected function data(Tour $t, TourDeparture $d, int $n = 2, array $extra = []): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['full_name' => 'Traveler ' . ($i + 1)];
        }

        return $extra + ['tour_id' => $t->id, 'tour_departure_id' => $d->id, 'travelers' => $rows, 'insurance_selected' => false];
    }

    public function test_quote_uses_base_price_and_departure_override(): void
    {
        $t = $this->tour();
        $plain = $this->departure($t);
        $special = $this->departure($t, ['price_override' => 800]);
        $e = app(BookingEngine::class);

        $this->assertSame(2000.0, $e->quote($t, $plain, 2, false)->total);
        $this->assertSame(1600.0, $e->quote($t, $special, 2, false)->total);
    }

    public function test_insurance_is_per_traveler_and_per_currency(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        $q = app(BookingEngine::class)->quote($t, $d, 3, true);
        $this->assertSame(255.0, $q->insuranceAmount);
        $this->assertSame(3255.0, $q->total);

        $inr = $this->tour(['currency' => 'INR', 'base_price' => 50000]);
        $this->assertSame(13000.0, app(BookingEngine::class)->quote($inr, $this->departure($inr), 2, true)->insuranceAmount);
    }

    public function test_create_makes_a_pending_unpaid_booking_with_lead_traveler(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        $staff = User::forceCreate(['name' => 'Agent', 'email' => 'agent@example.test', 'password' => bcrypt('x')]);

        $b = app(BookingEngine::class)->create($this->data($t, $d, 2, ['insurance_selected' => true, 'notes' => 'Window seat']), 'csr', $this->customer(), $staff);

        $this->assertSame('pending', $b->status);
        $this->assertSame('unpaid', $b->payment_status);
        $this->assertSame('csr', $b->source);
        $this->assertSame($staff->id, $b->created_by);
        $this->assertSame(2, $b->travelers_count);
        $this->assertEquals(2170.0, (float) $b->total_amount);
        $this->assertSame(2, $b->travelers()->count());
        $lead = $b->travelers()->where('is_lead', true)->first();
        $this->assertSame('olivia@example.com', $lead->email);   // defaults to the customer's contact
        $this->assertSame(1, $b->travelers()->where('is_lead', true)->count());
    }

    public function test_website_source_is_allowed_and_unknown_source_is_refused(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        $b = app(BookingEngine::class)->create($this->data($t, $d, 1), 'web', $this->customer());
        $this->assertSame('web', $b->source);
        $this->assertNull($b->created_by);

        $this->expectException(\InvalidArgumentException::class);
        app(BookingEngine::class)->create($this->data($t, $d, 1), 'admin', $this->customer());
    }

    public function test_the_total_is_calculated_on_the_server_not_taken_from_the_request(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        $b = app(BookingEngine::class)->create($this->data($t, $d, 1, ['total_amount' => 1, 'subtotal' => 1, 'status' => 'confirmed', 'payment_status' => 'paid']), 'csr', $this->customer());
        $this->assertEquals(1000.0, (float) $b->total_amount);
        $this->assertSame('pending', $b->status);
        $this->assertSame('unpaid', $b->payment_status);
    }

    public function test_refuses_unpublished_tours_closed_and_past_departures(): void
    {
        $e = app(BookingEngine::class);
        $draft = $this->tour(['status' => 'draft']);
        $t = $this->tour();
        $cases = [
            'draft tour' => [$draft, $this->departure($draft)],
            'closed departure' => [$t, $this->departure($t, ['status' => 'closed'])],
            'past departure' => [$t, $this->departure($t, ['departs_on' => now()->subDay()->toDateString()])],
            'departure today' => [$t, $this->departure($t, ['departs_on' => now()->toDateString()])],
        ];
        foreach ($cases as $name => [$tour, $dep]) {
            try {
                $e->quote($tour, $dep, 1, false);
                $this->fail("Accepted: $name");
            } catch (\DomainException $x) {
                $this->assertNotSame('', $x->getMessage(), $name);
            }
        }
    }

    public function test_refuses_a_departure_of_another_tour(): void
    {
        $a = $this->tour();
        $b = $this->tour();
        $this->expectException(\DomainException::class);
        app(BookingEngine::class)->quote($a, $this->departure($b), 1, false);
    }

    public function test_seats_are_counted_and_cancelled_bookings_free_them(): void
    {
        $t = $this->tour();
        $d = $this->departure($t, ['capacity' => 3]);
        $e = app(BookingEngine::class);

        $first = $e->create($this->data($t, $d, 2), 'csr', $this->customer());
        $this->assertSame(1, $e->seatsLeft($d));

        try {
            $e->create($this->data($t, $d, 2), 'csr', $this->customer());
            $this->fail('Overbooked');
        } catch (\DomainException $x) {
            $this->assertStringContainsString('seat', strtolower($x->getMessage()));
        }
        $this->assertSame(1, Booking::count());

        $first->update(['status' => 'cancelled']);
        $this->assertSame(3, $e->seatsLeft($d));
        $e->create($this->data($t, $d, 3), 'csr', $this->customer());
        $this->assertSame(0, $e->seatsLeft($d));
    }

    public function test_group_size_limits(): void
    {
        $t = $this->tour();
        $d = $this->departure($t, ['capacity' => 50]);
        $e = app(BookingEngine::class);
        foreach ([0, (int) config('booking.max_travelers') + 1] as $n) {
            try {
                $e->quote($t, $d, $n, false);
                $this->fail("Accepted $n travelers");
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame((int) config('booking.max_travelers'), $e->quote($t, $d, (int) config('booking.max_travelers'), false)->travelers);
    }

    public function test_a_tour_with_no_price_cannot_be_booked(): void
    {
        $t = $this->tour(['base_price' => 0]);
        $this->expectException(\DomainException::class);
        app(BookingEngine::class)->quote($t, $this->departure($t), 1, false);
    }

    public function test_a_failed_create_leaves_no_partial_booking(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        \App\Models\BookingTraveler::creating(fn () => throw new \RuntimeException('boom'));
        try {
            app(BookingEngine::class)->create($this->data($t, $d, 2), 'csr', $this->customer());
            $this->fail('should throw');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, Booking::count());
    }

    public function test_bookable_departures_hide_full_closed_and_unpublished(): void
    {
        $t = $this->tour();
        $open = $this->departure($t);
        $this->departure($t, ['status' => 'closed']);
        $full = $this->departure($t, ['capacity' => 1]);
        app(BookingEngine::class)->create($this->data($t, $full, 1), 'csr', $this->customer());

        $ids = app(BookingEngine::class)->bookableDepartures($t)->pluck('departure.id')->all();
        $this->assertSame([$open->id], $ids);
        $this->assertTrue(app(BookingEngine::class)->bookableDepartures($this->tour(['status' => 'archived']))->isEmpty());
    }

    public function test_traveler_text_is_cleaned(): void
    {
        $t = $this->tour();
        $d = $this->departure($t);
        $b = app(BookingEngine::class)->create(['tour_id' => $t->id, 'tour_departure_id' => $d->id,
            'travelers' => [['full_name' => "  <b>Anna</b>\x00 Rao  "]]], 'csr', $this->customer());
        $name = $b->travelers()->first()->full_name;
        $this->assertStringNotContainsString("\x00", $name);
        $this->assertSame($name, trim($name));
    }
}
