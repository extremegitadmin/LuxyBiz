<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\PaymentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Gateway settings (M3): one card per provider, routing by currency, keys, webhook URLs (Days 10 and 12). */
class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const SECRET = 'sk_test_SUPERSECRET123';
    private const HOOK = 'whsec_HOOKSECRET456';
    private const RZP_ID = 'rzp_test_AbC123xyz';
    private const RZP_SECRET = 'RZPSECRETVALUE9988';
    private const RZP_HOOK = 'rzpHookSecret77';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    private function save(string $provider, array $override = [], bool $https = false)
    {
        $defaults = [
            'stripe' => ['enabled' => '1', 'mode' => 'sandbox', 'public_key' => 'pk_test_PUBLIC789', 'secret_key' => self::SECRET, 'webhook_secret' => self::HOOK],
            'razorpay' => ['enabled' => '1', 'mode' => 'sandbox', 'public_key' => self::RZP_ID, 'secret_key' => self::RZP_SECRET, 'webhook_secret' => self::RZP_HOOK],
        ];

        $url = route('admin.settings.gateway.update', $provider);
        // The test client marks a request secure from its URL scheme. Set it explicitly both ways, because
        // route() copies the scheme of the previous request inside one test.
        $url = preg_replace('#^https?://#', $https ? 'https://' : 'http://', $url);

        return $this->put($url, array_merge($defaults[$provider], $override));
    }

    public function test_page_shows_a_card_per_provider_with_its_own_webhook_address(): void
    {
        $this->get(route('admin.settings.gateway'))
            ->assertOk()
            ->assertSee('Payment gateways')
            ->assertSee('Razorpay')->assertSee('Stripe')->assertSee('PayPal')
            ->assertSee('Not built yet')
            ->assertSee(url('/webhooks/payments/razorpay'), false)
            ->assertSee(url('/webhooks/payments/stripe'), false);
    }

    public function test_saving_stores_keys_per_provider_and_secrets_are_encrypted_at_rest(): void
    {
        $this->save('stripe')->assertRedirect(route('admin.settings.gateway'))->assertSessionHas('success');
        $this->save('razorpay')->assertSessionHas('success');

        $this->assertTrue(PaymentSetting::isEnabled('stripe'));
        $this->assertTrue(PaymentSetting::isEnabled('razorpay'));
        $this->assertSame('pk_test_PUBLIC789', PaymentSetting::field('stripe', 'public_key'));
        $this->assertSame(self::RZP_ID, PaymentSetting::field('razorpay', 'public_key'));
        $this->assertSame(self::SECRET, PaymentSetting::config('stripe')['secret_key']);
        $this->assertSame(self::RZP_SECRET, PaymentSetting::config('razorpay')['secret_key']);

        $raw = json_encode(DB::table('payment_settings')->get());
        foreach ([self::SECRET, self::HOOK, self::RZP_SECRET, self::RZP_HOOK] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, 'secrets must be encrypted in the database');
        }
    }

    public function test_one_providers_settings_do_not_touch_the_other(): void
    {
        $this->save('stripe');
        $this->save('razorpay', ['enabled' => '0']);

        $this->assertTrue(PaymentSetting::isEnabled('stripe'));
        $this->assertFalse(PaymentSetting::isEnabled('razorpay'));
        $this->assertSame(self::SECRET, PaymentSetting::config('stripe')['secret_key']);
    }

    public function test_secrets_are_never_shown_again(): void
    {
        $this->save('stripe');
        $this->save('razorpay');

        $html = $this->get(route('admin.settings.gateway'))->assertOk()->getContent();
        foreach ([self::SECRET, self::HOOK, self::RZP_SECRET, self::RZP_HOOK] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertStringContainsString('Saved (blank keeps it)', $html);
    }

    public function test_blank_secrets_keep_the_saved_ones_when_the_mode_is_unchanged(): void
    {
        $this->save('razorpay');
        $this->save('razorpay', ['secret_key' => '', 'webhook_secret' => ''])->assertSessionHasNoErrors();

        $this->assertSame(self::RZP_SECRET, PaymentSetting::config('razorpay')['secret_key']);
        $this->assertSame(self::RZP_HOOK, PaymentSetting::config('razorpay')['webhook_secret']);
    }

    public function test_changing_the_mode_requires_the_secrets_again_and_drops_the_old_ones(): void
    {
        $this->save('stripe');
        $this->save('stripe', ['mode' => 'live', 'public_key' => 'pk_live_X1', 'secret_key' => '', 'webhook_secret' => '', 'confirm_live' => '1'], true)
            ->assertSessionHasErrors(['secret_key', 'webhook_secret'], null, 'stripe');
        $this->assertSame(self::SECRET, PaymentSetting::config('stripe')['secret_key'], 'a failed save changes nothing');
    }

    public function test_switching_a_provider_on_needs_all_three_keys_the_first_time(): void
    {
        $this->save('razorpay', ['public_key' => '', 'secret_key' => '', 'webhook_secret' => ''])
            ->assertSessionHasErrors(['public_key', 'secret_key', 'webhook_secret'], null, 'razorpay');
        $this->assertFalse(PaymentSetting::isEnabled('razorpay'));
    }

    public function test_a_provider_can_be_switched_off_without_any_keys(): void
    {
        $this->save('stripe', ['enabled' => '0', 'public_key' => '', 'secret_key' => '', 'webhook_secret' => ''])
            ->assertSessionHasNoErrors();
        $this->assertFalse(PaymentSetting::isEnabled('stripe'));
    }

    public function test_keys_with_spaces_or_line_breaks_are_rejected(): void
    {
        $this->save('stripe', ['secret_key' => "sk_test_ABC DEF"])->assertSessionHasErrors('secret_key', null, 'stripe');
        $this->save('stripe', ['webhook_secret' => "whsec_ABC\nDEF"])->assertSessionHasErrors('webhook_secret', null, 'stripe');
    }

    public function test_a_pasted_key_of_the_wrong_kind_or_wrong_mode_is_caught_at_save_time(): void
    {
        // Razorpay Key Id must look like rzp_test_... / rzp_live_...
        $this->save('razorpay', ['public_key' => 'pk_test_ABC'])->assertSessionHasErrors('public_key', null, 'razorpay');
        // A live Key Id while the mode is sandbox
        $this->save('razorpay', ['public_key' => 'rzp_live_ABC123'])->assertSessionHasErrors('public_key', null, 'razorpay');
        // A live secret key while the mode is sandbox
        $this->save('stripe', ['secret_key' => 'sk_live_ABC123'])->assertSessionHasErrors('secret_key', null, 'stripe');
        // Stripe secret key must look like sk_/rk_
        $this->save('stripe', ['secret_key' => 'whsec_ABC123'])->assertSessionHasErrors('secret_key', null, 'stripe');

        $this->assertFalse(PaymentSetting::isEnabled('razorpay'));
        $this->assertFalse(PaymentSetting::isEnabled('stripe'));
    }

    public function test_live_mode_needs_confirmation_and_a_secure_connection(): void
    {
        $live = ['mode' => 'live', 'public_key' => self::RZP_ID === '' ? '' : 'rzp_live_Real123'];

        $this->save('razorpay', $live, true)->assertSessionHasErrors('confirm_live', null, 'razorpay');
        $this->save('razorpay', $live + ['confirm_live' => '1'], false)->assertSessionHasErrors('mode', null, 'razorpay');

        $this->save('razorpay', $live + ['confirm_live' => '1'], true)->assertSessionHasNoErrors();
        $this->assertSame('live', PaymentSetting::field('razorpay', 'mode'));
    }

    public function test_staying_in_live_mode_does_not_ask_for_confirmation_again(): void
    {
        $live = ['mode' => 'live', 'public_key' => 'rzp_live_Real123', 'confirm_live' => '1'];
        $this->save('razorpay', $live, true)->assertSessionHasNoErrors();

        $this->save('razorpay', ['mode' => 'live', 'public_key' => 'rzp_live_Real123', 'secret_key' => '', 'webhook_secret' => ''], true)
            ->assertSessionHasNoErrors();
    }

    public function test_invalid_mode_is_rejected_and_unknown_providers_have_no_route(): void
    {
        $this->save('stripe', ['mode' => 'nonsense'])->assertSessionHasErrors('mode', null, 'stripe');
        $this->put(preg_replace('#/stripe$#', '/paypal', route('admin.settings.gateway.update', 'stripe')), ['mode' => 'sandbox'])->assertNotFound();
    }

    public function test_a_failed_save_never_puts_secrets_back_in_the_session_or_the_form(): void
    {
        $this->save('stripe', ['mode' => 'nonsense'])->assertSessionHasErrors('mode', null, 'stripe');

        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('secret_key', $old);
        $this->assertArrayNotHasKey('webhook_secret', $old);
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
        $this->assertStringNotContainsString(self::HOOK, json_encode(session()->all()));
    }

    public function test_routing_chooses_the_provider_per_currency(): void
    {
        $this->assertSame('razorpay', PaymentSetting::routedProvider('INR'));
        $this->assertSame('stripe', PaymentSetting::routedProvider('USD'));
        $this->assertSame('stripe', PaymentSetting::routedProvider('EUR'));

        $this->put(route('admin.settings.gateway.routing'), ['route_inr' => 'stripe', 'route_other' => 'razorpay'])
            ->assertRedirect(route('admin.settings.gateway'))->assertSessionHas('success');

        $this->assertSame('stripe', PaymentSetting::routedProvider('inr'));
        $this->assertSame('razorpay', PaymentSetting::routedProvider('GBP'));
    }

    public function test_routing_rejects_unknown_providers(): void
    {
        $this->put(route('admin.settings.gateway.routing'), ['route_inr' => 'paypal', 'route_other' => 'stripe'])->assertSessionHasErrors('route_inr');
        $this->put(route('admin.settings.gateway.routing'), ['route_inr' => 'razorpay'])->assertSessionHasErrors('route_other');
    }

    public function test_the_page_warns_when_the_chosen_provider_is_not_ready(): void
    {
        $this->get(route('admin.settings.gateway'))->assertSee('Razorpay is not ready yet')->assertSee('Stripe is not ready yet');

        $this->save('razorpay');
        $this->save('stripe');
        $this->get(route('admin.settings.gateway'))->assertDontSee('is not ready yet');
    }

    public function test_the_audit_entry_says_what_changed_but_never_the_secrets(): void
    {
        if (! class_exists(\App\Support\Audit::class) || ! Schema::hasTable('audit_logs')) {
            $this->markTestSkipped("Anakha's audit helper (App\\Support\\Audit and the audit_logs table) is not merged yet.");
        }

        $this->save('razorpay');

        $this->assertNotNull(DB::table('audit_logs')->where('action', 'settings.gateway_updated')->first());
        $stored = json_encode(DB::table('audit_logs')->get());
        foreach ([self::RZP_SECRET, self::RZP_HOOK, self::SECRET, self::HOOK] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        $this->assertStringContainsString('secret_key_changed', $stored);
    }

    public function test_config_returns_the_decrypted_values_for_the_adapter(): void
    {
        $this->save('stripe');

        $this->assertSame([
            'provider' => 'stripe', 'enabled' => true, 'mode' => 'sandbox', 'public_key' => 'pk_test_PUBLIC789',
            'secret_key' => self::SECRET, 'webhook_secret' => self::HOOK,
        ], PaymentSetting::config('stripe'));
    }

    public function test_an_unreadable_secret_gives_null_instead_of_an_error(): void
    {
        // e.g. APP_KEY was changed after the secret was saved
        PaymentSetting::put('gw.stripe.secret_key', 'not-a-valid-encrypted-value');

        $this->assertNull(PaymentSetting::secret('gw.stripe.secret_key'));
        $this->get(route('admin.settings.gateway'))->assertOk();
    }

    public function test_a_card_shows_ready_only_when_switched_on_with_a_saved_secret(): void
    {
        $view = $this->get(route('admin.settings.gateway'));
        $this->assertFalse($view->viewData('cards')['razorpay']['ready']);

        $this->save('razorpay');
        $card = $this->get(route('admin.settings.gateway'))->viewData('cards')['razorpay'];
        $this->assertTrue($card['ready']);
        $this->assertTrue($card['hasSecret']);
        $this->assertTrue($card['hasWebhook']);
    }
}
