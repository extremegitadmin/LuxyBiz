<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Refund override with a mandatory reason (Day 18). */
class CancellationOverrideTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    /** Booking 5000, paid in full. No departure date -> calculated refund 0, so any amount up to 5000 is an override. */
    private function req(string $status = 'pending', float $calculated = 0): CancellationRequest
    {
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded']);
        $r = app(CancellationRequestService::class)->open($b);
        $r->update(['status' => $status, 'calculated_refund' => $calculated]);

        return $r->fresh();
    }

    private function override(CancellationRequest $r, array $o = [])
    {
        return $this->put(route('admin.cancellations.override', $r), $o + ['amount' => '2500.50', 'reason' => 'Tour cancelled by LuxyBiz']);
    }

    public function test_override_saves_amount_reason_and_user(): void
    {
        $admin = $this->signInAsSuperAdmin();
        $r = $this->req();

        $this->override($r)->assertRedirect(route('admin.cancellations.show', $r))->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('2500.50', (string) $r->final_refund);
        $this->assertSame('Tour cancelled by LuxyBiz', $r->override_reason);
        $this->assertSame($admin->id, $r->overridden_by);
        $this->assertSame('0.00', (string) $r->calculated_refund);   // the calculated amount is kept
        $this->assertTrue($r->isOverridden());
        $this->assertSame(2500.50, $r->refundAmount());
    }

    public function test_page_shows_the_override_form_and_the_result(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->get(route('admin.cancellations.show', $r))->assertSee('Change the refund amount')->assertSee('USD 5,000.00');   // max = paid

        $this->override($r);
        $this->get(route('admin.cancellations.show', $r))->assertSee('Overridden by staff')->assertSee('Tour cancelled by LuxyBiz')
            ->assertSee('Reset to calculated')->assertSee('USD 2,500.50');
    }

    public function test_reason_is_required_and_cleaned(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();

        foreach ([null, '', '   ', 'too short', '<b>  </b>   x  '] as $bad) {
            $this->override($r, ['reason' => $bad])->assertSessionHasErrors('reason');
        }
        $this->assertNull($r->fresh()->final_refund);

        $this->override($r, ['reason' => "  <script>x</script>Visa   refused\n\nby embassy  "])->assertSessionHasNoErrors();
        $this->assertSame('xVisa refused by embassy', $r->fresh()->override_reason);
    }

    public function test_amount_rules(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();

        foreach (['', 'abc', '-1', '5000.01', '100.123', '99999999999'] as $bad) {
            $this->override($r, ['amount' => $bad])->assertSessionHasErrors('amount');
        }
        $this->assertNull($r->fresh()->final_refund);

        $this->override($r, ['amount' => '5000'])->assertSessionHasNoErrors();      // exactly what was paid is fine
        $this->assertSame('5000.00', (string) $r->fresh()->final_refund);
    }

    public function test_zero_is_allowed_when_the_rules_would_refund_something(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('pending', 1000);
        $this->override($r, ['amount' => '0', 'reason' => 'Customer no-show, no refund'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', (string) $r->fresh()->final_refund);
        $this->assertSame(0.0, $r->fresh()->refundAmount());
    }

    public function test_same_as_calculated_is_refused(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('pending', 1000);
        $this->override($r, ['amount' => '1000.00'])->assertSessionHasErrors('amount');
        $this->assertNull($r->fresh()->final_refund);
    }

    public function test_cannot_refund_more_than_paid_even_after_earlier_refunds(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 3000, 'currency' => 'USD', 'status' => 'succeeded']);
        $this->override($r, ['amount' => '2000.01'])->assertSessionHasErrors('amount');
        $this->override($r, ['amount' => '2000.00'])->assertSessionHasNoErrors();
    }

    /** @dataProvider closed */
    public function test_closed_requests_cannot_be_overridden(string $status): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req($status);
        $this->override($r)->assertSessionHasErrors('amount');
        $this->assertNull($r->fresh()->final_refund);
        $this->get(route('admin.cancellations.show', $r))->assertDontSee('Change the refund amount');
    }

    public static function closed(): array
    {
        return [['rejected'], ['refunded'], ['credited']];
    }

    public function test_approved_requests_can_still_be_overridden(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('approved');
        $this->override($r)->assertSessionHasNoErrors();
        $this->assertSame('2500.50', (string) $r->fresh()->final_refund);
    }

    public function test_override_can_be_changed_again(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->override($r);
        $this->override($r, ['amount' => '100', 'reason' => 'Corrected after checking']);
        $this->assertSame('100.00', (string) $r->fresh()->final_refund);
        $this->assertSame('Corrected after checking', $r->fresh()->override_reason);
    }

    public function test_reset_removes_the_override(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req('pending', 1000);
        $this->override($r);
        $this->delete(route('admin.cancellations.override.reset', $r))->assertRedirect()->assertSessionHas('success');

        $r->refresh();
        $this->assertNull($r->final_refund);
        $this->assertNull($r->override_reason);
        $this->assertNull($r->overridden_by);
        $this->assertSame(1000.0, $r->refundAmount());
    }

    public function test_reset_without_override_or_on_closed_request_is_refused(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->delete(route('admin.cancellations.override.reset', $r))->assertSessionHasErrors('amount');

        $done = $this->req('refunded');
        $done->update(['final_refund' => 300, 'override_reason' => 'Approved lower amount']);
        $this->delete(route('admin.cancellations.override.reset', $done))->assertSessionHasErrors('amount');
        $this->assertSame('300.00', (string) $done->fresh()->final_refund);
    }

    public function test_permission_is_needed_to_override(): void
    {
        $r = $this->req();

        $this->actingAs($this->userWith(['admin.cancellations.view']));
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertDontSee('Change the refund amount');
        $this->override($r)->assertForbidden();
        $this->delete(route('admin.cancellations.override.reset', $r))->assertForbidden();
        $this->assertNull($r->fresh()->final_refund);

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.override'], 'senior@example.test', 'Senior'));
        $this->get(route('admin.cancellations.show', $r))->assertSee('Change the refund amount');
        $this->override($r)->assertSessionHasNoErrors();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $r = $this->req();
        $this->override($r)->assertRedirect();
    }

    public function test_override_does_not_touch_the_booking_or_payments(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $before = [Payment::count(), $r->booking->fresh()->status, $r->booking->fresh()->payment_status];
        $this->override($r);
        $this->assertSame($before, [Payment::count(), $r->booking->fresh()->status, $r->booking->fresh()->payment_status]);
    }
}
