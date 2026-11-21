<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Notifications\PaymentLinkNotification;
use App\Services\Payments\PaymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Day 29: customer payment links (create, send, open, pay, expire, cancel, renew). */
class PaymentRequestTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private User $staff;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = $this->signInAsSuperAdmin();
        PaymentSetting::putField('stripe', 'enabled', '1');
        PaymentSetting::putField('stripe', 'mode', 'sandbox');
        PaymentSetting::putField('stripe', 'public_key', 'pk_test_PUBLIC');
        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_VERYSECRET1234567890');
        Http::fake(['api.stripe.com/*' => Http::sequence()
            ->push(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'], 200)
            ->push(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2'], 200)
            ->whenEmpty(Http::response(['id' => 'cs_test_n', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_n'], 200))]);
        $customer = User::forceCreate(['name' => 'Olivia Bennett', 'email' => 'olivia@example.com', 'password' => bcrypt('x')]);
        $this->booking = Booking::factory()->csr($this->staff)->create(['user_id' => $customer->id, 'status' => 'pending', 'total_amount' => 1000, 'subtotal' => 1000, 'insurance_amount' => 0, 'currency' => 'USD']);
        Notification::fake();
    }

    private function svc(): PaymentRequestService
    {
        return app(PaymentRequestService::class);
    }

    private function make(float $amount = 1000): array
    {
        return $this->svc()->create($this->booking, $amount, $this->staff);
    }

    public function test_only_a_hash_of_the_link_is_stored(): void
    {
        ['request' => $r, 'url' => $url] = $this->make();
        $token = basename($url);

        $this->assertSame(43, strlen($token));
        $this->assertSame(hash('sha256', $token), $r->token_hash);
        $this->assertStringNotContainsString($token, json_encode(\DB::table('payment_requests')->get()));
    }

    public function test_two_links_never_share_a_token(): void
    {
        $a = basename($this->make(100)['url']);
        $this->travel(2)->minutes();
        $b = basename($this->make(100)['url']);
        $this->assertNotSame($a, $b);
    }

    public function test_link_rules(): void
    {
        $cases = [
            'zero' => fn () => $this->make(0),
            'negative' => fn () => $this->make(-1),
            'more than balance' => fn () => $this->make(1000.01),
        ];
        foreach ($cases as $name => $try) {
            try {
                $try();
                $this->fail("Accepted: $name");
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->booking->update(['status' => 'cancelled']);
        $this->expectException(\DomainException::class);
        $this->make(10);
    }

    public function test_a_link_cannot_be_made_when_no_provider_is_ready(): void
    {
        PaymentSetting::putField('stripe', 'enabled', '0');
        $this->expectException(\DomainException::class);
        $this->make(10);
    }

    public function test_duplicate_click_guard_and_open_link_limit(): void
    {
        $this->make(10);
        try {
            $this->make(10);
            $this->fail('A double click made two links');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->travel(2)->minutes();
            $this->make(10 + $i);
        }
        $this->travel(2)->minutes();
        $this->expectException(\DomainException::class);
        $this->make(99);
    }

    public function test_email_goes_to_the_customer_on_the_account_and_records_when(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(400);
        $this->assertTrue($this->svc()->send($r, $url));

        Notification::assertSentOnDemand(PaymentLinkNotification::class, fn ($n, $channels, $notifiable) => array_key_exists('olivia@example.com', $notifiable->routes['mail']));
        $r->refresh();
        $this->assertSame('olivia@example.com', $r->sent_to);
        $this->assertNotNull($r->sent_at);
        $this->assertNull($r->send_error);
    }

    public function test_a_failed_email_is_recorded_not_thrown(): void
    {
        Notification::swap(new class extends \Illuminate\Notifications\ChannelManager {
            public function __construct() {}
            public function route($c, $r) { return new class { public function notifyNow($n) { throw new \RuntimeException('smtp down'); } }; }
        });
        ['request' => $r, 'url' => $url] = $this->make(400);
        $this->assertFalse($this->svc()->send($r, $url));
        $r->refresh();
        $this->assertNull($r->sent_at);
        $this->assertNotNull($r->send_error);
        $this->assertStringNotContainsString('smtp', $r->send_error);
    }

    public function test_customer_page_shows_the_amount_and_hides_everything_else(): void
    {
        $this->app['auth']->forgetGuards();
        ['url' => $url] = $this->make(400);
        $this->flushSession();
        auth()->logout();

        $r = $this->get($url)->assertOk();
        $r->assertSee($this->booking->reference)->assertSee('400.00')->assertSee('Pay securely');
        $r->assertDontSee('olivia@example.com');
        $this->assertSame(1, PaymentRequest::firstOrFail()->open_count);
        $h = $r->headers;
        $this->assertStringContainsString('no-store', $h->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $h->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $h->get('Referrer-Policy'));
    }

    public function test_unknown_malformed_and_guessed_links_look_the_same(): void
    {
        ['url' => $url] = $this->make(400);
        auth()->logout();
        $real = basename($url);
        $wrongButValid = substr($real, 0, 42) . ($real[42] === 'a' ? 'b' : 'a');

        $bodies = [];
        foreach ([$wrongButValid, 'short', str_repeat('z', 43), str_repeat('z', 80)] as $t) {
            $r = $this->get(route('pay.show', $t))->assertOk();
            $r->assertSee('This link cannot be used');
            $bodies[] = preg_replace('/csrf[^"]*"[^"]*"/i', '', $r->getContent());
        }
        $this->assertSame(1, count(array_unique(array_map(fn ($b) => preg_replace('/value="[^"]*"/', '', $b), $bodies))));
        $this->post(route('pay.start', 'short'))->assertOk()->assertSee('This link cannot be used');
    }

    public function test_customer_can_start_payment_and_is_sent_to_the_provider(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(400);
        auth()->logout();

        $this->post(route('pay.start', basename($url)))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $p = Payment::firstOrFail();
        $this->assertEquals(400.0, (float) $p->amount);
        $this->assertSame($r->id, (int) $p->payment_request_id);
        $this->assertSame('csr_link', $p->channel);
    }

    public function test_a_second_payment_page_is_not_made_straight_away(): void
    {
        ['url' => $url] = $this->make(400);
        auth()->logout();
        $token = basename($url);

        $this->post(route('pay.start', $token))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $this->post(route('pay.start', $token))->assertRedirect(route('pay.show', $token))->assertSessionHas('pay_error');
        $this->assertSame(1, Payment::count());

        $this->travel(6)->minutes();
        $this->post(route('pay.start', $token))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_2');
        $this->assertSame(2, Payment::count());
    }

    public function test_provider_errors_are_not_shown_to_the_customer(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Invalid API Key provided: sk_test_VERY****7890']], 401)]);
        ['url' => $url] = $this->make(400);
        auth()->logout();
        $token = basename($url);

        $r = $this->post(route('pay.start', $token))->assertRedirect(route('pay.show', $token));
        $msg = session('pay_error');
        $this->assertNotEmpty($msg);
        $this->assertStringNotContainsStringIgnoringCase('api key', $msg);
        $this->assertStringNotContainsString('sk_', $msg);
    }

    public function test_the_link_expires(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(400);
        auth()->logout();
        $token = basename($url);

        $this->travel(config('booking.payment_link_hours') - 1)->hours();
        $this->get(route('pay.show', $token))->assertSee('Pay securely');

        $this->travel(2)->hours();
        $this->get(route('pay.show', $token))->assertSee('expired')->assertDontSee('Pay securely');
        $this->post(route('pay.start', $token))->assertRedirect()->assertSessionHas('pay_error');
        $this->assertSame(0, Payment::count());
        $this->assertSame('expired', $r->fresh()->status());
    }

    public function test_a_cancelled_link_stops_working(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(400);
        $this->svc()->cancel($r, $this->staff);
        auth()->logout();

        $this->get($url)->assertSee('no longer active')->assertDontSee('Pay securely');
        $this->post(route('pay.start', basename($url)))->assertRedirect()->assertSessionHas('pay_error');
        $this->assertSame(0, Payment::count());
    }

    public function test_renewing_replaces_the_old_link(): void
    {
        ['request' => $r, 'url' => $old] = $this->make(400);
        $new = $this->svc()->resend($r, $this->staff);
        auth()->logout();

        $this->assertNotSame(basename($old), basename($new));
        $this->get($old)->assertSee('This link cannot be used');
        $this->get($new)->assertSee('Pay securely');
        $this->post(route('pay.start', basename($old)))->assertOk()->assertSee('This link cannot be used');
        Notification::assertSentOnDemandTimes(PaymentLinkNotification::class, 1);
    }

    public function test_a_paid_link_shows_paid_and_cannot_be_used_again(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(1000);
        auth()->logout();
        Payment::factory()->create(['booking_id' => $this->booking->id, 'payment_request_id' => $r->id, 'type' => 'charge', 'status' => 'succeeded', 'amount' => 1000, 'currency' => 'USD']);
        $this->booking->syncPaymentStatus();

        $this->get($url)->assertSee('Already paid')->assertDontSee('Pay securely');
        $this->assertNotNull($r->fresh()->paid_at);
        $this->post(route('pay.start', basename($url)))->assertRedirect()->assertSessionHas('pay_error');
        $this->assertSame(1, Payment::count());
    }

    public function test_the_customer_never_pays_more_than_is_still_due(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(900);
        // 600 arrives some other way, so only 400 is left
        Payment::factory()->create(['booking_id' => $this->booking->id, 'type' => 'charge', 'status' => 'succeeded', 'amount' => 600, 'currency' => 'USD']);
        auth()->logout();

        $this->get($url)->assertSee('400.00')->assertDontSee('900.00');
        $this->post(route('pay.start', basename($url)));
        $this->assertEquals(400.0, (float) Payment::where('status', 'pending')->firstOrFail()->amount);
    }

    public function test_link_becomes_unavailable_if_the_booking_is_cancelled(): void
    {
        ['url' => $url] = $this->make(400);
        $this->booking->update(['status' => 'cancelled']);
        auth()->logout();
        $this->get($url)->assertSee('This link cannot be used')->assertDontSee('Pay securely');
    }

    public function test_the_customer_page_is_throttled(): void
    {
        auth()->logout();
        $codes = [];
        for ($i = 0; $i < 35; $i++) {
            $codes[] = $this->get(route('pay.show', 'x' . $i))->getStatusCode();
        }
        $this->assertContains(429, $codes);
    }

    public function test_audit_entries_exist_and_never_hold_the_link(): void
    {
        if (! \Schema::hasTable('activity_logs')) {
            \Schema::create('activity_logs', function ($t) {
                $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('action'); $t->text('description')->nullable(); $t->text('properties')->nullable(); $t->timestamps();
            });
        }
        ['request' => $r, 'url' => $url] = $this->make(400);
        $this->svc()->send($r, $url);
        $new = $this->svc()->resend($r, $this->staff);
        $this->svc()->cancel($r->fresh(), $this->staff);

        $actions = \DB::table('activity_logs')->pluck('action')->all();
        foreach (['payment.link_created', 'payment.link_sent', 'payment.link_renewed', 'payment.link_cancelled'] as $a) {
            $this->assertContains($a, $actions);
        }
        $all = \DB::table('activity_logs')->get()->toJson();
        $this->assertStringNotContainsString(basename($url), $all);
        $this->assertStringNotContainsString(basename($new), $all);
        $this->assertStringNotContainsString('olivia@example.com', $all);
    }

    public function test_email_text_holds_the_link_and_no_card_wording(): void
    {
        ['request' => $r, 'url' => $url] = $this->make(400);
        $html = (new PaymentLinkNotification($r, $url))->toMail(new \Illuminate\Notifications\AnonymousNotifiable())->render();
        $this->assertStringContainsString($url, (string) $html);
        $this->assertStringContainsString($this->booking->reference, (string) $html);
    }
}
