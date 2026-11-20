<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingTraveler;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bookings list, filters, CSV export and detail page (Day 6). */
class BookingListTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    private function make(array $attrs = [], ?string $customer = null): Booking
    {
        $user = User::factory()->create($customer ? ['name' => $customer] : []);
        $booking = Booking::factory()->create($attrs + ['user_id' => $user->id, 'travelers_count' => 1]);
        BookingTraveler::factory()->lead()->create(['booking_id' => $booking->id]);

        return $booking->fresh();
    }

    private function csvBody($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    // ------------------------------------------------------------------ list

    public function test_list_shows_bookings_with_view_and_edit_links(): void
    {
        $b = $this->make(['total_amount' => 1234.5]);

        $this->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee($b->reference)
            ->assertSee('1,234.50')
            ->assertSee(route('admin.bookings.edit', $b), false)
            ->assertSee(route('admin.bookings.show', $b), false);
    }

    public function test_list_search_finds_by_reference_customer_and_traveler(): void
    {
        $a = $this->make([], 'Zebulon Quimby');
        $b = $this->make([], 'Someone Else');

        $this->get(route('admin.bookings.index', ['q' => $a->reference]))->assertSee($a->reference)->assertDontSee($b->reference);
        $this->get(route('admin.bookings.index', ['q' => 'Quimby']))->assertSee($a->reference)->assertDontSee($b->reference);

        $name = $b->travelers->first()->full_name;
        $this->get(route('admin.bookings.index', ['q' => $name]))->assertSee($b->reference);
    }

    public function test_list_filters_by_status_payment_source_and_dates(): void
    {
        $confirmed = $this->make(['status' => 'confirmed', 'payment_status' => 'paid', 'source' => 'csr']);
        $pending = $this->make(['status' => 'pending', 'payment_status' => 'unpaid', 'source' => 'web']);
        $old = $this->make(['status' => 'pending', 'created_at' => now()->subDays(40)]);

        $this->get(route('admin.bookings.index', ['status' => 'confirmed']))->assertSee($confirmed->reference)->assertDontSee($pending->reference);
        $this->get(route('admin.bookings.index', ['payment_status' => 'unpaid']))->assertSee($pending->reference)->assertDontSee($confirmed->reference);
        $this->get(route('admin.bookings.index', ['source' => 'csr']))->assertSee($confirmed->reference)->assertDontSee($pending->reference);
        $this->get(route('admin.bookings.index', ['from' => now()->subDays(5)->format('Y-m-d')]))->assertDontSee($old->reference)->assertSee($pending->reference);
        $this->get(route('admin.bookings.index', ['to' => now()->subDays(30)->format('Y-m-d')]))->assertSee($old->reference)->assertDontSee($pending->reference);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->from(route('admin.bookings.index'))
            ->get(route('admin.bookings.index', ['status' => 'bogus', 'to' => '2026-01-01', 'from' => '2026-02-01']))
            ->assertSessionHasErrors(['status', 'to']);
    }

    public function test_list_is_paginated_and_keeps_filters(): void
    {
        foreach (range(1, 17) as $i) {
            $this->make(['status' => 'confirmed']);
        }

        $this->get(route('admin.bookings.index', ['status' => 'confirmed']))
            ->assertOk()->assertSee('17 bookings')->assertSee('page=2', false);
    }

    public function test_empty_list_message(): void
    {
        $this->get(route('admin.bookings.index', ['q' => 'nothing-matches']))->assertSee('No bookings match these filters.');
    }

    public function test_paid_column_is_net_of_refunds(): void
    {
        $b = $this->make(['total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000]);
        Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 300]);

        $this->get(route('admin.bookings.index'))->assertSee('700.00 paid');
    }

    // ---------------------------------------------------------------- export

    public function test_export_returns_csv_with_header_and_rows(): void
    {
        $b = $this->make(['total_amount' => 500, 'status' => 'confirmed']);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 200]);

        $response = $this->get(route('admin.bookings.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $body = $this->csvBody($response);
        $this->assertStringContainsString('Reference,Created,Customer', $body);
        $this->assertStringContainsString($b->reference, $body);
        $this->assertStringContainsString('500.00,200.00,confirmed', $body);
    }

    public function test_export_respects_filters(): void
    {
        $keep = $this->make(['status' => 'confirmed']);
        $skip = $this->make(['status' => 'pending']);

        $body = $this->csvBody($this->get(route('admin.bookings.export', ['status' => 'confirmed'])));

        $this->assertStringContainsString($keep->reference, $body);
        $this->assertStringNotContainsString($skip->reference, $body);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        $this->make([], '=HYPERLINK("http://evil.example","click")');

        $body = $this->csvBody($this->get(route('admin.bookings.export')));

        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString(',"=HYPERLINK', $body);
    }

    public function test_export_never_contains_passport_or_health_data(): void
    {
        $b = $this->make();
        $b->travelers->first()->update(['passport_number' => 'SECRETPASS77', 'health_notes' => 'Allergic to penicillin']);

        $body = $this->csvBody($this->get(route('admin.bookings.export')));

        $this->assertStringNotContainsString('SECRETPASS77', $body);
        $this->assertStringNotContainsString('penicillin', $body);
    }

    // ---------------------------------------------------------------- detail

    public function test_detail_shows_booking_travelers_payments_and_history(): void
    {
        $b = $this->make(['total_amount' => 800, 'notes' => 'Window seat please']);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 300, 'gateway_reference' => 'REF-ABC123']);
        BookingChange::create([
            'booking_id' => $b->id, 'reason' => 'Fixed phone',
            'old_values' => ['booking' => ['insurance_selected' => false], 'travelers' => []],
            'new_values' => ['booking' => ['insurance_selected' => true], 'travelers' => []],
        ]);

        $this->get(route('admin.bookings.show', $b))
            ->assertOk()
            ->assertSee($b->reference)
            ->assertSee($b->travelers->first()->full_name)
            ->assertSee('Window seat please')
            ->assertSee('REF-ABC123')
            ->assertSee('300.00')          // paid
            ->assertSee('500.00')          // balance
            ->assertSee('Fixed phone')
            ->assertSee(route('admin.bookings.edit', $b), false);
    }

    public function test_detail_hides_health_notes_and_full_passport_number(): void
    {
        $b = $this->make();
        $b->travelers->first()->update(['passport_number' => 'SECRETPASS77', 'health_notes' => 'Allergic to penicillin']);

        $this->get(route('admin.bookings.show', $b))
            ->assertOk()
            ->assertSee('ending S77')
            ->assertDontSee('SECRETPASS77')
            ->assertDontSee('penicillin');
    }

    public function test_detail_for_missing_booking_is_404(): void
    {
        $this->get('/admin/bookings/99999')->assertNotFound();
    }
}
