<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Payments list (E4): filters, search, totals, badges (Day 7). */
class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminRole::class);
    }

    private function payment(array $attrs = [], ?string $customer = null, ?string $email = null): Payment
    {
        $user = User::factory()->create(array_filter(['name' => $customer, 'email' => $email]));
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        return Payment::factory()->create($attrs + ['booking_id' => $booking->id]);
    }

    public function test_list_shows_payments_with_booking_link_and_badge(): void
    {
        $p = $this->payment(['amount' => 1234.5, 'gateway_reference' => 'REF-ZZ1']);

        $this->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($p->booking->reference)
            ->assertSee('REF-ZZ1')
            ->assertSee('USD 1,234.50')
            ->assertSee('Succeeded')
            ->assertSee(route('admin.bookings.show', $p->booking_id), false);
    }

    public function test_refunds_are_shown_as_negative(): void
    {
        $this->payment(['type' => 'refund', 'amount' => 300]);

        $this->get(route('admin.payments.index'))->assertSee('-USD 300.00');
    }

    public function test_search_by_booking_reference_gateway_reference_and_customer(): void
    {
        $a = $this->payment(['gateway_reference' => 'REF-AAA'], 'Zebulon Quimby', 'zeb@example.com');
        $b = $this->payment(['gateway_reference' => 'REF-BBB'], 'Someone Else');

        $this->get(route('admin.payments.index', ['q' => $a->booking->reference]))->assertSee('REF-AAA')->assertDontSee('REF-BBB');
        $this->get(route('admin.payments.index', ['q' => 'REF-BBB']))->assertSee('REF-BBB')->assertDontSee('REF-AAA');
        $this->get(route('admin.payments.index', ['q' => 'Quimby']))->assertSee('REF-AAA')->assertDontSee('REF-BBB');
        $this->get(route('admin.payments.index', ['q' => 'zeb@example']))->assertSee('REF-AAA')->assertDontSee('REF-BBB');
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->payment(['gateway_reference' => 'REF-AAA']);

        $this->get(route('admin.payments.index', ['q' => '%']))->assertSee('No payments match these filters.');
        $this->get(route('admin.payments.index', ['q' => 'REF_AAA']))->assertSee('No payments match these filters.');
    }

    public function test_filters_by_type_status_channel_method_and_dates(): void
    {
        $charge = $this->payment(['gateway_reference' => 'REF-CHG', 'channel' => 'online', 'method' => 'card']);
        $refund = $this->payment(['gateway_reference' => 'REF-RFD', 'type' => 'refund', 'channel' => 'offline', 'method' => 'cash']);
        $failed = $this->payment(['gateway_reference' => 'REF-FLD', 'status' => 'failed']);
        $old = $this->payment(['gateway_reference' => 'REF-OLD', 'created_at' => now()->subDays(40)]);

        $this->get(route('admin.payments.index', ['type' => 'refund']))->assertSee('REF-RFD')->assertDontSee('REF-CHG');
        $this->get(route('admin.payments.index', ['status' => 'failed']))->assertSee('REF-FLD')->assertDontSee('REF-CHG');
        $this->get(route('admin.payments.index', ['channel' => 'online']))->assertSee('REF-CHG')->assertDontSee('REF-RFD');
        $this->get(route('admin.payments.index', ['method' => 'cash']))->assertSee('REF-RFD')->assertDontSee('REF-CHG');
        $this->get(route('admin.payments.index', ['from' => now()->subDays(5)->format('Y-m-d')]))->assertDontSee('REF-OLD')->assertSee('REF-CHG');
        $this->get(route('admin.payments.index', ['to' => now()->subDays(30)->format('Y-m-d')]))->assertSee('REF-OLD')->assertDontSee('REF-CHG');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->from(route('admin.payments.index'))
            ->get(route('admin.payments.index', ['type' => 'bogus', 'status' => 'nope', 'from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertSessionHasErrors(['type', 'status', 'to']);
    }

    public function test_totals_count_only_succeeded_money_and_follow_the_filters(): void
    {
        $this->payment(['amount' => 1000]);
        $this->payment(['amount' => 500]);
        $this->payment(['amount' => 200, 'type' => 'refund']);
        $this->payment(['amount' => 9999, 'status' => 'failed']);
        $this->payment(['amount' => 8888, 'status' => 'pending']);

        $response = $this->get(route('admin.payments.index'));
        $totals = $response->viewData('totals');

        $this->assertSame(1500.0, $totals['collected']);
        $this->assertSame(200.0, $totals['refunded']);
        $this->assertSame(1300.0, $totals['net']);
        $this->assertSame(1, $totals['failed']);
        $this->assertSame(1, $totals['pending']);

        $filtered = $this->get(route('admin.payments.index', ['type' => 'refund']))->viewData('totals');
        $this->assertSame(0.0, $filtered['collected']);
        $this->assertSame(200.0, $filtered['refunded']);
    }

    public function test_list_is_paginated_and_keeps_filters(): void
    {
        foreach (range(1, 22) as $i) {
            $this->payment(['status' => 'succeeded']);
        }

        $this->get(route('admin.payments.index', ['status' => 'succeeded']))
            ->assertOk()->assertSee('22 payments')->assertSee('page=2', false)->assertSee('status=succeeded', false);
    }

    public function test_empty_list_message(): void
    {
        $this->get(route('admin.payments.index'))->assertOk()->assertSee('No payments match these filters.');
    }

    public function test_no_card_data_columns_are_rendered(): void
    {
        $this->payment(['card_brand' => 'visa', 'card_last4' => '4242']);

        $this->get(route('admin.payments.index'))->assertOk()->assertDontSee('4242');
    }
}
