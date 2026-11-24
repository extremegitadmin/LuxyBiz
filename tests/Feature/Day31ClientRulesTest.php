<?php

namespace Tests\Feature;

use App\Console\Commands\CancellationApplyClientRules;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Bookings\BookingEngine;
use App\Services\Cancellations\CancellationPolicy;
use App\Services\Cancellations\RefundCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Client rules of 23 Nov: refund tiers, booking cut-off (default 5, per tour), unlimited seats, no travel credit. */
class Day31ClientRulesTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function tour(array $a = []): Tour
    {
        return Tour::create($a + ['title' => 'Kerala Wellness', 'slug' => 'k-' . uniqid(), 'base_price' => 1000, 'currency' => 'USD', 'status' => 'published']);
    }

    private function departure(Tour $t, int $daysAhead, ?int $capacity = 6): TourDeparture
    {
        return TourDeparture::create(['tour_id' => $t->id, 'departs_on' => now()->addDays($daysAhead)->toDateString(), 'capacity' => $capacity, 'status' => 'open']);
    }

    private function customer(): User
    {
        return User::query()->where('email', 'o@example.com')->first() ?? User::forceCreate(['name' => 'Olivia', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
    }

    private function data(Tour $t, TourDeparture $d, int $n = 1): array
    {
        return ['tour_id' => $t->id, 'tour_departure_id' => $d->id, 'travelers' => array_fill(0, $n, ['full_name' => 'Traveler'])];
    }

    // ------------------------------------------------------------ refund tiers (client's rules)

    private function refundPercent(int $daysBefore): int
    {
        $b = Booking::factory()->create(['user_id' => $this->customer()->id, 'status' => 'confirmed', 'currency' => 'USD', 'subtotal' => 1000, 'insurance_amount' => 0, 'total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000, 'currency' => 'USD', 'status' => 'succeeded']);

        return (new RefundCalculator())->calculate($b->fresh(), now(), null, now()->addDays($daysBefore))->percent;
    }

    public function test_the_command_saves_the_client_tiers(): void
    {
        $this->artisan('cancellation:apply-client-rules')->assertSuccessful();
        $p = CancellationPolicy::current();
        $this->assertSame(CancellationApplyClientRules::TIERS, $p->tiers);
        $this->assertSame('tour_price', $p->base);
        $this->assertSame([7, 10], [$p->refundDaysMin, $p->refundDaysMax]);
        $this->assertFalse($p->confirmed, 'the command must not confirm the rules by itself');

        $this->artisan('cancellation:apply-client-rules', ['--confirm' => true])->assertSuccessful();
        $this->assertTrue(CancellationPolicy::current()->confirmed);
    }

    public function test_client_tiers_give_the_refund_percent_the_client_listed(): void
    {
        $this->artisan('cancellation:apply-client-rules');
        // more than 30 days 90, 15-30 75, 3-14 50, 48-72 hours 25, under 48 hours or after departure 0
        $expected = [90 => 90, 31 => 90, 30 => 75, 15 => 75, 14 => 50, 3 => 50, 2 => 25, 1 => 0, 0 => 0, -1 => 0];
        foreach ($expected as $days => $percent) {
            $this->assertSame($percent, $this->refundPercent($days), "$days days before departure");
        }
    }

    public function test_the_commands_percent_is_applied_to_the_money(): void
    {
        $this->artisan('cancellation:apply-client-rules');
        $b = Booking::factory()->create(['user_id' => $this->customer()->id, 'status' => 'confirmed', 'currency' => 'USD', 'subtotal' => 1000, 'insurance_amount' => 0, 'total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000, 'currency' => 'USD', 'status' => 'succeeded']);
        $r = (new RefundCalculator())->calculate($b->fresh(), now(), null, now()->addDays(20));
        $this->assertEquals(750.0, $r->refund);   // 75% of 1000 (the client's own example: cancel in the 15-30 day band)
    }

    // ------------------------------------------------------------ booking cut-off

    public function test_default_cutoff_is_five_days(): void
    {
        $this->assertSame(5, config('booking.cutoff_days'));
        $this->assertSame(5, app(BookingEngine::class)->cutoffDays($this->tour()));
    }

    public function test_cutoff_boundary_on_the_website_and_staff_flow(): void
    {
        $t = $this->tour();
        $e = app(BookingEngine::class);

        $this->assertNotNull($e->quote($t, $this->departure($t, 5), 1, false));   // exactly 5 days: still bookable
        foreach ([4, 1] as $days) {
            try {
                $e->quote($t, $this->departure($t, $days), 1, false);
                $this->fail("$days days before departure was accepted");
            } catch (\DomainException $x) {
                $this->assertStringContainsString('closed 5 days', $x->getMessage());
            }
        }
        $this->assertSame(1, $e->create($this->data($t, $this->departure($t, 9)), 'web', $this->customer())->travelers_count);
    }

    public function test_cutoff_can_be_set_per_tour(): void
    {
        $e = app(BookingEngine::class);
        $long = $this->tour(['booking_cutoff_days' => 14]);
        $short = $this->tour(['booking_cutoff_days' => 2]);

        $this->assertSame(14, app(BookingEngine::class)->cutoffDays($long));
        $this->expectException(\DomainException::class);
        try {
            $e->quote($short, $this->departure($short, 2), 1, false);   // 2 days is fine for the short tour
            $e->quote($short, $this->departure($short, 1), 1, false);   // 1 is not
        } catch (\DomainException $x) {
            $this->assertStringContainsString('closed 2 days', $x->getMessage());
            $e->quote($long, $this->departure($long, 13), 1, false);    // 13 is too late for the long tour
        }
    }

    public function test_bookable_list_hides_departures_inside_the_cutoff(): void
    {
        $t = $this->tour();
        $late = $this->departure($t, 3);
        $ok = $this->departure($t, 6);
        $e = app(BookingEngine::class);

        $this->assertSame([$ok->id], $e->bookableDepartures($t)->pluck('departure.id')->all());
        $withOverride = $e->bookableDepartures($t, true);
        $this->assertEqualsCanonicalizing([$late->id, $ok->id], $withOverride->pluck('departure.id')->all());
        $this->assertTrue($withOverride->firstWhere('departure.id', $late->id)['inside_cutoff']);
        $this->assertFalse($withOverride->firstWhere('departure.id', $ok->id)['inside_cutoff']);
    }

    public function test_staff_with_the_override_permission_can_book_inside_the_cutoff_and_it_is_logged(): void
    {
        $this->ensureAuditTable();
        $t = $this->tour();
        $late = $this->departure($t, 2);
        $form = fn () => ['customer_mode' => 'new', 'customer_name' => 'Maya', 'customer_email' => 'maya@example.com',
            'tour_id' => $t->id, 'tour_departure_id' => $late->id, 'travelers' => [['full_name' => 'Maya']]];

        $this->actingAs($this->userWith(['admin.assisted.create'], 'plain@example.test', 'Plain'));
        $this->post(route('admin.assisted.store'), $form())->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());

        $this->actingAs($this->userWith(['admin.assisted.create', 'admin.assisted.override_cutoff'], 'boss@example.test', 'Lead'));
        $this->post(route('admin.assisted.store'), $form())->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Booking::count());
        $log = \DB::table('activity_logs')->where('action', 'booking.created')->first();
        $this->assertTrue((bool) json_decode($log->properties, true)['cutoff_overridden']);
    }

    public function test_the_website_cannot_use_the_staff_override(): void
    {
        $t = $this->tour();
        $this->expectException(\DomainException::class);
        app(BookingEngine::class)->create($this->data($t, $this->departure($t, 2)), 'web', $this->customer());
    }

    public function test_blank_cutoff_falls_back_to_the_default_and_a_changed_default_is_used(): void
    {
        $t = $this->tour(['booking_cutoff_days' => null]);
        config(['booking.cutoff_days' => 7]);
        $e = app(BookingEngine::class);
        $this->assertSame(7, $e->cutoffDays($t));
        $this->assertSame(0, $e->cutoffDays($this->tour(['booking_cutoff_days' => 0])));   // 0 is a real value: no cut-off
    }

    public function test_the_staff_form_shows_the_cutoff_and_marks_late_departures_for_override_users(): void
    {
        $t = $this->tour();
        $this->departure($t, 3);
        $this->departure($t, 12);

        $this->actingAs($this->userWith(['admin.assisted.create'], 'a@example.test', 'A'));
        $page = $this->get(route('admin.assisted.create'))->assertOk()->getContent();
        preg_match('/const tours = (\[.*?\]);\n/s', $page, $m);
        $tours = json_decode($m[1], true);
        $this->assertCount(1, $tours[0]['departures']);
        $this->assertSame(5, $tours[0]['cutoff']);

        $this->actingAs($this->userWith(['admin.assisted.create', 'admin.assisted.override_cutoff'], 'b@example.test', 'B'));
        preg_match('/const tours = (\[.*?\]);\n/s', $this->get(route('admin.assisted.create'))->getContent(), $m);
        $this->assertCount(2, json_decode($m[1], true)[0]['departures']);
    }

    // ------------------------------------------------------------ unlimited seats

    public function test_blank_capacity_means_unlimited_seats(): void
    {
        $t = $this->tour();
        $d = $this->departure($t, 30, null);
        $e = app(BookingEngine::class);

                $this->assertNull($e->seatsLeft($d));
        for ($i = 0; $i < 5; $i++) {
            $e->create($this->data($t, $d, 10), 'web', $this->customer());   // 50 travelers and still open
        }
        $this->assertSame(50, (int) Booking::sum('travelers_count'));
        $this->assertNull($e->quote($t, $d, 3, false)->seatsLeft);
        $this->assertSame([$d->id], $e->bookableDepartures($t)->pluck('departure.id')->all());
    }

    public function test_a_capacity_that_is_set_still_limits_seats(): void
    {
        $t = $this->tour();
        $d = $this->departure($t, 30, 3);
        $e = app(BookingEngine::class);
        $e->create($this->data($t, $d, 3), 'web', $this->customer());
        $this->assertSame(0, $e->seatsLeft($d));
        $this->expectException(\DomainException::class);
        $e->create($this->data($t, $d, 1), 'web', $this->customer());
    }

    // ------------------------------------------------------------ travel credit is off

    public function test_travel_credit_is_hidden_and_refused_by_default(): void
    {
        $this->signInAsSuperAdmin();
        $this->assertFalse(config('booking.credits_enabled'));

        $this->get(route('admin.settings.cancellation'))->assertOk()->assertDontSee('Travel credit instead of a refund');

        $b = Booking::factory()->create(['user_id' => $this->customer()->id, 'status' => 'confirmed', 'total_amount' => 1000, 'subtotal' => 1000, 'insurance_amount' => 0, 'currency' => 'USD']);
        $r = \App\Models\CancellationRequest::factory()->create(['booking_id' => $b->id, 'status' => 'approved']);
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertDontSee('Travel credit instead')->assertDontSee('Issue credit');

        $this->post(route('admin.cancellations.credit', $r))->assertSessionHasErrors('completion');
        $this->assertSame(0, \DB::table('travel_credits')->count());
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_saving_the_settings_still_works_with_credit_hidden(): void
    {
        $this->signInAsSuperAdmin();
        $page = $this->get(route('admin.settings.cancellation'))->getContent();
        $this->assertStringContainsString('name="credit_percent"', $page);   // kept as hidden fields so the form still validates
        $this->assertStringContainsString('type="hidden" name="credit_months"', $page);
    }

    private function ensureAuditTable(): void
    {
        if (! \Schema::hasTable('activity_logs')) {
            \Schema::create('activity_logs', function ($t) {
                $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('action'); $t->text('description')->nullable(); $t->text('properties')->nullable(); $t->timestamps();
            });
        }
    }
}
