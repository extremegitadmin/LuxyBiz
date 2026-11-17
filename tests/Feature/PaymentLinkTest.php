<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\Gateway;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Payment gateway integration (sandbox): payment links and return pages (Day 11). Stripe is faked, no real request is made. */
class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_VERYSECRET1234567890';
    private const LINK = 'https://checkout.stripe.com/c/pay/cs_test_abc123';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminRole::class);
        $this->staff = User::factory()->create(['name' => 'Priya Staff']);
        $this->actingAs($this->staff);
    }

    private function configureStripe(string $mode = 'sandbox', string $secret = self::SECRET): void
    {
        PaymentSetting::put('gateway_provider', 'stripe');
        PaymentSetting::put('gateway_mode', $mode);
        PaymentSetting::put('gateway_public_key', 'pk_test_PUBLIC');
        PaymentSetting::putSecret('gateway_secret_key', $secret);
    }

    private function fakeStripeOk(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_abc123', 'url' => self::LINK], 200)]);
    }

    private function booking(array $attrs = []): Booking
    {
        $user = User::firstOrCreate(['email' => 'olivia@example.com'], ['name' => 'Olivia Bennett', 'password' => bcrypt('not-used')]);

        return Booking::factory()->create($attrs + ['user_id' => $user->id, 'status' => 'confirmed', 'total_amount' => 1000, 'currency' => 'USD']);
    }

    private function request(Booking $b, $amount)
    {
        return $this->post(route('admin.bookings.payment-link', $b), ['amount' => $amount]);
    }

    // ------------------------------------------------------------ happy path

    public function test_creates_a_pending_payment_and_shows_the_link(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();

        $this->request($b, '250.00')
            ->assertRedirect(route('admin.bookings.show', $b))
            ->assertSessionHas('payment_link', self::LINK)
            ->assertSessionHas('success');

        $p = Payment::firstOrFail();
        $this->assertSame('pending', $p->status);
        $this->assertSame('charge', $p->type);
        $this->assertSame('csr_link', $p->channel);
        $this->assertSame('stripe', $p->gateway);
        $this->assertSame('cs_test_abc123', $p->gateway_session_id);
        $this->assertSame($this->staff->id, $p->recorded_by);
        $this->assertSame('250.00', (string) $p->amount);
        $this->assertNull($p->paid_at);

        $this->get(route('admin.bookings.show', $b))
            ->assertOk()->assertSee(self::LINK)->assertSee('Anyone with this link can pay');
    }

    public function test_the_request_sent_to_stripe_is_correct_and_has_no_card_fields(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();

        $this->request($b, '19.99');
        $p = Payment::firstOrFail();

        Http::assertSent(function (Request $r) use ($b, $p) {
            $d = $r->data();

            return $r->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $r->method() === 'POST'
                && $r->header('Authorization')[0] === 'Basic ' . base64_encode(self::SECRET . ':')
                && $r->header('Idempotency-Key')[0] === 'payment-' . $p->id
                && $d['mode'] === 'payment'
                && $d['client_reference_id'] === $b->reference
                && $d['customer_email'] === 'olivia@example.com'
                && $d['billing_address_collection'] === 'required'
                && $d['line_items'][0]['price_data']['currency'] === 'usd'
                && (int) $d['line_items'][0]['price_data']['unit_amount'] === 1999          // 19.99 must not become 1998
                && (int) $d['metadata']['payment_id'] === $p->id
                && (int) $d['payment_intent_data']['metadata']['booking_id'] === $b->id
                && str_contains($d['payment_intent_data']['description'], $b->reference)
                && $d['success_url'] === route('payment.return')
                && $d['cancel_url'] === route('payment.cancelled')
                && ! str_contains($r->body(), 'card_number');
        });
    }

    public function test_the_amount_is_sent_in_the_smallest_unit_of_the_booking_currency(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking(['currency' => 'INR', 'total_amount' => 100000]);

        $this->request($b, '0.50');

        Http::assertSent(fn (Request $r) => $r->data()['line_items'][0]['price_data']['currency'] === 'inr'
            && (int) $r->data()['line_items'][0]['price_data']['unit_amount'] === 50);
    }

    public function test_the_secret_key_never_appears_in_the_page_session_database_or_audit(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();

        $this->request($b, '100');
        $page = $this->get(route('admin.bookings.show', $b))->getContent();

        $this->assertStringNotContainsString(self::SECRET, $page);
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
        $this->assertStringNotContainsString(self::SECRET, json_encode(DB::table('payments')->get()));
        if (Schema::hasTable('audit_logs')) {
            $this->assertStringNotContainsString(self::SECRET, json_encode(DB::table('audit_logs')->get()));
        }
    }

    public function test_the_audit_entry_never_contains_the_link(): void
    {
        if (! class_exists(\App\Support\Audit::class) || ! Schema::hasTable('audit_logs')) {
            $this->markTestSkipped("Anakha's audit helper (App\\Support\\Audit and the audit_logs table) is not merged yet.");
        }
        $this->configureStripe();
        $this->fakeStripeOk();

        $this->request($this->booking(), '100');

        $stored = json_encode(DB::table('audit_logs')->get());
        $this->assertStringContainsString('payment.session_created', $stored);
        $this->assertStringNotContainsString('checkout.stripe.com', $stored);
    }

    // ------------------------------------------------------------ validation

    public function test_amount_must_be_a_sensible_money_value(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();

        foreach (['', 'abc', '0', '0.4', '-5', '10.123', '99999999'] as $bad) {
            $this->request($b, $bad)->assertSessionHasErrors('amount');
        }
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_amount_cannot_exceed_the_balance_due(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking(['total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 700]);

        $this->request($b, '400')->assertSessionHas('error');
        $this->assertStringContainsString('more than the balance due (USD 300.00)', session('error'));
        $this->assertSame(1, Payment::count());   // only the earlier payment

        $this->request($b, '300')->assertSessionHas('payment_link');
    }

    public function test_the_balance_is_net_of_refunds(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking(['total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000]);
        Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 400]);

        $this->request($b, '400')->assertSessionHas('payment_link');
    }

    public function test_cancelled_completed_and_other_unpayable_bookings_are_refused(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();

        foreach (['cancelled', 'completed', 'cancellation_requested'] as $status) {
            $this->request($this->booking(['status' => $status]), '100')->assertSessionHas('error');
        }
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_repeat_submit_within_a_minute_is_blocked(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();

        $this->request($b, '100')->assertSessionHas('payment_link');
        $this->request($b, '100')->assertSessionHas('error');

        $this->assertSame(1, Payment::count());
    }

    // ------------------------------------------------------------ settings problems leave no trace

    public function test_with_manual_selected_nothing_is_created_and_nothing_is_sent(): void
    {
        Http::fake();
        $b = $this->booking();

        $this->request($b, '100')->assertSessionHas('error');

        $this->assertStringContainsString('No online payment provider', session('error'));
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_provider_without_an_adapter_behaves_like_manual(): void
    {
        PaymentSetting::put('gateway_provider', 'razorpay');
        $this->assertInstanceOf(ManualGateway::class, Gateway::resolve());
        $this->assertFalse(Gateway::hasAdapter('razorpay'));
        $this->assertTrue(Gateway::hasAdapter('stripe'));
        $this->assertTrue(Gateway::hasAdapter('manual'));
    }

    public function test_a_live_key_in_sandbox_mode_is_refused_before_anything_happens(): void
    {
        $this->configureStripe('sandbox', 'sk_live_REALMONEYKEY');
        Http::fake();
        $b = $this->booking();

        $this->request($b, '100')->assertSessionHas('error');

        $this->assertStringContainsString('live key but the mode is sandbox', session('error'));
        $this->assertStringNotContainsString('REALMONEYKEY', session('error'));
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_test_key_in_live_mode_is_refused_too(): void
    {
        $this->configureStripe('live', 'sk_test_TESTKEY');
        Http::fake();

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('test key but the mode is live', session('error'));
        Http::assertNothingSent();
    }

    public function test_a_key_that_is_not_a_stripe_key_is_refused(): void
    {
        $this->configureStripe('sandbox', 'something-else-123');
        Http::fake();

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('does not look like a Stripe secret key', session('error'));
        $this->assertStringNotContainsString('something-else-123', session('error'));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ provider failures

    public function test_a_rejected_key_marks_the_payment_failed_and_explains_why(): void
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid API Key provided: ' . self::SECRET]], 401)]);
        $b = $this->booking();

        $this->request($b, '100')->assertSessionHas('error')->assertSessionMissing('payment_link');

        $this->assertStringContainsString('rejected the secret key', session('error'));
        $this->assertStringNotContainsString(self::SECRET, session('error'));
        $p = Payment::firstOrFail();
        $this->assertSame('failed', $p->status);
        $this->assertSame('session_error', $p->failure_code);
        $this->assertNull($p->gateway_session_id);
    }

    public function test_provider_error_text_is_shown_but_never_with_the_key(): void
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'parameter_invalid_integer', 'message' => 'Bad amount, key ' . self::SECRET . ' was used']], 400)]);

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('parameter_invalid_integer', session('error'));
        $this->assertStringNotContainsString(self::SECRET, session('error'));
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_a_network_failure_is_handled_politely(): void
    {
        $this->configureStripe();
        Http::fake(function () {
            throw new ConnectionException('cURL error 6: could not resolve host');
        });

        $this->request($this->booking(), '100')->assertSessionHas('error');

        $this->assertStringContainsString('Could not reach Stripe', session('error'));
        $this->assertSame('failed', Payment::firstOrFail()->status);
    }

    public function test_a_response_without_a_proper_https_link_is_not_trusted(): void
    {
        $this->configureStripe();

        foreach ([['id' => 'cs_1'], ['id' => 'cs_1', 'url' => 'javascript:alert(1)'], ['id' => 'cs_1', 'url' => 'http://insecure.example/pay'], ['url' => self::LINK]] as $body) {
            Http::fake(['api.stripe.com/*' => Http::response($body, 200)]);
            $b = $this->booking();

            $this->request($b, '100')->assertSessionHas('error')->assertSessionMissing('payment_link');
        }
        $this->assertSame(0, Payment::where('status', 'pending')->count());
    }

    // ------------------------------------------------------------ booking page

    public function test_booking_page_shows_the_form_with_the_balance_when_online_payments_are_ready(): void
    {
        $this->configureStripe();
        $b = $this->booking(['total_amount' => 1000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 250]);

        $this->get(route('admin.bookings.show', $b))
            ->assertOk()
            ->assertSee('Request online payment')
            ->assertSee('Create payment link')
            ->assertSee('value="750.00"', false)
            ->assertSee(route('admin.bookings.payment-link', $b), false);
    }

    public function test_booking_page_explains_when_online_payments_are_not_set_up(): void
    {
        $b = $this->booking();

        $this->get(route('admin.bookings.show', $b))
            ->assertOk()->assertSee('Online payments are not set up yet')->assertDontSee('Create payment link')
            ->assertSee(route('admin.settings.gateway'), false);
    }

    public function test_booking_page_has_no_form_for_a_cancelled_or_fully_paid_booking(): void
    {
        $this->configureStripe();

        $this->get(route('admin.bookings.show', $this->booking(['status' => 'cancelled'])))
            ->assertSee('so it cannot take a payment')->assertDontSee('Create payment link');

        $paid = $this->booking(['total_amount' => 500]);
        Payment::factory()->create(['booking_id' => $paid->id, 'amount' => 500]);
        $this->get(route('admin.bookings.show', $paid))->assertSee('Nothing left to pay')->assertDontSee('Create payment link');
    }

    public function test_booking_page_warns_about_links_that_are_still_open(): void
    {
        $this->configureStripe();
        $this->fakeStripeOk();
        $b = $this->booking();
        $this->request($b, '300');

        $this->get(route('admin.bookings.show', $b))
            ->assertSee('1 payment link for this booking is still open')->assertSee('USD 300.00')->assertSee('overpaid');
    }

    // ------------------------------------------------------------ return pages

    public function test_return_pages_are_public_show_no_booking_data_and_change_nothing(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->pending()->create(['booking_id' => $b->id, 'amount' => 100, 'gateway_session_id' => 'cs_test_abc123']);
        auth()->logout();

        $ok = $this->get(route('payment.return'));
        $ok->assertOk()->assertSee('Your payment is being confirmed')->assertDontSee($b->reference);
        $this->assertStringContainsString('no-store', $ok->headers->get('Cache-Control'));

        $this->get(route('payment.cancelled'))->assertOk()->assertSee('No payment was taken');

        // Opening the "success" page must never mark anything as paid. Only the provider's webhook does that.
        $this->assertSame('pending', $p->fresh()->status);
        $this->assertNull($p->fresh()->paid_at);
    }

    // ------------------------------------------------------------ test connection

    public function test_test_connection_reports_success_without_showing_the_key(): void
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['object' => 'balance'], 200)]);

        $this->post(route('admin.settings.gateway.test'))
            ->assertSessionHas('success', 'Connected to Stripe (sandbox mode). The secret key works.');
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === 'https://api.stripe.com/v1/balance');
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
    }

    public function test_test_connection_reports_a_rejected_key(): void
    {
        $this->configureStripe();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['type' => 'authentication_error']], 401)]);

        $this->post(route('admin.settings.gateway.test'))->assertSessionHasErrors('test');
        $this->assertStringContainsString('rejected the secret key', session('errors')->first('test'));
    }

    public function test_test_connection_with_manual_sends_nothing(): void
    {
        Http::fake();

        $this->post(route('admin.settings.gateway.test'))->assertSessionHasErrors('test');
        Http::assertNothingSent();
    }

    public function test_gateway_page_shows_the_test_button_only_when_keys_are_usable(): void
    {
        $this->get(route('admin.settings.gateway'))->assertDontSee('Test saved keys');

        $this->configureStripe();
        $this->get(route('admin.settings.gateway'))->assertSee('Test saved keys')->assertSee('Connection built');
    }

    public function test_resolve_returns_stripe_when_configured(): void
    {
        $this->configureStripe();

        $this->assertInstanceOf(StripeGateway::class, Gateway::resolve());
        $this->assertTrue(Gateway::isOnline());
    }
}
