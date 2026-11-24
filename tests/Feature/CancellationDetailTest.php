<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\PaymentSetting;
use App\Models\Payment;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Cancellation detail (F2, Day 17): the saved refund calculation, the booking and its payments. */
class CancellationDetailTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['booking.credits_enabled' => true]);   // these tests cover the credit option, which is off by default
    }

    /** Booking 5000 + 200 insurance + 300 tax, paid in full, departing in 70 days: refund 4500 on the default rules. */
    private function openRequest(array $booking = []): CancellationRequest
    {
        $b = Booking::factory()->create($booking + [
            'user_id' => User::factory()->create(['name' => 'Olivia Bennett', 'email' => 'olivia@example.test'])->id,
            'status' => 'confirmed', 'currency' => 'USD', 'subtotal' => 5000, 'insurance_amount' => 200, 'total_amount' => 5500, 'insurance_selected' => true,
        ]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5500, 'currency' => 'USD', 'status' => 'succeeded']);
        $b->setRelation('departure', new TourDeparture(['departs_on' => now()->addDays(70)]));

        // the calculator reads the departure through the relation, so give it a real row
        \DB::table('tours')->insertOrIgnore(['id' => $b->tour_id, 'slug' => 'tour-' . $b->tour_id, 'title' => 'Alpine Wellness Retreat', 'created_at' => now(), 'updated_at' => now()]);
        $dep = TourDeparture::create(['tour_id' => $b->tour_id, 'departs_on' => now()->addDays(70)->toDateString(), 'capacity' => 12, 'status' => 'open']);
        $b->update(['tour_departure_id' => $dep->id]);

        return app(CancellationRequestService::class)->open($b->fresh());
    }

    public function test_detail_shows_request_booking_and_breakdown(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();

        $this->get(route('admin.cancellations.show', $r))->assertOk()
            ->assertSee($r->reference)->assertSee('Pending')
            ->assertSee($r->booking->reference)->assertSee('Olivia Bennett')->assertSee('olivia@example.test')->assertSee('Alpine Wellness Retreat')
            ->assertSee('90%')->assertSee('USD 4,500.00')->assertSee('USD 1,000.00')   // refund and kept by LuxyBiz
            ->assertSee('Travel credit instead of a refund')->assertSee('Approve')->assertSee('Reject')
            ->assertSee('60+ days before departure: 90%')->assertSee('Net paid so far');
    }

    public function test_notes_and_warnings_from_the_snapshot_are_shown(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();   // default rules are placeholders -> warning
        $this->get(route('admin.cancellations.show', $r))->assertSee('Check before deciding')->assertSee('still placeholders')
            ->assertSee('Travel insurance')->assertSee('is not refundable');
    }

    public function test_changed_rules_show_a_notice_but_keep_the_saved_amount(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        PaymentSetting::put('cancel.tiers', json_encode([['min_days' => 60, 'percent' => 50], ['min_days' => 0, 'percent' => 0]]));

        $this->get(route('admin.cancellations.show', $r))->assertSee('The amount would be different today')->assertSee('USD 2,500.00')->assertSee('USD 4,500.00');
        $this->assertSame('4500.00', (string) $r->fresh()->calculated_refund);   // nothing was rewritten
    }

    public function test_no_notice_when_nothing_changed(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        $this->get(route('admin.cancellations.show', $r))->assertDontSee('would be different today');
    }

    public function test_finished_requests_are_not_compared(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        PaymentSetting::put('cancel.tiers', json_encode([['min_days' => 0, 'percent' => 10]]));
        $r->update(['status' => 'refunded', 'decided_at' => now(), 'completed_at' => now(), 'decision_note' => 'Approved in full']);

        $this->get(route('admin.cancellations.show', $r))->assertDontSee('would be different today')->assertSee('Approved in full')->assertSee('Refund paid');
    }

    public function test_override_is_shown_with_its_reason(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        $r->update(['final_refund' => 5500, 'override_reason' => 'Tour cancelled by LuxyBiz']);
        $this->get(route('admin.cancellations.show', $r))->assertSee('Overridden by staff')->assertSee('Tour cancelled by LuxyBiz')->assertSee('USD 5,500.00');
    }

    public function test_request_without_a_saved_calculation_still_opens(): void
    {
        $this->signInAsSuperAdmin();
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'cancelled']);
        $r = CancellationRequest::factory()->create(['booking_id' => $b->id, 'status' => 'refunded', 'calculation' => null, 'calculated_refund' => 700, 'final_refund' => 700]);
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertSee('No calculation was saved');
    }

    public function test_missing_departure_is_flagged(): void
    {
        $this->signInAsSuperAdmin();
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'total_amount' => 1000, 'subtotal' => 1000, 'insurance_amount' => 0]);
        $r = app(CancellationRequestService::class)->open($b);
        $this->get(route('admin.cancellations.show', $r))->assertSee('not set')->assertSee('no departure date')->assertSee('unknown (no departure date)');
    }

    public function test_payments_of_the_booking_are_listed(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 100, 'currency' => 'USD', 'status' => 'succeeded']);
        $this->get(route('admin.cancellations.show', $r))->assertSee('USD 5,500.00')->assertSee('-USD 100.00');
    }

    public function test_list_links_to_the_detail(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->openRequest();
        $this->get(route('admin.cancellations.index'))->assertSee(route('admin.cancellations.show', $r), false);
    }

    public function test_unknown_or_non_numeric_id_is_not_found(): void
    {
        $this->signInAsSuperAdmin();
        $this->get('/admin/cancellations/99999')->assertNotFound();
        $this->get('/admin/cancellations/abc')->assertNotFound();
    }

    public function test_permission_is_needed_and_links_follow_permissions(): void
    {
        $r = $this->openRequest();

        $this->actingAs($this->userWith(['admin.payments.view']));
        $this->get(route('admin.cancellations.show', $r))->assertForbidden();

        $this->actingAs($this->userWith(['admin.cancellations.view'], 'viewer@example.test', 'Viewer'));
        $this->get(route('admin.cancellations.show', $r))->assertOk()
            ->assertDontSee(route('admin.bookings.show', $r->booking_id), false);   // no booking permission, no booking link
    }

    public function test_guest_is_sent_to_login(): void
    {
        $r = $this->openRequest();
        $this->get(route('admin.cancellations.show', $r))->assertRedirect();
    }
}
