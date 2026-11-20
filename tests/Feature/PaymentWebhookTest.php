<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\StripeWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Webhook receiver (Day 12): signature check, success, failure, refunds, retries. Nothing here calls Stripe. */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const WHSEC = 'whsec_test_SECRET1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        PaymentSetting::putField('stripe', 'enabled', '1');
        PaymentSetting::putField('stripe', 'mode', 'sandbox');
        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_VERYSECRET1234567890');
        PaymentSetting::putField('stripe', 'webhook_secret', self::WHSEC);
    }

    // ------------------------------------------------------------ helpers

    private function booking(float $total = 1000): Booking
    {
        $user = User::firstOrCreate(['email' => 'olivia@example.com'], ['name' => 'Olivia Bennett', 'password' => bcrypt('x')]);

        return Booking::factory()->create(['user_id' => $user->id, 'status' => 'confirmed', 'total_amount' => $total, 'currency' => 'USD', 'payment_status' => 'unpaid']);
    }

    private function pendingPayment(Booking $b, float $amount = 250, ?string $session = 'cs_test_1'): Payment
    {
        return Payment::factory()->pending()->create([
            'booking_id' => $b->id, 'channel' => 'csr_link', 'gateway' => 'stripe', 'method' => 'card',
            'amount' => $amount, 'currency' => 'USD', 'gateway_reference' => null, 'gateway_session_id' => $session,
        ]);
    }

    private function sessionEvent(string $type, Payment $p, array $over = [], array $eventOver = []): array
    {
        return array_replace_recursive([
            'id' => 'evt_' . uniqid(),
            'type' => $type,
            'created' => time(),
            'livemode' => false,
            'data' => ['object' => array_merge([
                'id' => $p->gateway_session_id ?? 'cs_test_1',
                'object' => 'checkout.session',
                'payment_intent' => 'pi_test_1',
                'payment_status' => 'paid',
                'amount_total' => (int) round($p->amount * 100),
                'currency' => 'usd',
                'metadata' => ['booking_id' => (string) $p->booking_id, 'payment_id' => (string) $p->id],
                'customer_details' => ['name' => 'Olivia Bennett', 'email' => 'olivia@example.com', 'address' => ['line1' => '1 Main St']],
            ], $over)],
        ], $eventOver);
    }

    private function refundEvent(string $type, Booking $b, array $over = [], array $eventOver = []): array
    {
        return array_replace_recursive([
            'id' => 'evt_' . uniqid(),
            'type' => $type,
            'created' => time(),
            'livemode' => false,
            'data' => ['object' => array_merge([
                'id' => 're_test_1', 'object' => 'refund', 'payment_intent' => 'pi_test_1', 'amount' => 10000, 'currency' => 'usd', 'status' => 'succeeded',
            ], $over)],
        ], $eventOver);
    }

    private function sign(string $raw, ?int $t = null, string $secret = self::WHSEC): string
    {
        $t ??= time();

        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $raw, $secret);
    }

    private function send(array $event, ?string $header = null, ?string $raw = null)
    {
        $raw ??= json_encode($event);
        $header ??= $this->sign($raw);

        return $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $header], $raw);
    }

    // ------------------------------------------------------------ signature

    public function test_signature_helper_accepts_a_good_signature_and_rejects_bad_ones(): void
    {
        $raw = '{"a":1}';
        $this->assertTrue(StripeWebhookHandler::signatureIsValid($raw, $this->sign($raw), self::WHSEC));
        $this->assertFalse(StripeWebhookHandler::signatureIsValid($raw, $this->sign($raw, null, 'whsec_other'), self::WHSEC));
        $this->assertFalse(StripeWebhookHandler::signatureIsValid('{"a":2}', $this->sign($raw), self::WHSEC), 'changed body');
        $this->assertFalse(StripeWebhookHandler::signatureIsValid($raw, null, self::WHSEC));
        $this->assertFalse(StripeWebhookHandler::signatureIsValid($raw, 'garbage', self::WHSEC));
        $this->assertFalse(StripeWebhookHandler::signatureIsValid($raw, $this->sign($raw), ''));
    }

    public function test_old_signatures_are_rejected_as_replays(): void
    {
        $raw = '{"a":1}';
        $this->assertFalse(StripeWebhookHandler::signatureIsValid($raw, $this->sign($raw, time() - 301), self::WHSEC));
        $this->assertTrue(StripeWebhookHandler::signatureIsValid($raw, $this->sign($raw, time() - 200), self::WHSEC));
    }

    public function test_any_of_several_v1_signatures_may_match(): void
    {
        $raw = '{"a":1}';
        $t = time();
        $good = hash_hmac('sha256', $t . '.' . $raw, self::WHSEC);
        $this->assertTrue(StripeWebhookHandler::signatureIsValid($raw, "t=$t,v1=deadbeef,v1=$good", self::WHSEC));
    }

    public function test_request_without_valid_signature_gets_400_and_changes_nothing(): void
    {
        $p = $this->pendingPayment($this->booking());
        $event = $this->sessionEvent('checkout.session.completed', $p);

        $this->send($event, 't=' . time() . ',v1=bad')->assertStatus(400);
        $this->call('POST', '/webhooks/payments/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertStatus(400);

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame(0, PaymentEvent::count(), 'unsigned requests are not even logged');
    }

    public function test_bad_signature_is_logged_without_the_body_or_secret(): void
    {
        Log::spy();
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p), 't=1,v1=bad');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m) => ! str_contains($m, 'olivia') && ! str_contains($m, self::WHSEC));
    }

    public function test_answers_503_when_no_webhook_secret_is_saved_or_the_provider_is_off(): void
    {
        PaymentSetting::putField('stripe', 'webhook_secret', null);
        $this->send(['id' => 'evt_1', 'type' => 'x'])->assertStatus(503);

        PaymentSetting::putField('stripe', 'webhook_secret', self::WHSEC);
        PaymentSetting::putField('stripe', 'enabled', '0');
        $this->send(['id' => 'evt_1', 'type' => 'x'])->assertStatus(503);
    }

    public function test_route_needs_no_login_and_no_csrf_token(): void
    {
        $route = app('router')->getRoutes()->getByName('webhooks.payments');
        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $excluded = $route->excludedMiddleware();
        $this->assertTrue(in_array(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, $excluded, true)
            || in_array(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, $excluded, true));
        $this->assertNotContains('auth', $route->gatherMiddleware());
    }

    public function test_invalid_json_with_a_valid_signature_gets_400(): void
    {
        $raw = 'not json';
        $this->send([], $this->sign($raw), $raw)->assertStatus(400);
    }

    // ------------------------------------------------------------ success

    public function test_completed_session_marks_payment_succeeded_and_updates_the_booking(): void
    {
        $b = $this->booking(1000);
        $p = $this->pendingPayment($b, 250);

        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk()->assertJson(['received' => true]);

        $p->refresh();
        $this->assertSame('succeeded', $p->status);
        $this->assertSame('pi_test_1', $p->gateway_reference);
        $this->assertNotNull($p->paid_at);
        $this->assertSame('partial', $b->fresh()->payment_status);
    }

    public function test_paying_the_full_balance_marks_the_booking_paid(): void
    {
        $b = $this->booking(250);
        $p = $this->pendingPayment($b, 250);
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();
        $this->assertSame('paid', $b->fresh()->payment_status);
    }

    public function test_event_is_logged_as_processed_and_linked_to_the_payment(): void
    {
        $p = $this->pendingPayment($this->booking());
        $event = $this->sessionEvent('checkout.session.completed', $p);
        $this->send($event)->assertOk();

        $log = PaymentEvent::firstOrFail();
        $this->assertSame('processed', $log->outcome);
        $this->assertSame($event['id'], $log->external_id);
        $this->assertSame($p->id, $log->payment_id);
        $this->assertSame('stripe', $log->gateway);
    }

    public function test_logged_payload_keeps_no_customer_name_email_or_address(): void
    {
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();

        $json = json_encode(PaymentEvent::firstOrFail()->payload);
        $this->assertStringNotContainsString('olivia@example.com', $json);
        $this->assertStringNotContainsString('Olivia', $json);
        $this->assertStringNotContainsString('1 Main St', $json);
        $this->assertStringContainsString('pi_test_1', $json);
    }

    public function test_the_same_event_sent_twice_changes_the_payment_only_once(): void
    {
        $b = $this->booking(1000);
        $p = $this->pendingPayment($b, 250);
        $event = $this->sessionEvent('checkout.session.completed', $p);

        $this->send($event)->assertOk();
        $paidAt = $p->fresh()->paid_at;
        $this->travel(2)->minutes();
        $this->send($event)->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertEquals($paidAt, $p->fresh()->paid_at);
        $this->assertSame(1, Payment::where('status', 'succeeded')->count());
    }

    public function test_a_second_event_for_an_already_succeeded_payment_is_ignored(): void
    {
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();
        $this->send($this->sessionEvent('checkout.session.async_payment_succeeded', $p))->assertOk();

        $this->assertSame(2, PaymentEvent::count());
        $this->assertSame('ignored', PaymentEvent::latest('id')->first()->outcome);
        $this->assertSame('succeeded', $p->fresh()->status);
    }

    public function test_unpaid_completed_session_waits_for_the_bank(): void
    {
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p, ['payment_status' => 'unpaid']))->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('processed', PaymentEvent::firstOrFail()->outcome);

        $this->send($this->sessionEvent('checkout.session.async_payment_succeeded', $p))->assertOk();
        $this->assertSame('succeeded', $p->fresh()->status);
    }

    public function test_payment_is_found_by_metadata_when_the_session_id_was_never_saved(): void
    {
        // Our call to Stripe timed out: the row was marked failed with no session id, but Stripe had created the session.
        $b = $this->booking();
        $p = $this->pendingPayment($b, 250, null);
        $p->update(['status' => 'failed', 'failure_code' => 'session_error']);

        $this->send($this->sessionEvent('checkout.session.completed', $p, ['id' => 'cs_test_late']))->assertOk();

        $p->refresh();
        $this->assertSame('succeeded', $p->status);
        $this->assertNull($p->failure_code);
        $this->assertSame('partial', $b->fresh()->payment_status);
    }

    // ------------------------------------------------------------ checks that refuse to book money

    public function test_amount_mismatch_is_not_booked_and_is_flagged(): void
    {
        $p = $this->pendingPayment($this->booking(), 250);
        $this->send($this->sessionEvent('checkout.session.completed', $p, ['amount_total' => 100]))->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $log = PaymentEvent::firstOrFail();
        $this->assertSame('failed', $log->outcome);
        $this->assertStringContainsString('does not match', $log->note);
    }

    public function test_currency_mismatch_is_not_booked(): void
    {
        $p = $this->pendingPayment($this->booking(), 250);
        $this->send($this->sessionEvent('checkout.session.completed', $p, ['currency' => 'inr']))->assertOk();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_missing_amount_is_not_booked(): void
    {
        $p = $this->pendingPayment($this->booking(), 250);
        $event = $this->sessionEvent('checkout.session.completed', $p);
        unset($event['data']['object']['amount_total']);
        $this->send($event)->assertOk();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_session_for_another_booking_is_not_booked(): void
    {
        $p = $this->pendingPayment($this->booking(), 250);
        $other = $this->booking();
        $this->send($this->sessionEvent('checkout.session.completed', $p, ['metadata' => ['booking_id' => (string) $other->id, 'payment_id' => (string) $p->id]]))->assertOk();
        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('failed', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_different_session_id_than_the_one_we_saved_is_not_booked(): void
    {
        $p = $this->pendingPayment($this->booking(), 250, 'cs_test_real');
        // Found through metadata.payment_id, but it is another session.
        $this->send($this->sessionEvent('checkout.session.completed', $p, ['id' => 'cs_test_other']))->assertOk();
        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('failed', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_unknown_session_is_ignored(): void
    {
        $p = $this->pendingPayment($this->booking());
        $event = $this->sessionEvent('checkout.session.completed', $p, ['id' => 'cs_unknown', 'metadata' => []]);
        $this->send($event)->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_live_event_in_sandbox_mode_is_ignored(): void
    {
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p, [], ['livemode' => true]))->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertStringContainsString('sandbox', PaymentEvent::firstOrFail()->note);
    }

    public function test_sandbox_event_in_live_mode_is_ignored(): void
    {
        PaymentSetting::putField('stripe', 'mode', 'live');
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_offline_payments_are_never_touched(): void
    {
        $b = $this->booking();
        $offline = Payment::factory()->pending()->create(['booking_id' => $b->id, 'gateway' => null, 'gateway_session_id' => 'cs_test_1', 'amount' => 250, 'currency' => 'USD']);
        $this->send($this->sessionEvent('checkout.session.completed', $offline))->assertOk();
        $this->assertSame('pending', $offline->fresh()->status);
    }

    public function test_unused_event_types_are_logged_and_ignored(): void
    {
        $this->send(['id' => 'evt_x1', 'type' => 'customer.created', 'livemode' => false, 'data' => ['object' => ['id' => 'cus_1']]])->assertOk();
        $log = PaymentEvent::firstOrFail();
        $this->assertSame('ignored', $log->outcome);
        $this->assertSame('customer.created', $log->event);
    }

    // ------------------------------------------------------------ failure and expiry

    public function test_expired_session_marks_a_pending_payment_failed(): void
    {
        $b = $this->booking();
        $p = $this->pendingPayment($b);
        $this->send($this->sessionEvent('checkout.session.expired', $p, ['payment_status' => 'unpaid']))->assertOk();

        $p->refresh();
        $this->assertSame('failed', $p->status);
        $this->assertSame('session_expired', $p->failure_code);
        $this->assertSame('unpaid', $b->fresh()->payment_status);
    }

    public function test_async_payment_failure_marks_a_pending_payment_failed(): void
    {
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.async_payment_failed', $p))->assertOk();
        $this->assertSame('failed', $p->fresh()->status);
        $this->assertSame('async_payment_failed', $p->fresh()->failure_code);
    }

    public function test_a_failure_message_never_undoes_a_payment_that_succeeded(): void
    {
        $b = $this->booking(1000);
        $p = $this->pendingPayment($b, 250);
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();
        $this->send($this->sessionEvent('checkout.session.expired', $p))->assertOk();

        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('partial', $b->fresh()->payment_status);
        $this->assertSame('ignored', PaymentEvent::latest('id')->first()->outcome);
    }

    // ------------------------------------------------------------ refunds

    private function succeededCharge(Booking $b, float $amount = 250): Payment
    {
        $p = $this->pendingPayment($b, $amount);
        $p->update(['status' => 'succeeded', 'paid_at' => now(), 'gateway_reference' => 'pi_test_1']);
        $b->syncPaymentStatus();

        return $p;
    }

    public function test_refund_made_at_stripe_creates_a_refund_row_and_updates_the_booking(): void
    {
        $b = $this->booking(100);
        $charge = $this->succeededCharge($b, 100);
        $this->assertSame('paid', $b->fresh()->payment_status);

        $this->send($this->refundEvent('refund.created', $b, ['amount' => 10000]))->assertOk();

        $refund = Payment::where('type', 'refund')->firstOrFail();
        $this->assertSame('succeeded', $refund->status);
        $this->assertEquals(100.00, (float) $refund->amount);
        $this->assertSame('re_test_1', $refund->gateway_reference);
        $this->assertSame($b->id, $refund->booking_id);
        $this->assertSame('refunded', $b->fresh()->payment_status);
        $this->assertSame($refund->id, PaymentEvent::firstOrFail()->payment_id);
    }

    public function test_partial_refund_leaves_the_booking_partial(): void
    {
        $b = $this->booking(100);
        $this->succeededCharge($b, 100);
        $this->send($this->refundEvent('refund.created', $b, ['amount' => 4000]))->assertOk();
        $this->assertSame('partial', $b->fresh()->payment_status);
    }

    public function test_pending_refund_is_recorded_pending_then_completed_by_the_update_event(): void
    {
        $b = $this->booking(100);
        $this->succeededCharge($b, 100);

        $this->send($this->refundEvent('refund.created', $b, ['status' => 'pending']))->assertOk();
        $this->assertSame('pending', Payment::where('type', 'refund')->firstOrFail()->status);
        $this->assertSame('paid', $b->fresh()->payment_status, 'a pending refund has not moved money yet');

        $this->send($this->refundEvent('refund.updated', $b, ['status' => 'succeeded']))->assertOk();
        $this->assertSame(1, Payment::where('type', 'refund')->count());
        $this->assertSame('succeeded', Payment::where('type', 'refund')->firstOrFail()->status);
        $this->assertSame('refunded', $b->fresh()->payment_status);
    }

    public function test_failed_refund_is_marked_failed(): void
    {
        $b = $this->booking(100);
        $this->succeededCharge($b, 100);
        $this->send($this->refundEvent('refund.created', $b, ['status' => 'pending']))->assertOk();
        $this->send($this->refundEvent('refund.failed', $b, ['status' => 'failed', 'failure_reason' => 'lost_or_stolen_card']))->assertOk();

        $refund = Payment::where('type', 'refund')->firstOrFail();
        $this->assertSame('failed', $refund->status);
        $this->assertSame('lost_or_stolen_card', $refund->failure_code);
        $this->assertSame('paid', $b->fresh()->payment_status);
    }

    public function test_refund_update_finishes_a_pending_refund_row_created_by_staff(): void
    {
        // A refund started from this system keeps its Stripe refund id in gateway_reference (built on the refund days).
        $b = $this->booking(100);
        $this->succeededCharge($b, 100);
        $row = Payment::factory()->refund()->pending()->create(['booking_id' => $b->id, 'gateway' => 'stripe', 'gateway_reference' => 're_test_1', 'amount' => 100, 'currency' => 'USD']);

        $this->send($this->refundEvent('refund.updated', $b))->assertOk();

        $this->assertSame(1, Payment::where('type', 'refund')->count());
        $this->assertSame('succeeded', $row->fresh()->status);
    }

    public function test_the_same_refund_sent_in_two_different_events_is_booked_once(): void
    {
        $b = $this->booking(100);
        $this->succeededCharge($b, 100);
        $this->send($this->refundEvent('refund.created', $b))->assertOk();
        $this->send($this->refundEvent('refund.updated', $b))->assertOk();

        $this->assertSame(1, Payment::where('type', 'refund')->count());
        $this->assertSame('ignored', PaymentEvent::latest('id')->first()->outcome);
    }

    public function test_refund_for_an_unknown_payment_is_ignored(): void
    {
        $this->send($this->refundEvent('refund.created', $this->booking(), ['payment_intent' => 'pi_unknown']))->assertOk();
        $this->assertSame(0, Payment::where('type', 'refund')->count());
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    // ------------------------------------------------------------ retries after a fault

    public function test_a_server_fault_answers_500_and_the_retry_is_processed(): void
    {
        $b = $this->booking(1000);
        $p = $this->pendingPayment($b, 250);
        $event = $this->sessionEvent('checkout.session.completed', $p);

        // Break the booking update once.
        \App\Models\Booking::updating(function () {
            static $once = true;
            if ($once) {
                $once = false;
                throw new \RuntimeException('db down');
            }
        });

        $this->send($event)->assertStatus(500);
        $this->assertSame('pending', $p->fresh()->status, 'the whole change was rolled back');

        $this->send($event)->assertOk();
        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('partial', $b->fresh()->payment_status);
        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('processed', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_a_server_fault_never_writes_the_event_body_to_the_log(): void
    {
        Log::spy();
        $p = $this->pendingPayment($this->booking());
        \App\Models\Payment::updating(fn () => throw new \RuntimeException('db down'));
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertStatus(500);

        Log::shouldHaveReceived('error')->once()->withArgs(fn ($m) => ! str_contains($m, 'olivia') && ! str_contains($m, 'pi_test_1'));
    }

    // ------------------------------------------------------------ the events screen and settings page

    public function test_processed_events_show_up_on_the_webhook_events_screen(): void
    {
        $this->signInAsSuperAdmin();
        $p = $this->pendingPayment($this->booking());
        $this->send($this->sessionEvent('checkout.session.completed', $p))->assertOk();

        $this->get(route('admin.payments.events'))->assertOk()->assertSee('checkout.session.completed')->assertSee('Payment succeeded.');
    }

    public function test_settings_page_lists_the_events_to_tick_in_stripe(): void
    {
        $this->signInAsSuperAdmin();

        $page = $this->get(route('admin.settings.gateway'))->assertOk();
        foreach (array_merge(StripeWebhookHandler::EVENTS, \App\Services\Payments\RazorpayWebhookHandler::EVENTS) as $evt) {
            $page->assertSee($evt);
        }
        $page->assertSee('/webhooks/payments/stripe')->assertSee('/webhooks/payments/razorpay');
    }
}
