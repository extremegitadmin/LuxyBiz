<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\CancellationDecisionNotification;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Approve and reject with a customer email (Day 19). */
class CancellationDecisionTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    /** Booking 5000 paid in full. No departure date -> calculated 0 and a warning; tests set the amount they need. */
    private function req(array $o = [], array $booking = []): CancellationRequest
    {
        $b = Booking::factory()->create($booking + [
            'user_id' => User::factory()->create(['name' => 'Olivia Bennett', 'email' => 'olivia+' . uniqid() . '@example.test'])->id,
            'status' => 'confirmed', 'payment_status' => 'paid', 'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000,
        ]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded']);
        $r = app(CancellationRequestService::class)->open($b);
        $r->update($o + ['calculated_refund' => 1000, 'calculation' => ['warnings' => []]]);

        return $r->fresh();
    }

    private function approve(CancellationRequest $r, array $o = [])
    {
        return $this->post(route('admin.cancellations.approve', $r), $o);
    }

    private function reject(CancellationRequest $r, array $o = [])
    {
        return $this->post(route('admin.cancellations.reject', $r), $o + ['reason' => 'The tour starts too soon for a refund under our terms.']);
    }

    public function test_approve_fixes_the_amount_records_who_and_emails_the_customer(): void
    {
        Notification::fake();
        $admin = $this->signInAsSuperAdmin();
        $r = $this->req();

        $this->approve($r, ['note' => '  Approved <b>in full</b>  '])->assertRedirect(route('admin.cancellations.show', $r))->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertSame('1000.00', (string) $r->final_refund);
        $this->assertSame($admin->id, $r->decided_by);
        $this->assertNotNull($r->decided_at);
        $this->assertSame('Approved in full', $r->decision_note);
        $this->assertNotNull($r->customer_notified_at);
        $this->assertNull($r->notification_error);
        $this->assertSame('cancellation_requested', $r->booking->fresh()->status);   // money not moved yet

        Notification::assertSentOnDemand(CancellationDecisionNotification::class, function ($n, $channels, AnonymousNotifiable $to) use ($r) {
            return array_key_exists($r->booking->user->email, $to->routes['mail']);
        });
    }

    public function test_approve_keeps_an_override_amount(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req(['final_refund' => 2500, 'override_reason' => 'Tour cancelled by LuxyBiz']);
        $this->approve($r);
        $this->assertSame('2500.00', (string) $r->fresh()->final_refund);
    }

    public function test_warnings_must_be_acknowledged(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req(['calculation' => ['warnings' => ['No departure date.']]]);

        $this->approve($r)->assertSessionHasErrors('decision');
        $this->assertSame('pending', $r->fresh()->status);
        Notification::assertNothingSent();

        $this->approve($r, ['acknowledged' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $r->fresh()->status);
    }

    public function test_cannot_approve_more_than_is_now_paid(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req(['calculated_refund' => 4500]);
        Payment::factory()->refund()->create(['booking_id' => $r->booking_id, 'amount' => 3000, 'currency' => 'USD', 'status' => 'succeeded']);   // only 2000 left

        $this->approve($r)->assertSessionHasErrors('decision');
        $this->assertSame('pending', $r->fresh()->status);
    }

    public function test_zero_refund_can_be_approved(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req(['calculated_refund' => 0]);
        $this->approve($r)->assertSessionHasNoErrors();
        $this->assertSame('0.00', (string) $r->fresh()->final_refund);
    }

    public function test_only_pending_requests_can_be_decided(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        foreach (['approved', 'rejected', 'refunded', 'credited'] as $status) {
            $r = $this->req(['status' => $status]);
            $this->approve($r)->assertSessionHasErrors('decision');
            $this->reject($r)->assertSessionHasErrors('reject_reason');
            $this->assertSame($status, $r->fresh()->status);
        }
        Notification::assertNothingSent();
    }

    public function test_reject_needs_a_reason_then_restores_the_booking_and_emails(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req();

        foreach ([null, '', '   ', 'no', '<i> </i>'] as $bad) {
            $this->reject($r, ['reason' => $bad])->assertSessionHasErrors();
        }
        $this->assertSame('pending', $r->fresh()->status);

        $this->reject($r)->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('rejected', $r->status);
        $this->assertSame('The tour starts too soon for a refund under our terms.', $r->decision_note);
        $this->assertNull($r->final_refund);
        $this->assertSame('confirmed', $r->booking->fresh()->status);   // paid booking goes back to confirmed
        Notification::assertSentOnDemandTimes(CancellationDecisionNotification::class, 1);
    }

    public function test_rejecting_an_unpaid_booking_sets_it_back_to_pending(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req([], ['payment_status' => 'unpaid']);
        $this->reject($r);
        $this->assertSame('pending', $r->booking->fresh()->status);
    }

    public function test_a_new_request_is_possible_after_a_rejection(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->reject($r);
        $again = app(CancellationRequestService::class)->open($r->booking->fresh());
        $this->assertSame('pending', $again->status);
    }

    public function test_no_email_address_is_reported_not_hidden(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req([], ['user_id' => null]);

        $this->approve($r)->assertSessionHas('warning');
        $r->refresh();
        $this->assertSame('approved', $r->status);                 // the decision stands
        $this->assertNull($r->customer_notified_at);
        $this->assertStringContainsString('no valid email', $r->notification_error);
        Notification::assertNothingSent();
        $this->get(route('admin.cancellations.show', $r))->assertSee('no valid email')->assertSee('Try sending the email again');
    }

    public function test_falls_back_to_the_lead_traveler_email(): void
    {
        Notification::fake();
        $this->signInAsSuperAdmin();
        $r = $this->req([], ['user_id' => null]);
        BookingTraveler::factory()->create(['booking_id' => $r->booking_id, 'is_lead' => true, 'full_name' => 'Pat Traveler', 'email' => 'pat@example.test']);

        $this->approve($r);
        Notification::assertSentOnDemand(CancellationDecisionNotification::class, fn ($n, $c, $to) => array_key_exists('pat@example.test', $to->routes['mail']));
        $this->assertNotNull($r->fresh()->customer_notified_at);
    }

    public function test_a_mail_failure_does_not_undo_the_decision_and_can_be_retried(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);

        $this->approve($r)->assertSessionHas('warning');
        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertNull($r->customer_notified_at);
        $this->assertStringContainsString('could not be sent', $r->notification_error);

        // fixed: mail goes to the log driver again, and the resend works
        config(['mail.default' => 'array']);
        $this->post(route('admin.cancellations.resend', $r))->assertSessionHas('success');
        $this->assertNotNull($r->fresh()->customer_notified_at);
        $this->assertNull($r->fresh()->notification_error);
    }

    public function test_resend_is_refused_for_pending(): void
    {
        $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->post(route('admin.cancellations.resend', $r))->assertSessionHasErrors('decision');
    }

    public function test_the_email_says_what_happened(): void
    {
        $this->signInAsSuperAdmin();
        config(['mail.default' => 'array']);
        $r = $this->req();
        $this->approve($r);
        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $body = $sent->last()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('approved', $body);
        $this->assertStringContainsString('USD 1,000.00', $body);
        $this->assertStringContainsString('Olivia Bennett', $body);

        $r2 = $this->req();
        $this->reject($r2, ['reason' => 'Departure is in two days, no refund is possible.']);
        $body2 = $sent->last()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('not able to approve', $body2);
        $this->assertStringContainsString('Departure is in two days', $body2);
    }

    public function test_page_shows_the_forms_only_to_people_who_may_decide(): void
    {
        $r = $this->req();

        $this->actingAs($this->userWith(['admin.cancellations.view']));
        $this->get(route('admin.cancellations.show', $r))->assertOk()->assertDontSee('Reject')->assertSee('Waiting for a decision');
        $this->approve($r)->assertForbidden();
        $this->reject($r)->assertForbidden();
        $this->post(route('admin.cancellations.resend', $r))->assertForbidden();
        $this->assertSame('pending', $r->fresh()->status);

        Notification::fake();
        $this->actingAs($this->userWith(['admin.cancellations.view', 'admin.cancellations.decide'], 'lead@example.test', 'Lead'));
        $this->get(route('admin.cancellations.show', $r))->assertSee('Approve')->assertSee('Reject');
        $this->approve($r)->assertSessionHasNoErrors();
    }

    public function test_decided_page_shows_who_when_and_the_email_status(): void
    {
        Notification::fake();
        $admin = $this->signInAsSuperAdmin();
        $r = $this->req();
        $this->approve($r, ['note' => 'All good']);
        $this->get(route('admin.cancellations.show', $r))->assertSee('by ' . $admin->name)->assertSee('All good')->assertSee('Customer emailed on')->assertDontSee('Waiting for a decision');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $r = $this->req();
        $this->approve($r)->assertRedirect();
    }
}
