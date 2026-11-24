<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Mark refunded and travel credit instead (Day 20). */
class CancellationCompletionTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['booking.credits_enabled' => true]);   // these tests cover the credit option, which is off by default
    }

    /** Booking 5000 paid in full by a Stripe charge; request approved for $1000. */
    private function approved(float $refund = 1000, array $booking = []): CancellationRequest
    {
        $b = Booking::factory()->create($booking + [
            'user_id' => User::factory()->create()->id, 'status' => 'cancellation_requested', 'payment_status' => 'paid',
            'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000, 'currency' => 'USD',
        ]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded', 'gateway' => 'stripe']);
        $r = CancellationRequest::factory()->create([
            'booking_id' => $b->id, 'status' => 'approved', 'currency' => 'USD', 'calculated_refund' => $refund, 'final_refund' => $refund,
            'decided_at' => now()->subHour(), 'calculation' => ['warnings' => []],
        ]);

        return $r->fresh();
    }

    private function refunded(CancellationRequest $r, array $o = [])
    {
        return $this->post(route('admin.cancellations.refunded', $r), $o + ['method' => 'bank_transfer', 'reference' => 'TXN-1', 'refunded_on' => now()->toDateString()]);
    }

    public function test_mark_refunded_records_the_refund_and_cancels_the_booking(): void
    {
        $admin = $this->signInAsSuperAdmin();
        $r = $this->approved(1000);

        $this->refunded($r, ['note' => ' Paid <b>today</b> '])->assertRedirect(route('admin.cancellations.show', $r))->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('refunded', $r->status);
        $this->assertSame('refund', $r->resolution);
        $this->assertNotNull($r->completed_at);
        $this->assertSame('Paid today', $r->completion_note);

        $refund = Payment::where('booking_id', $r->booking_id)->where('type', 'refund')->sole();
        $this->assertSame('1000.00', (string) $refund->amount);
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('bank_transfer', $refund->method);
        $this->assertSame('manual', $refund->gateway);
        $this->assertSame('TXN-1', $refund->gateway_reference);
        $this->assertSame($r->id, $refund->cancellation_request_id);
        $this->assertSame($admin->id, $refund->recorded_by);

        $b = $r->booking->fresh();
        $this->assertSame('cancelled', $b->status);
        $this->assertSame('partial', $b->payment_status);   // 4000 of 5000 still held
        $this->assertSame(4000.0, $b->paidAmount());
    }

    public function test_a_full_refund_sets_the_booking_to_refunded(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(5000);
        $this->refunded($r);
        $this->assertSame('refunded', $r->booking->fresh()->payment_status);
    }

    public function test_dashboard_refund_needs_the_provider_refund_id_and_uses_the_charge_gateway(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);

        $this->refunded($r, ['method' => 'gateway_dashboard', 'reference' => ''])->assertSessionHasErrors('completion');
        $this->assertSame('approved', $r->fresh()->status);

        $this->refunded($r, ['method' => 'gateway_dashboard', 'reference' => 're_ABC123'])->assertSessionHasNoErrors();
        $p = Payment::where('type', 'refund')->sole();
        $this->assertSame('stripe', $p->gateway);
        $this->assertSame('re_ABC123', $p->gateway_reference);   // the webhook will match this row instead of adding another
    }

    public function test_a_refund_the_webhook_already_recorded_is_counted_not_doubled(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);
        // refund made in the provider's dashboard after the approval, recorded by the webhook
        $hook = Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'gateway' => 'stripe', 'gateway_reference' => 're_X', 'created_at' => now()]);

        $this->get(route('admin.cancellations.show', $r))->assertSee('already recorded')->assertSee('Nothing more needs to be recorded');
        $this->refunded($r, ['method' => 'other', 'reference' => null])->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('type', 'refund')->count());                       // no second refund row
        $this->assertSame($r->id, $hook->fresh()->cancellation_request_id);                      // the webhook row now belongs to the request
        $this->assertSame('refunded', $r->fresh()->status);
        $this->assertSame('cancelled', $r->booking->fresh()->status);
    }

    public function test_a_partly_recorded_refund_only_adds_the_rest(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 400, 'currency' => 'USD', 'status' => 'succeeded', 'created_at' => now()]);
        $this->refunded($r);
        $this->assertSame(1000.0, (float) Payment::where('type', 'refund')->sum('amount'));
        $this->assertSame(2, Payment::where('type', 'refund')->count());
    }

    public function test_refunds_from_before_the_approval_or_owned_by_another_request_are_not_counted(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 300, 'currency' => 'USD', 'status' => 'succeeded', 'created_at' => now()->subDays(3)]);
        $other = CancellationRequest::factory()->create(['booking_id' => $r->booking_id, 'status' => 'refunded']);
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 200, 'currency' => 'USD', 'status' => 'succeeded', 'cancellation_request_id' => $other->id, 'created_at' => now()]);

        $this->refunded($r);
        $this->assertSame(1000.0, (float) Payment::where('type', 'refund')->where('cancellation_request_id', $r->id)->sum('amount'));
    }

    public function test_zero_refund_just_closes_the_request(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(0);
        $this->refunded($r)->assertSessionHasNoErrors();
        $this->assertSame(0, Payment::where('type', 'refund')->count());
        $this->assertSame('refunded', $r->fresh()->status);
        $this->assertSame('cancelled', $r->booking->fresh()->status);
    }

    public function test_cannot_refund_more_than_is_left(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 4500, 'currency' => 'USD', 'status' => 'succeeded', 'cancellation_request_id' => CancellationRequest::factory()->create(['booking_id' => $r->booking_id])->id]);
        $this->refunded($r)->assertSessionHasErrors('completion');
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_validation(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved();
        $this->refunded($r, ['method' => 'bogus'])->assertSessionHasErrors('method');
        $this->refunded($r, ['method' => ''])->assertSessionHasErrors('method');
        $this->refunded($r, ['refunded_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('refunded_on');
        $this->refunded($r, ['refunded_on' => now()->subDays(5)->toDateString()])->assertSessionHasErrors('refunded_on');   // before the approval
        $this->refunded($r, ['refunded_on' => 'x'])->assertSessionHasErrors('refunded_on');
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertSame(0, Payment::where('type', 'refund')->count());
    }

    /** @dataProvider notApproved */
    public function test_only_approved_requests_can_be_finished(string $status): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved();
        $r->update(['status' => $status]);
        $this->refunded($r)->assertSessionHasErrors('completion');
        $this->post(route('admin.cancellations.credit', $r))->assertSessionHasErrors('completion');
        $this->assertSame(0, Payment::where('type', 'refund')->count());
        $this->assertSame(0, DB::table('travel_credits')->count());
    }

    public static function notApproved(): array
    {
        return [['pending'], ['rejected'], ['refunded'], ['credited']];
    }

    public function test_finishing_twice_does_not_record_two_refunds(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved();
        $this->refunded($r);
        $this->refunded($r)->assertSessionHasErrors('completion');
        $this->assertSame(1, Payment::where('type', 'refund')->count());
    }

    // ---- credit ----------------------------------------------------------------------

    public function test_issue_credit_creates_the_credit_and_cancels_the_booking(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);

        $this->post(route('admin.cancellations.credit', $r), ['note' => 'Customer prefers credit'])->assertRedirect()->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('credited', $r->status);
        $this->assertSame('credit', $r->resolution);
        $this->assertSame('Customer prefers credit', $r->completion_note);

        $c = DB::table('travel_credits')->where('cancellation_request_id', $r->id)->sole();
        $this->assertMatchesRegularExpression('/^TC-[A-Z0-9]{8}$/', $c->code);
        $this->assertSame($r->booking->user_id, (int) $c->customer_id);
        $this->assertEquals(1000.00, (float) $c->amount);            // default rules: 100% of the refund
        $this->assertEquals(1000.00, (float) $c->balance);
        $this->assertSame('USD', $c->currency);
        $this->assertSame('active', $c->status);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateString(), $c->expires_at);

        $b = $r->booking->fresh();
        $this->assertSame('cancelled', $b->status);
        $this->assertSame(0, Payment::where('type', 'refund')->count());   // no money goes back
        $this->assertSame(5000.0, $b->paidAmount());
        $this->get(route('admin.cancellations.show', $r))->assertSee($c->code)->assertSee('Travel credit');
    }

    public function test_credit_follows_the_saved_credit_rules(): void
    {
        $this->signInAsSuperAdmin();
        \App\Models\PaymentSetting::put('cancel.credit_percent', '110');
        \App\Models\PaymentSetting::put('cancel.credit_months', '6');
        $r = $this->approved(1000);
        $this->post(route('admin.cancellations.credit', $r));
        $c = DB::table('travel_credits')->sole();
        $this->assertEquals(1100.00, (float) $c->amount);
        $this->assertSame(now()->addMonthsNoOverflow(6)->toDateString(), $c->expires_at);
    }

    public function test_credit_refused_without_a_customer_account_zero_amount_or_existing_refund(): void
    {
        $this->signInAsSuperAdmin();

        $guest = $this->approved(1000, ['user_id' => null]);
        $this->post(route('admin.cancellations.credit', $guest))->assertSessionHasErrors('completion');

        $zero = $this->approved(0);
        $this->post(route('admin.cancellations.credit', $zero))->assertSessionHasErrors('completion');

        $paidOut = $this->approved(1000);
        Payment::factory()->refund()->create(['booking_id' => $paidOut->booking_id, 'amount' => 1000, 'currency' => 'USD', 'status' => 'succeeded', 'created_at' => now()]);
        $this->post(route('admin.cancellations.credit', $paidOut))->assertSessionHasErrors('completion');

        $this->assertSame(0, DB::table('travel_credits')->count());
        foreach ([$guest, $zero, $paidOut] as $x) {
            $this->assertSame('approved', $x->fresh()->status);
        }
    }

    public function test_credit_cannot_be_issued_twice_for_one_request(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved();
        $this->post(route('admin.cancellations.credit', $r));
        $r->update(['status' => 'approved']);   // even if the status were wrong
        $this->post(route('admin.cancellations.credit', $r))->assertSessionHasErrors('completion');
        $this->assertSame(1, DB::table('travel_credits')->count());
    }

    // ---- page and permission ---------------------------------------------------------

    public function test_page_shows_the_finish_card_for_approved_requests(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved(1000);
        $this->get(route('admin.cancellations.show', $r))->assertSee('Finish this cancellation')->assertSee('Mark refunded')->assertSee('Travel credit instead')->assertSee('USD 1,000.00');

        $guest = $this->approved(1000, ['user_id' => null]);
        $this->get(route('admin.cancellations.show', $guest))->assertSee('no customer account');
    }

    public function test_finished_requests_show_how_they_ended_and_have_no_finish_card(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->approved();
        $this->refunded($r, ['note' => 'Sent by bank']);
        $this->get(route('admin.cancellations.show', $r))->assertDontSee('Finish this cancellation')->assertSee('Refund paid')->assertSee('Sent by bank');
    }

    public function test_permission_is_needed(): void
    {
        $r = $this->approved();

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.decide', 'admin.cancellations.override']));
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertDontSee('Finish this cancellation');
        $this->refunded($r)->assertForbidden();
        $this->post(route('admin.cancellations.credit', $r))->assertForbidden();
        $this->assertSame('approved', $r->fresh()->status);

        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.complete'], 'pay@example.test', 'Finance'));
        $this->get(route('admin.cancellations.show', $r))->assertSee('Finish this cancellation');
        $this->refunded($r)->assertSessionHasNoErrors();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $r = $this->approved();
        $this->refunded($r)->assertRedirect();
    }

    public function test_whole_flow_open_approve_override_then_refund(): void
    {
        $this->signInAsSuperAdmin();
        \Illuminate\Support\Facades\Notification::fake();
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'payment_status' => 'paid', 'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded']);

        $r = app(CancellationRequestService::class)->open($b);
        $this->put(route('admin.cancellations.override', $r), ['amount' => '2500', 'reason' => 'Tour cancelled by LuxyBiz'])->assertSessionHasNoErrors();
        $this->post(route('admin.cancellations.approve', $r), ['acknowledged' => '1'])->assertSessionHasNoErrors();
        $this->refunded($r->fresh())->assertSessionHasNoErrors();

        $this->assertSame('refunded', $r->fresh()->status);
        $this->assertSame(2500.0, (float) Payment::where('type', 'refund')->sum('amount'));
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(2500.0, $b->fresh()->paidAmount());
    }
}
