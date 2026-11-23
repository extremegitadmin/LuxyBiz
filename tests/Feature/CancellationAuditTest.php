<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Every cancellation action leaves one clear audit entry (Day 21). Entries are written by SafeAudit into activity_logs. */
class CancellationAuditTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        // The team's activity_logs table is Anakha's. The test makes a copy of it when the project does not have a migration for it.
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function ($t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action');
                $t->text('description')->nullable();
                $t->text('properties')->nullable();
                $t->timestamps();
            });
        }
        Notification::fake();
    }

    private function entries(?string $action = null): \Illuminate\Support\Collection
    {
        return DB::table('activity_logs')->when($action, fn ($q) => $q->where('action', $action))->orderBy('id')->get()
            ->map(fn ($e) => (object) ['user_id' => $e->user_id, 'action' => $e->action, 'description' => $e->description, 'p' => json_decode($e->properties, true)]);
    }

    private function booking(array $o = []): Booking
    {
        $b = Booking::factory()->create($o + [
            'user_id' => User::factory()->create(['name' => 'Olivia Bennett', 'email' => 'olivia.bennett@example.test'])->id,
            'status' => 'confirmed', 'payment_status' => 'paid', 'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000, 'currency' => 'USD',
        ]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded', 'gateway' => 'stripe']);

        return $b;
    }

    private function pending(float $refund = 1000, array $booking = []): CancellationRequest
    {
        $r = app(CancellationRequestService::class)->open($this->booking($booking));
        $r->update(['calculated_refund' => $refund, 'calculation' => ['warnings' => []]]);
        DB::table('activity_logs')->delete();   // start each test with an empty log

        return $r->fresh();
    }

    public function test_opening_a_request_is_logged_with_the_booking(): void
    {
        $b = $this->booking();
        $r = app(CancellationRequestService::class)->open($b, $this->superAdmin(), 'Family reason', 'staff');

        $e = $this->entries('cancellation.requested');
        $this->assertCount(1, $e);
        $this->assertSame($r->id, $e[0]->p['subject_id']);
        $this->assertSame('CancellationRequest', $e[0]->p['subject_type']);
        $this->assertSame($r->reference, $e[0]->p['request_reference']);
        $this->assertSame($b->id, $e[0]->p['booking_id']);
        $this->assertSame($b->reference, $e[0]->p['booking_reference']);
        $this->assertSame('staff', $e[0]->p['source']);
    }

    public function test_approving_is_logged_with_who_amount_and_note(): void
    {
        $r = $this->pending(1000);
        $staff = $this->userWith(['admin.cancellations.view', 'admin.cancellations.decide'], 'decider@example.test', 'Decider');
        $this->actingAs($staff)->post(route('admin.cancellations.approve', $r), ['note' => 'Within the policy'])->assertSessionHasNoErrors();

        $e = $this->entries('cancellation.approved');
        $this->assertCount(1, $e);
        $this->assertSame($staff->id, $e[0]->user_id);
        $this->assertSame(1000.0, (float) $e[0]->p['refund']);
        $this->assertSame('Within the policy', $e[0]->p['note']);
        $this->assertFalse($e[0]->p['overridden']);
        $this->assertSame($r->booking->reference, $e[0]->p['booking_reference']);
        $this->assertSame('USD', $e[0]->p['currency']);
    }

    public function test_approving_with_warnings_records_that_they_were_acknowledged(): void
    {
        $r = $this->pending(1000);
        $r->update(['calculation' => ['warnings' => ['No departure date']]]);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r), ['acknowledged' => 1])->assertSessionHasNoErrors();

        $this->assertTrue($this->entries('cancellation.approved')[0]->p['warnings_acknowledged']);
    }

    public function test_rejecting_is_logged_with_the_reason(): void
    {
        $r = $this->pending();
        $staff = $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.reject', $r), ['reason' => 'Departure is in two days, so it cannot be cancelled.'])->assertSessionHasNoErrors();

        $e = $this->entries('cancellation.rejected');
        $this->assertCount(1, $e);
        $this->assertSame($staff->id, $e[0]->user_id);
        $this->assertSame('Departure is in two days, so it cannot be cancelled.', $e[0]->p['reason']);
    }

    public function test_override_and_reset_are_logged_with_before_after_and_reason(): void
    {
        $r = $this->pending(1000);
        $this->signInAsSuperAdmin();
        $this->put(route('admin.cancellations.override', $r), ['amount' => '1500', 'reason' => 'Goodwill after a complaint'])->assertSessionHasNoErrors();
        $this->delete(route('admin.cancellations.override.reset', $r))->assertSessionHasNoErrors();

        $o = $this->entries('cancellation.refund_overridden');
        $this->assertCount(1, $o);
        $this->assertSame(1000.0, (float) $o[0]->p['from']);
        $this->assertSame(1500.0, (float) $o[0]->p['to']);
        $this->assertSame(1000.0, (float) $o[0]->p['calculated']);
        $this->assertSame('Goodwill after a complaint', $o[0]->p['reason']);

        $x = $this->entries('cancellation.override_removed');
        $this->assertCount(1, $x);
        $this->assertSame(1500.0, (float) $x[0]->p['from']);
        $this->assertSame(1000.0, (float) $x[0]->p['to']);
    }

    public function test_marking_refunded_is_logged(): void
    {
        $r = $this->pending(1000);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasNoErrors();
        $this->post(route('admin.cancellations.refunded', $r), ['method' => 'gateway_dashboard', 'reference' => 'rfnd_TEST123', 'refunded_on' => now()->toDateString()])->assertSessionHasNoErrors();

        $e = $this->entries('cancellation.refunded');
        $this->assertCount(1, $e);
        $this->assertSame('gateway_dashboard', $e[0]->p['method']);
        $this->assertSame('rfnd_TEST123', $e[0]->p['provider_refund_id']);
        $this->assertSame(1000.0, (float) $e[0]->p['amount']);
        $this->assertSame(now()->toDateString(), $e[0]->p['refunded_on']);
        $this->assertSame($r->booking->reference, $e[0]->p['booking_reference']);
    }

    public function test_issuing_credit_is_logged_with_the_code(): void
    {
        $r = $this->pending(1000);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasNoErrors();
        $this->post(route('admin.cancellations.credit', $r))->assertSessionHasNoErrors();

        $e = $this->entries('cancellation.credited');
        $this->assertCount(1, $e);
        $this->assertNotEmpty($e[0]->p['code']);
        $this->assertGreaterThan(0, (float) $e[0]->p['credit']);
    }

    public function test_a_failed_decision_email_is_logged_and_the_decision_stands(): void
    {
        // The customer has no valid email address, so the email cannot be sent.
        $r = $this->pending(1000, ['user_id' => User::factory()->create(['email' => 'not-an-email'])->id]);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasNoErrors();

        $this->assertSame('approved', $r->fresh()->status);
        $e = $this->entries('cancellation.email_failed');
        $this->assertCount(1, $e);
        $this->assertSame('approved', $e[0]->p['decision']);
        $this->assertNotEmpty($e[0]->p['error']);
    }

    public function test_resending_the_email_is_logged_with_who_and_the_result(): void
    {
        $r = $this->pending();
        $staff = $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.reject', $r), ['reason' => 'Not possible this close to departure.']);
        $this->post(route('admin.cancellations.resend', $r))->assertSessionHasNoErrors();

        $e = $this->entries('cancellation.email_resent');
        $this->assertCount(1, $e);
        $this->assertTrue($e[0]->p['ok']);
        $this->assertSame($staff->id, $e[0]->p['resent_by']);
        $this->assertCount(0, $this->entries('cancellation.email_failed'));
    }

    public function test_saving_the_rules_is_logged_with_before_and_after(): void
    {
        $this->signInAsSuperAdmin();
        $this->put(route('admin.settings.cancellation.update'), [
            'tiers' => [['min_days' => 60, 'percent' => 80], ['min_days' => 0, 'percent' => 0]],
            'base' => 'total', 'insurance_refundable' => '1', 'fixed_fee' => '10', 'credit_percent' => 100, 'credit_months' => 12,
            'refund_days_min' => 5, 'refund_days_max' => 10, 'confirmed' => '1',
        ])->assertSessionHasNoErrors();

        $e = $this->entries('settings.cancellation_rules_updated');
        $this->assertCount(1, $e);
        $this->assertArrayHasKey('before', $e[0]->p);
        $this->assertArrayHasKey('after', $e[0]->p);
        $this->assertNotSame($e[0]->p['before'], $e[0]->p['after']);
    }

    public function test_refused_actions_leave_no_entry(): void
    {
        $r = $this->pending();
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasNoErrors();
        $this->post(route('admin.cancellations.refunded', $r), ['method' => 'cash', 'refunded_on' => now()->toDateString()])->assertSessionHasNoErrors();
        DB::table('activity_logs')->delete();

        // The request is finished: deciding again, changing the amount and refunding again are all refused.
        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasErrors('decision');
        $this->post(route('admin.cancellations.reject', $r), ['reason' => 'Changed my mind about this one.'])->assertSessionHasErrors('reject_reason');
        $this->put(route('admin.cancellations.override', $r), ['amount' => '10', 'reason' => 'Too late to change'])->assertSessionHasErrors('amount');
        $this->delete(route('admin.cancellations.override.reset', $r))->assertSessionHasErrors('amount');
        $this->post(route('admin.cancellations.refunded', $r), ['method' => 'cash', 'refunded_on' => now()->toDateString()])->assertSessionHasErrors('completion');
        $this->post(route('admin.cancellations.credit', $r))->assertSessionHasErrors('completion');

        $this->assertCount(0, $this->entries());
    }

    public function test_entries_hold_no_customer_email_and_no_secrets(): void
    {
        $r = $this->pending(1000);
        $this->signInAsSuperAdmin();
        $this->post(route('admin.cancellations.approve', $r), ['note' => 'ok']);
        $this->post(route('admin.cancellations.refunded', $r), ['method' => 'gateway_dashboard', 'reference' => 'rfnd_TEST123', 'refunded_on' => now()->toDateString()]);

        $all = DB::table('activity_logs')->get()->map(fn ($e) => $e->description . ' ' . $e->properties)->implode(' ');
        $this->assertNotEmpty($all);
        $this->assertStringNotContainsString('olivia.bennett@example.test', $all);
        foreach (['password', 'secret', 'whsec_', 'rzp_', 'sk_test', 'card'] as $bad) {
            $this->assertStringNotContainsString($bad, strtolower($all));
        }
    }

    public function test_every_entry_is_tied_to_a_request_and_a_booking(): void
    {
        $r = $this->pending(1000);
        $this->signInAsSuperAdmin();
        $this->put(route('admin.cancellations.override', $r), ['amount' => '900', 'reason' => 'Adjusted by manager']);
        $this->post(route('admin.cancellations.approve', $r));
        $this->post(route('admin.cancellations.resend', $r));
        $this->post(route('admin.cancellations.refunded', $r), ['method' => 'cash', 'refunded_on' => now()->toDateString()]);

        $this->assertGreaterThanOrEqual(4, $this->entries()->count());
        foreach ($this->entries() as $e) {
            $this->assertSame($r->id, $e->p['subject_id'], $e->action);
            $this->assertSame($r->reference, $e->p['request_reference'], $e->action);
            $this->assertSame($r->booking_id, $e->p['booking_id'], $e->action);
            $this->assertNotNull($e->user_id, $e->action);
        }
    }

    public function test_a_broken_audit_log_never_stops_the_work(): void
    {
        $r = $this->pending(1000);
        Schema::drop('activity_logs');
        $this->signInAsSuperAdmin();

        $this->post(route('admin.cancellations.approve', $r))->assertSessionHasNoErrors();
        $this->assertSame('approved', $r->fresh()->status);
    }
}
