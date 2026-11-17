<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminRole;
use App\Models\PaymentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Gateway settings (M3): provider, mode, keys, webhook URL (Day 10). */
class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_SUPERSECRET123';
    private const HOOK = 'whsec_HOOKSECRET456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminRole::class);
    }

    private function save(array $override = [], bool $https = false)
    {
        $payload = array_merge([
            'provider' => 'stripe', 'mode' => 'sandbox', 'public_key' => 'pk_test_PUBLIC789',
            'secret_key' => self::SECRET, 'webhook_secret' => self::HOOK,
        ], $override);

        $url = route('admin.settings.gateway.update');
        // The test client marks a request secure from its URL scheme. Set it explicitly both ways, because
        // route() copies the scheme of the previous request inside one test.
        $url = preg_replace('#^https?://#', $https ? 'https://' : 'http://', $url);

        return $this->put($url, $payload);
    }

    public function test_page_shows_defaults_and_the_webhook_url(): void
    {
        $this->get(route('admin.settings.gateway'))
            ->assertOk()
            ->assertSee('Payment gateway')
            ->assertSee('Manual (offline payments only)')
            ->assertSee('Not saved')
            ->assertSee(url('/webhooks/payments'), false);
    }

    public function test_saving_stores_keys_and_secrets_are_encrypted_at_rest(): void
    {
        $this->save()->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.gateway'));

        $this->assertSame('stripe', PaymentSetting::get('gateway_provider'));
        $this->assertSame('pk_test_PUBLIC789', PaymentSetting::get('gateway_public_key'));

        $raw = json_encode(DB::table('payment_settings')->get());
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringNotContainsString(self::HOOK, $raw);
        $this->assertSame(self::SECRET, PaymentSetting::secret('gateway_secret_key'));
        $this->assertSame(self::HOOK, PaymentSetting::secret('gateway_webhook_secret'));
    }

    public function test_secrets_are_never_shown_again(): void
    {
        $this->save();

        $this->get(route('admin.settings.gateway'))
            ->assertOk()
            ->assertDontSee(self::SECRET)
            ->assertDontSee(self::HOOK)
            ->assertSee('Saved. Leave blank to keep it', false)
            ->assertSee('pk_test_PUBLIC789');   // the public key is not secret
    }

    public function test_blank_secrets_keep_the_saved_ones_when_nothing_else_changed(): void
    {
        $this->save();

        $this->save(['public_key' => 'pk_test_NEWPUBLIC', 'secret_key' => '', 'webhook_secret' => ''])->assertSessionHasNoErrors();

        $this->assertSame('pk_test_NEWPUBLIC', PaymentSetting::get('gateway_public_key'));
        $this->assertSame(self::SECRET, PaymentSetting::secret('gateway_secret_key'));
        $this->assertSame(self::HOOK, PaymentSetting::secret('gateway_webhook_secret'));
    }

    public function test_changing_provider_or_mode_requires_the_secrets_again(): void
    {
        $this->save();

        $this->save(['provider' => 'razorpay', 'secret_key' => '', 'webhook_secret' => ''])
            ->assertSessionHasErrors(['secret_key', 'webhook_secret']);
        $this->assertSame('stripe', PaymentSetting::get('gateway_provider'));          // nothing was changed

        $this->save(['provider' => 'razorpay', 'secret_key' => 'rzp_secret_NEW', 'webhook_secret' => 'rzp_hook_NEW'])
            ->assertSessionHasNoErrors();
        $this->assertSame('rzp_secret_NEW', PaymentSetting::secret('gateway_secret_key'));
    }

    public function test_a_provider_other_than_manual_needs_all_three_keys_the_first_time(): void
    {
        $this->save(['public_key' => '', 'secret_key' => '', 'webhook_secret' => ''])
            ->assertSessionHasErrors(['public_key', 'secret_key', 'webhook_secret']);
        $this->assertNull(PaymentSetting::get('gateway_provider'));
    }

    public function test_keys_with_spaces_or_line_breaks_are_rejected(): void
    {
        $this->save(['secret_key' => "sk_test abc"])->assertSessionHasErrors('secret_key');
        $this->save(['public_key' => "pk_test_a\nb"])->assertSessionHasErrors('public_key');
    }

    public function test_live_mode_needs_confirmation_and_a_secure_connection(): void
    {
        $this->save(['mode' => 'live'], https: true)->assertSessionHasErrors('confirm_live');
        $this->save(['mode' => 'live', 'confirm_live' => '1'], https: false)->assertSessionHasErrors('mode');
        $this->assertNull(PaymentSetting::get('gateway_mode'));

        $this->save(['mode' => 'live', 'confirm_live' => '1'], https: true)->assertSessionHasNoErrors();
        $this->assertSame('live', PaymentSetting::get('gateway_mode'));
    }

    public function test_staying_in_live_mode_does_not_ask_for_confirmation_again(): void
    {
        $this->save(['mode' => 'live', 'confirm_live' => '1'], https: true);

        $this->save(['mode' => 'live', 'secret_key' => '', 'webhook_secret' => ''], https: true)->assertSessionHasNoErrors();
    }

    public function test_choosing_manual_removes_all_saved_keys(): void
    {
        $this->save();

        $this->save(['provider' => 'manual', 'public_key' => '', 'secret_key' => '', 'webhook_secret' => ''])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Gateway settings saved. Saved keys were removed because Manual needs none.');

        $this->assertNull(PaymentSetting::get('gateway_public_key'));
        $this->assertFalse(PaymentSetting::has('gateway_secret_key'));
        $this->assertFalse(PaymentSetting::has('gateway_webhook_secret'));
    }

    public function test_invalid_provider_and_mode_are_rejected(): void
    {
        $this->save(['provider' => 'bitcoin', 'mode' => 'yolo'])->assertSessionHasErrors(['provider', 'mode']);
    }

    public function test_a_failed_save_never_puts_secrets_back_in_the_session_or_the_form(): void
    {
        $response = $this->save(['mode' => 'nonsense']);

        $response->assertSessionHasErrors('mode');
        $old = session()->getOldInput();
        $this->assertArrayNotHasKey('secret_key', $old);
        $this->assertArrayNotHasKey('webhook_secret', $old);
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
        $this->assertStringNotContainsString(self::HOOK, json_encode(session()->all()));
    }

    public function test_the_audit_entry_says_what_changed_but_never_the_secrets(): void
    {
        if (! class_exists(\App\Support\Audit::class) || ! Schema::hasTable('audit_logs')) {
            $this->markTestSkipped("Anakha's audit helper (App\\Support\\Audit and the audit_logs table) is not merged yet.");
        }

        $this->save();

        $row = DB::table('audit_logs')->where('action', 'settings.gateway_updated')->first();
        $this->assertNotNull($row);
        $stored = json_encode(DB::table('audit_logs')->get());
        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertStringNotContainsString(self::HOOK, $stored);
        $this->assertStringContainsString('secret_key_changed', $stored);
    }

    public function test_gateway_config_returns_the_decrypted_values_for_the_adapter(): void
    {
        $this->save();

        $this->assertSame([
            'provider' => 'stripe', 'mode' => 'sandbox', 'public_key' => 'pk_test_PUBLIC789',
            'secret_key' => self::SECRET, 'webhook_secret' => self::HOOK,
        ], PaymentSetting::gatewayConfig());
    }

    public function test_an_unreadable_secret_gives_null_instead_of_an_error(): void
    {
        // e.g. APP_KEY was changed after the secret was saved
        PaymentSetting::put('gateway_secret_key', 'not-a-valid-encrypted-value');

        $this->assertNull(PaymentSetting::secret('gateway_secret_key'));
        $this->get(route('admin.settings.gateway'))->assertOk();
    }

    public function test_status_card_reflects_whether_keys_are_ready(): void
    {
        $this->get(route('admin.settings.gateway'))->assertSee('Keys ready');
        $this->assertFalse($this->get(route('admin.settings.gateway'))->viewData('hasSecret'));

        $this->save();
        $view = $this->get(route('admin.settings.gateway'));
        $this->assertTrue($view->viewData('hasSecret'));
        $this->assertTrue($view->viewData('hasWebhook'));
    }
}
