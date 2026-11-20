<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\Gateway;
use App\Services\Payments\PaymentSessionService;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Razorpay (INR) next to Stripe (USD): payment links, routing by currency, and the Razorpay webhook (Day 12).
 * Razorpay is faked, no real request is made.
 */
class RazorpayTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const KEY_ID = 'rzp_test_AbC123xyz';
    private const KEY_SECRET = 'RZPSECRETVALUE9988';
    private const HOOK = 'rzpHookSecret77';
    private const LINK = 'https://rzp.io/i/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    // ------------------------------------------------------------ helpers

    private function configureRazorpay(string $mode = 'sandbox', string $keyId = self::KEY_ID): void
    {
        PaymentSetting::putField('razorpay', 'enabled', '1');
        PaymentSetting::putField('razorpay', 'mode', $mode);
        PaymentSetting::putField('razorpay', 'public_key', $keyId);
        PaymentSetting::putField('razorpay', 'secret_key', self::KEY_SECRET);
        PaymentSetting::putField('razorpay', 'webhook_secret', self::HOOK);
    }

    private function configureStripe(): void
    {
        PaymentSetting::putField('stripe', 'enabled', '1');
        PaymentSetting::putField('stripe', 'mode', 'sandbox');
        PaymentSetting::putField('stripe', 'public_key', 'pk_test_PUBLIC');
        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_VERYSECRET1234567890');
    }

    private function fakeOk(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plink_Abc123', 'short_url' => self::LINK, 'status' => 'created'], 200)]);
    }

    private function booking(array $attrs = []): Booking
    {
        $user = User::firstOrCreate(['email' => 'olivia@example.com'], ['name' => 'Olivia Bennett', 'password' => bcrypt('x')]);

        return Booking::factory()->create($attrs + ['user_id' => $user->id, 'status' => 'confirmed', 'total_amount' => 10000, 'currency' => 'INR', 'payment_status' => 'unpaid']);
    }

    private function request(Booking $b, $amount)
    {
        return $this->post(route('admin.bookings.payment-link', $b), ['amount' => $amount]);
    }

    private function pendingPayment(Booking $b, float $amount = 2500, ?string $session = 'plink_Abc123'): Payment
    {
        return Payment::factory()->pending()->create([
            'booking_id' => $b->id, 'channel' => 'csr_link', 'gateway' => 'razorpay', 'method' => 'card',
            'amount' => $amount, 'currency' => 'INR', 'gateway_reference' => null, 'gateway_session_id' => $session,
        ]);
    }

    // ------------------------------------------------------------ routing by currency

    public function test_an_inr_booking_goes_to_razorpay_and_a_usd_booking_goes_to_stripe(): void
    {
        $this->configureRazorpay();
        $this->configureStripe();

        $this->assertSame('razorpay', Gateway::providerFor('INR'));
        $this->assertSame('razorpay', Gateway::providerFor('inr'));
        $this->assertSame('stripe', Gateway::providerFor('USD'));
        $this->assertSame('stripe', Gateway::providerFor('EUR'));
        $this->assertInstanceOf(RazorpayGateway::class, Gateway::forCurrency('INR'));
    }

    public function test_inr_payment_only_talks_to_razorpay_never_to_stripe(): void
    {
        $this->configureRazorpay();
        $this->configureStripe();
        $this->fakeOk();

        $this->request($this->booking(), '2500.00')->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://api.razorpay.com/'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'stripe.com'));
        $this->assertSame('razorpay', Payment::firstOrFail()->gateway);
    }

    public function test_usd_payment_only_talks_to_stripe_never_to_razorpay(): void
    {
        $this->configureRazorpay();
        $this->configureStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'], 200)]);

        $this->request($this->booking(['currency' => 'USD', 'total_amount' => 500]), '100.00')->assertSessionHas('success');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'razorpay.com'));
        $this->assertSame('stripe', Payment::firstOrFail()->gateway);
    }

    public function test_no_provider_for_the_currency_gives_a_clear_message_and_no_payment_row(): void
    {
        $this->configureStripe();   // only Stripe is on, the booking is in INR
        Http::fake();

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('for INR', session('error'));
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_settings_can_send_inr_to_stripe_and_usd_to_razorpay(): void
    {
        $this->configureRazorpay();
        $this->configureStripe();
        PaymentSetting::put('route.inr', 'stripe');
        PaymentSetting::put('route.other', 'razorpay');

        $this->assertSame('stripe', Gateway::providerFor('INR'));
        $this->assertSame('razorpay', Gateway::providerFor('USD'));
    }

    public function test_a_provider_that_is_switched_off_is_never_used_even_with_keys_saved(): void
    {
        $this->configureRazorpay();
        PaymentSetting::putField('razorpay', 'enabled', '0');

        $this->assertNull(Gateway::providerFor('INR'));
        $this->assertFalse(Gateway::isOnlineFor('INR'));
    }

    // ------------------------------------------------------------ payment link

    public function test_creates_a_pending_payment_with_the_razorpay_fields(): void
    {
        $this->configureRazorpay();
        $this->fakeOk();
        $b = $this->booking();

        $this->request($b, '2500.50')
            ->assertRedirect(route('admin.bookings.show', $b))
            ->assertSessionHas('payment_link', self::LINK)
            ->assertSessionHas('payment_link_hours', 48);

        $p = Payment::firstOrFail();
        $this->assertSame('pending', $p->status);
        $this->assertSame('plink_Abc123', $p->gateway_session_id);
        $this->assertSame('INR', $p->currency);

        Http::assertSent(function (Request $r) use ($p, $b) {
            $d = $r->data();

            return $r->method() === 'POST'
                && $r->url() === 'https://api.razorpay.com/v1/payment_links/'
                && $r->hasHeader('Authorization', 'Basic ' . base64_encode(self::KEY_ID . ':' . self::KEY_SECRET))
                && $d['amount'] === 250050
                && $d['currency'] === 'INR'
                && $d['reference_id'] === 'pay-' . $p->id
                && $d['accept_partial'] === false
                && $d['callback_method'] === 'get'
                && $d['callback_url'] === route('payment.return')
                && $d['notify'] === ['sms' => false, 'email' => false]
                && $d['reminder_enable'] === false
                && $d['notes']['payment_id'] === (string) $p->id
                && $d['notes']['booking_id'] === (string) $b->id
                && $d['notes']['booking_reference'] === $b->reference
                && $d['customer'] === ['name' => 'Olivia Bennett', 'email' => 'olivia@example.com']
                && abs($d['expire_by'] - now()->addHours(48)->timestamp) < 120;
        });
    }

    public function test_the_booking_page_says_the_link_is_valid_for_48_hours(): void
    {
        $this->configureRazorpay();
        $this->fakeOk();
        $b = $this->booking();

        $this->request($b, '100');
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee('valid for 48 hours')->assertSee('Razorpay');
    }

    public function test_amounts_go_to_razorpay_in_the_smallest_unit_for_usd_too(): void
    {
        $this->configureRazorpay();
        PaymentSetting::put('route.other', 'razorpay');
        $this->fakeOk();

        $this->request($this->booking(['currency' => 'USD', 'total_amount' => 500]), '19.99');

        Http::assertSent(fn (Request $r) => $r->data()['amount'] === 1999 && $r->data()['currency'] === 'USD');
    }

    public function test_a_key_id_of_the_wrong_mode_or_kind_is_refused_before_anything_is_sent(): void
    {
        Http::fake();
        $b = $this->booking();

        $this->configureRazorpay('sandbox', 'rzp_live_RealMoney1');
        $this->request($b, '100')->assertSessionHas('error');
        $this->assertStringContainsString('live key but the mode is sandbox', session('error'));

        $this->configureRazorpay('sandbox', 'pk_test_notrazorpay');
        $this->request($b, '100')->assertSessionHas('error');
        $this->assertStringContainsString('does not look like a Razorpay key', session('error'));

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_rejected_keys_mark_the_payment_failed_and_never_show_a_key(): void
    {
        $this->configureRazorpay();
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'Authentication failed']], 401)]);

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('Razorpay rejected the keys', session('error'));
        $this->assertStringNotContainsString(self::KEY_SECRET, session('error'));
        $this->assertSame('failed', Payment::firstOrFail()->status);
        $this->assertSame('session_error', Payment::firstOrFail()->failure_code);
    }

    public function test_a_razorpay_error_message_is_shown_but_keys_in_it_are_hidden(): void
    {
        $this->configureRazorpay();
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'Bad input ' . self::KEY_SECRET . ' amount too low', 'field' => 'amount']], 400)]);

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('BAD_REQUEST_ERROR', session('error'));
        $this->assertStringContainsString('amount too low', session('error'));
        $this->assertStringNotContainsString(self::KEY_SECRET, session('error'));
    }

    public function test_a_network_failure_is_reported_and_the_payment_is_marked_failed(): void
    {
        $this->configureRazorpay();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('Could not reach Razorpay', session('error'));
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_a_link_that_is_not_https_is_rejected(): void
    {
        $this->configureRazorpay();
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plink_x', 'short_url' => 'http://evil.example/pay'], 200)]);

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertNull(Payment::firstOrFail()->gateway_session_id);
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_the_keys_never_appear_in_the_session_page_or_logs(): void
    {
        $this->configureRazorpay();
        Log::spy();
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['code' => 'SERVER_ERROR', 'description' => 'boom']], 500)]);
        $b = $this->booking();

        $this->request($b, '100');

        $page = $this->get(route('admin.bookings.show', $b))->getContent();
        $this->assertStringNotContainsString(self::KEY_SECRET, $page);
        $this->assertStringNotContainsString(self::KEY_SECRET, json_encode(session()->all()));
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx = []) => ! str_contains(json_encode($ctx), self::KEY_SECRET));
    }

    public function test_open_link_warning_uses_each_providers_own_lifetime(): void
    {
        $this->configureRazorpay();
        $this->configureStripe();
        $b = $this->booking(['currency' => 'INR']);

        $rzp = $this->pendingPayment($b);
        $rzp->forceFill(['created_at' => now()->subHours(30)])->save();   // 30h old: Razorpay links last 48h
        $stripe = Payment::factory()->pending()->create(['booking_id' => $b->id, 'gateway' => 'stripe', 'gateway_session_id' => 'cs_old', 'amount' => 10]);
        $stripe->forceFill(['created_at' => now()->subHours(30)])->save(); // 30h old: Stripe links last 23h

        $open = PaymentSessionService::openLinks($b);
        $this->assertSame([$rzp->id], $open->pluck('id')->all());
    }

    // ------------------------------------------------------------ test connection

    public function test_test_connection_calls_razorpay_and_reports_success(): void
    {
        $this->configureRazorpay();
        Http::fake(['api.razorpay.com/*' => Http::response(['entity' => 'collection', 'count' => 0, 'items' => []], 200)]);

        $this->post(route('admin.settings.gateway.test', 'razorpay'))
            ->assertSessionHas('success', 'Connected to Razorpay (sandbox mode). The keys work.');
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_starts_with($r->url(), 'https://api.razorpay.com/v1/payment_links'));
        $this->assertStringNotContainsString(self::KEY_SECRET, json_encode(session()->all()));
    }

    public function test_test_connection_reports_rejected_keys_under_the_razorpay_name(): void
    {
        $this->configureRazorpay();
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'Authentication failed']], 401)]);

        $this->post(route('admin.settings.gateway.test', 'razorpay'))->assertSessionHasErrors('test_razorpay');
        $this->assertStringContainsString('Razorpay: Razorpay rejected the keys', session('errors')->first('test_razorpay'));
    }

    // ------------------------------------------------------------ webhook

    private function hook(array $event, ?string $eventId = null, ?string $signature = null, ?string $raw = null)
    {
        $raw ??= json_encode($event);
        $signature ??= hash_hmac('sha256', $raw, self::HOOK);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature];
        if ($eventId !== false) {
            $server['HTTP_X_RAZORPAY_EVENT_ID'] = $eventId ?? ('evtid_' . uniqid());
        }

        return $this->call('POST', '/webhooks/payments/razorpay', [], [], [], $server, $raw);
    }

    private function paidEvent(Payment $p, array $link = [], array $pay = []): array
    {
        return [
            'entity' => 'event', 'account_id' => 'acc_1', 'event' => 'payment_link.paid', 'created_at' => time(),
            'payload' => [
                'payment_link' => ['entity' => array_merge([
                    'id' => $p->gateway_session_id ?? 'plink_Abc123', 'status' => 'paid', 'reference_id' => 'pay-' . $p->id,
                    'amount' => (int) round($p->amount * 100), 'amount_paid' => (int) round($p->amount * 100), 'currency' => 'INR',
                    'notes' => ['booking_id' => (string) $p->booking_id, 'payment_id' => (string) $p->id],
                    'customer' => ['name' => 'Olivia Bennett', 'email' => 'olivia@example.com', 'contact' => '+919800000000'],
                ], $link)],
                'payment' => ['entity' => array_merge([
                    'id' => 'pay_Xyz789', 'amount' => (int) round($p->amount * 100), 'currency' => 'INR', 'status' => 'captured', 'method' => 'upi',
                    'email' => 'olivia@example.com', 'contact' => '+919800000000',
                ], $pay)],
            ],
        ];
    }

    private function linkEvent(string $name, Payment $p, array $link = []): array
    {
        $e = $this->paidEvent($p, $link);
        $e['event'] = $name;
        unset($e['payload']['payment']);
        $e['payload']['payment_link']['entity']['status'] = $name === 'payment_link.expired' ? 'expired' : 'cancelled';

        return $e;
    }

    private function refundEvent(string $name, array $refund = []): array
    {
        return [
            'entity' => 'event', 'event' => $name, 'created_at' => time(),
            'payload' => ['refund' => ['entity' => array_merge([
                'id' => 'rfnd_Aaa111', 'payment_id' => 'pay_Xyz789', 'amount' => 10000, 'currency' => 'INR', 'status' => 'processed',
            ], $refund)]],
        ];
    }

    private function succeededCharge(Booking $b, float $amount = 100): Payment
    {
        $p = $this->pendingPayment($b, $amount);
        $p->update(['status' => 'succeeded', 'paid_at' => now(), 'gateway_reference' => 'pay_Xyz789']);
        $b->syncPaymentStatus();

        return $p;
    }

    public function test_a_bad_or_missing_signature_gets_400_and_changes_nothing(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());
        $event = $this->paidEvent($p);

        $this->hook($event, null, 'deadbeef')->assertStatus(400);
        $this->hook($event, null, hash_hmac('sha256', json_encode($event), 'another-secret'))->assertStatus(400);
        $this->call('POST', '/webhooks/payments/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertStatus(400);
        // body changed after signing
        $this->hook($event, null, hash_hmac('sha256', '{}', self::HOOK))->assertStatus(400);

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_the_stripe_secret_cannot_be_used_at_the_razorpay_address(): void
    {
        $this->configureRazorpay();
        PaymentSetting::putField('stripe', 'webhook_secret', 'whsec_stripe_only');
        $p = $this->pendingPayment($this->booking());
        $raw = json_encode($this->paidEvent($p));

        $this->hook([], null, hash_hmac('sha256', $raw, 'whsec_stripe_only'), $raw)->assertStatus(400);
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_503_when_razorpay_is_off_or_has_no_webhook_secret_and_404_for_unknown_providers(): void
    {
        $this->configureRazorpay();
        PaymentSetting::putField('razorpay', 'enabled', '0');
        $this->hook(['event' => 'x'])->assertStatus(503);

        PaymentSetting::putField('razorpay', 'enabled', '1');
        PaymentSetting::putField('razorpay', 'webhook_secret', null);
        $this->hook(['event' => 'x'])->assertStatus(503);

        $this->call('POST', '/webhooks/payments/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertNotFound();
    }

    public function test_paid_link_marks_the_payment_succeeded_and_updates_the_booking(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 10000]);
        $p = $this->pendingPayment($b, 2500);

        $this->hook($this->paidEvent($p), 'evt_paid_1')->assertOk()->assertJson(['received' => true]);

        $p->refresh();
        $this->assertSame('succeeded', $p->status);
        $this->assertSame('pay_Xyz789', $p->gateway_reference);
        $this->assertNotNull($p->paid_at);
        $this->assertSame('partial', $b->fresh()->payment_status);

        $log = PaymentEvent::firstOrFail();
        $this->assertSame('processed', $log->outcome);
        $this->assertSame('evt_paid_1', $log->external_id);
        $this->assertSame('razorpay', $log->gateway);
        $this->assertSame('payment_link.paid', $log->event);
        $this->assertSame($p->id, $log->payment_id);
    }

    public function test_paying_the_full_balance_marks_the_booking_paid(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 2500]);
        $this->hook($this->paidEvent($this->pendingPayment($b, 2500)))->assertOk();
        $this->assertSame('paid', $b->fresh()->payment_status);
    }

    public function test_the_log_keeps_no_customer_name_email_phone_or_card_details(): void
    {
        $this->configureRazorpay();
        $this->hook($this->paidEvent($this->pendingPayment($this->booking())))->assertOk();

        $json = json_encode(PaymentEvent::firstOrFail()->payload);
        foreach (['olivia@example.com', 'Olivia', '+919800000000'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertStringContainsString('pay_Xyz789', $json);
    }

    public function test_the_same_event_id_sent_twice_changes_the_payment_once(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());
        $event = $this->paidEvent($p);

        $this->hook($event, 'evt_same')->assertOk();
        $paidAt = $p->fresh()->paid_at;
        $this->travel(2)->minutes();
        $this->hook($event, 'evt_same')->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertEquals($paidAt, $p->fresh()->paid_at);
    }

    public function test_without_an_event_id_header_the_same_body_still_counts_once(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());
        $event = $this->paidEvent($p);

        $this->hook($event, false)->assertOk();
        $this->hook($event, false)->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        $this->assertStringStartsWith('rzp-', PaymentEvent::firstOrFail()->external_id);
    }

    public function test_a_second_paid_event_for_the_same_link_is_ignored(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());
        $this->hook($this->paidEvent($p), 'evt_a')->assertOk();
        $this->hook($this->paidEvent($p), 'evt_b')->assertOk();

        $this->assertSame('ignored', PaymentEvent::where('external_id', 'evt_b')->firstOrFail()->outcome);
        $this->assertSame(1, Payment::where('status', 'succeeded')->count());
    }

    public function test_amount_or_currency_mismatch_is_not_booked_and_is_flagged(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking(), 2500);

        $this->hook($this->paidEvent($p, [], ['amount' => 100]), 'evt_1')->assertOk();
        $this->hook($this->paidEvent($p, [], ['currency' => 'USD']), 'evt_2')->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame(['failed', 'failed'], PaymentEvent::orderBy('id')->pluck('outcome')->all());
    }

    public function test_a_link_whose_status_is_not_paid_is_not_booked(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());

        $this->hook($this->paidEvent($p, ['status' => 'partially_paid']))->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_a_link_for_another_booking_or_session_is_not_booked(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking(), 2500, 'plink_real');
        $other = $this->booking();

        $this->hook($this->paidEvent($p, ['notes' => ['booking_id' => (string) $other->id, 'payment_id' => (string) $p->id]]), 'evt_1')->assertOk();
        $this->hook($this->paidEvent($p, ['id' => 'plink_other']), 'evt_2')->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame(['failed', 'failed'], PaymentEvent::orderBy('id')->pluck('outcome')->all());
    }

    public function test_payment_is_found_by_the_notes_when_the_link_id_was_never_saved(): void
    {
        // Our call timed out after Razorpay had created the link: the row is failed and has no link id.
        $this->configureRazorpay();
        $b = $this->booking();
        $p = $this->pendingPayment($b, 2500, null);
        $p->update(['status' => 'failed', 'failure_code' => 'session_error']);

        $this->hook($this->paidEvent($p, ['id' => 'plink_late']))->assertOk();

        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertNull($p->fresh()->failure_code);
    }

    public function test_a_stripe_payment_is_never_touched_by_a_razorpay_event(): void
    {
        $this->configureRazorpay();
        $b = $this->booking();
        $stripe = Payment::factory()->pending()->create(['booking_id' => $b->id, 'gateway' => 'stripe', 'gateway_session_id' => 'plink_Abc123', 'amount' => 2500, 'currency' => 'INR']);

        $this->hook($this->paidEvent($stripe))->assertOk();

        $this->assertSame('pending', $stripe->fresh()->status);
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_expired_and_cancelled_links_fail_a_pending_payment(): void
    {
        $this->configureRazorpay();
        $a = $this->pendingPayment($this->booking(), 100, 'plink_a');
        $c = $this->pendingPayment($this->booking(), 100, 'plink_c');

        $this->hook($this->linkEvent('payment_link.expired', $a), 'evt_x')->assertOk();
        $this->hook($this->linkEvent('payment_link.cancelled', $c), 'evt_y')->assertOk();

        $this->assertSame(['failed', 'session_expired'], [$a->fresh()->status, $a->fresh()->failure_code]);
        $this->assertSame(['failed', 'link_cancelled'], [$c->fresh()->status, $c->fresh()->failure_code]);
    }

    public function test_an_expired_message_never_undoes_a_payment_that_succeeded(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 10000]);
        $p = $this->pendingPayment($b, 2500);
        $this->hook($this->paidEvent($p), 'evt_1')->assertOk();
        $this->hook($this->linkEvent('payment_link.expired', $p), 'evt_2')->assertOk();

        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame('partial', $b->fresh()->payment_status);
        $this->assertSame('ignored', PaymentEvent::where('external_id', 'evt_2')->firstOrFail()->outcome);
    }

    public function test_one_failed_attempt_does_not_fail_the_link_because_the_customer_can_retry(): void
    {
        $this->configureRazorpay();
        $p = $this->pendingPayment($this->booking());
        $event = ['entity' => 'event', 'event' => 'payment.failed', 'created_at' => time(), 'payload' => ['payment' => ['entity' => ['id' => 'pay_f1', 'amount' => 250000, 'currency' => 'INR', 'status' => 'failed', 'error_code' => 'BAD_REQUEST_ERROR']]]];

        $this->hook($event)->assertOk();

        $this->assertSame('pending', $p->fresh()->status);
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_a_refund_made_in_the_dashboard_creates_a_refund_row(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 100]);
        $this->succeededCharge($b, 100);
        $this->assertSame('paid', $b->fresh()->payment_status);

        $this->hook($this->refundEvent('refund.processed'))->assertOk();

        $refund = Payment::where('type', 'refund')->firstOrFail();
        $this->assertSame('succeeded', $refund->status);
        $this->assertEquals(100.00, (float) $refund->amount);
        $this->assertSame('rfnd_Aaa111', $refund->gateway_reference);
        $this->assertSame('razorpay', $refund->gateway);
        $this->assertSame('refunded', $b->fresh()->payment_status);
    }

    public function test_a_created_refund_is_pending_then_processed_and_failed_refunds_are_marked(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 100]);
        $this->succeededCharge($b, 100);

        $this->hook($this->refundEvent('refund.created', ['status' => 'created']))->assertOk();
        $this->assertSame('pending', Payment::where('type', 'refund')->firstOrFail()->status);
        $this->assertSame('paid', $b->fresh()->payment_status);

        $this->hook($this->refundEvent('refund.processed'))->assertOk();
        $this->assertSame(1, Payment::where('type', 'refund')->count());
        $this->assertSame('refunded', $b->fresh()->payment_status);

        // a different refund that fails
        $this->hook($this->refundEvent('refund.failed', ['id' => 'rfnd_Bbb222', 'status' => 'failed', 'amount' => 5000]))->assertOk();
        $failed = Payment::where('gateway_reference', 'rfnd_Bbb222')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('refunded', $b->fresh()->payment_status, 'a failed refund moves no money');
    }

    public function test_a_refund_started_by_staff_is_finished_by_the_event_instead_of_duplicated(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 100]);
        $this->succeededCharge($b, 100);
        $row = Payment::factory()->refund()->pending()->create(['booking_id' => $b->id, 'gateway' => 'razorpay', 'gateway_reference' => 'rfnd_Aaa111', 'amount' => 100, 'currency' => 'INR']);

        $this->hook($this->refundEvent('refund.processed'))->assertOk();

        $this->assertSame(1, Payment::where('type', 'refund')->count());
        $this->assertSame('succeeded', $row->fresh()->status);
    }

    public function test_a_refund_for_an_unknown_payment_is_ignored(): void
    {
        $this->configureRazorpay();
        $this->hook($this->refundEvent('refund.processed', ['payment_id' => 'pay_unknown']))->assertOk();

        $this->assertSame(0, Payment::where('type', 'refund')->count());
        $this->assertSame('ignored', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_unused_event_types_are_logged_and_ignored(): void
    {
        $this->configureRazorpay();
        $this->hook(['entity' => 'event', 'event' => 'order.paid', 'created_at' => time(), 'payload' => []])->assertOk();

        $log = PaymentEvent::firstOrFail();
        $this->assertSame('ignored', $log->outcome);
        $this->assertSame('order.paid', $log->event);
    }

    public function test_a_server_fault_answers_500_and_the_retry_is_processed(): void
    {
        $this->configureRazorpay();
        $b = $this->booking(['total_amount' => 10000]);
        $p = $this->pendingPayment($b, 2500);
        $event = $this->paidEvent($p);

        Booking::updating(function () {
            static $once = true;
            if ($once) {
                $once = false;
                throw new \RuntimeException('db down');
            }
        });

        $this->hook($event, 'evt_retry')->assertStatus(500);
        $this->assertSame('pending', $p->fresh()->status, 'the whole change was rolled back');

        $this->hook($event, 'evt_retry')->assertOk();
        $this->assertSame('succeeded', $p->fresh()->status);
        $this->assertSame(1, PaymentEvent::count());
        $this->assertSame('processed', PaymentEvent::firstOrFail()->outcome);
    }

    public function test_the_webhook_log_line_for_a_bad_signature_has_no_body_or_secret(): void
    {
        $this->configureRazorpay();
        Log::spy();
        $p = $this->pendingPayment($this->booking());

        $this->hook($this->paidEvent($p), null, 'bad');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m) => ! str_contains($m, 'olivia') && ! str_contains($m, self::HOOK));
    }

    public function test_the_events_screen_shows_razorpay_events(): void
    {
        $this->configureRazorpay();
        $this->hook($this->paidEvent($this->pendingPayment($this->booking())))->assertOk();

        $this->get(route('admin.payments.events'))->assertOk()->assertSee('payment_link.paid')->assertSee('Payment succeeded.');
    }
}
