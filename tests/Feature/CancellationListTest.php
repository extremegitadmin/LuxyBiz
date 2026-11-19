<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\TourDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Cancellation requests list (F1, Day 16). */
class CancellationListTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function req(string $status = 'pending', array $booking = [], array $o = []): CancellationRequest
    {
        $b = Booking::factory()->create($booking + ['user_id' => User::factory()->create(['name' => 'Cust ' . uniqid()])->id, 'status' => 'cancellation_requested']);

        return CancellationRequest::factory()->create($o + ['booking_id' => $b->id, 'status' => $status]);
    }

    public function test_list_shows_requests_with_their_details(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('pending', ['currency' => 'USD'], ['calculated_refund' => 1234.5, 'requested_on' => now()->subDays(4)->toDateString(), 'created_at' => now()->subDays(4)]);

        $this->get(route('admin.cancellations.index'))->assertOk()
            ->assertSee($r->reference)->assertSee($r->booking->reference)->assertSee($r->booking->user->name)
            ->assertSee('USD 1,234.50')->assertSee('waiting 4 days')->assertSee('Pending');
    }

    public function test_pending_requests_come_first_oldest_first(): void
    {
        $this->signInAsSuperAdmin();
        $done = $this->req('refunded', [], ['requested_on' => now()->subDays(30)->toDateString()]);
        $newer = $this->req('pending', [], ['requested_on' => now()->subDay()->toDateString()]);
        $older = $this->req('pending', [], ['requested_on' => now()->subDays(5)->toDateString()]);

        $this->get(route('admin.cancellations.index'))->assertSeeInOrder([$older->reference, $newer->reference, $done->reference]);
    }

    public function test_tabs_show_counts_and_filter_by_status(): void
    {
        $this->signInAsSuperAdmin();
        $p = $this->req('pending');
        $this->req('pending');
        $a = $this->req('approved');
        $x = $this->req('rejected');

        $html = $this->get(route('admin.cancellations.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/All\s*<span[^>]*>4<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Pending\s*<span[^>]*>2<\/span>/', $html);

        $this->get(route('admin.cancellations.index', ['status' => 'approved']))->assertSee($a->reference)->assertDontSee($p->reference)->assertDontSee($x->reference);
    }

    public function test_search_by_request_booking_customer_and_traveler(): void
    {
        $this->signInAsSuperAdmin();
        $u = User::factory()->create(['name' => 'Zelda Quinlan', 'email' => 'zelda@example.test']);
        $mine = $this->req('pending', ['user_id' => $u->id]);
        $other = $this->req('pending');

        foreach (['Zelda', 'zelda@example', $mine->reference, $mine->booking->reference] as $term) {
            $this->get(route('admin.cancellations.index', ['q' => $term]))->assertSee($mine->reference)->assertDontSee($other->reference);
        }
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->get(route('admin.cancellations.index', ['q' => '%']))->assertDontSee($r->reference);
        $this->get(route('admin.cancellations.index', ['q' => '_']))->assertDontSee($r->reference);
    }

    public function test_date_filter(): void
    {
        $this->signInAsSuperAdmin();
        $old = $this->req('pending', [], ['requested_on' => '2026-01-10']);
        $new = $this->req('pending', [], ['requested_on' => '2026-06-10']);
        $this->get(route('admin.cancellations.index', ['from' => '2026-06-01', 'to' => '2026-06-30']))->assertSee($new->reference)->assertDontSee($old->reference);
    }

    public function test_bad_filters_are_refused(): void
    {
        $this->signInAsSuperAdmin();
        $this->get(route('admin.cancellations.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
        $this->get(route('admin.cancellations.index', ['from' => '2026-06-10', 'to' => '2026-06-01']))->assertSessionHasErrors('to');
    }

    public function test_departure_date_and_needs_review_are_shown(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('pending', [], ['calculation' => ['needs_review' => true]]);
        $this->get(route('admin.cancellations.index'))->assertSee('not set')->assertSee('needs review');
    }

    public function test_empty_list_message(): void
    {
        $this->signInAsSuperAdmin();
        $this->get(route('admin.cancellations.index'))->assertOk()->assertSee('No cancellation requests yet.');
        $this->get(route('admin.cancellations.index', ['q' => 'nothing']))->assertSee('Clear the filters');
    }

    public function test_pages_of_15(): void
    {
        $this->signInAsSuperAdmin();
        CancellationRequest::factory()->count(17)->create(['booking_id' => Booking::factory()->create()->id]);   // same booking is fine for a list test
        $this->get(route('admin.cancellations.index'))->assertSee('17 requests')->assertSee('page=2');
    }

    public function test_override_is_marked(): void
    {
        $this->signInAsSuperAdmin();
        $this->req('approved', [], ['calculated_refund' => 100, 'final_refund' => 500]);
        $this->get(route('admin.cancellations.index'))->assertSee('USD 500.00')->assertSee('overridden');
    }

    public function test_permission_is_needed(): void
    {
        $this->actingAs($this->userWith(['admin.payments.view']));
        $this->get(route('admin.cancellations.index'))->assertForbidden();

        $this->actingAs($this->userWith(['admin.cancellations.view'], 'viewer@example.test', 'Viewer'));
        $this->get(route('admin.cancellations.index'))->assertOk();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.cancellations.index'))->assertRedirect();
    }

    public function test_pending_count_for_the_sidebar(): void
    {
        $this->req('pending');
        $this->req('approved');
        $this->assertSame(1, CancellationRequest::pendingCount());
    }
}
