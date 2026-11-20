<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Providers\PaymentServiceProvider;
use App\Services\Payments\Gateway;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentSessionService;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\RazorpayWebhookHandler;
use App\Services\Payments\StripeGateway;
use App\Services\Payments\StripeWebhookHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The payment gateways are registered in the container by PaymentServiceProvider (Day 12). */
class PaymentServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_provider_is_registered_in_bootstrap_providers(): void
    {
        // If this fails, add App\Providers\PaymentServiceProvider::class to bootstrap/providers.php
        $this->assertArrayHasKey(PaymentServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_each_adapter_is_bound_in_the_container(): void
    {
        $this->assertTrue($this->app->bound('payments.gateway.razorpay'));
        $this->assertTrue($this->app->bound('payments.gateway.stripe'));
        $this->assertInstanceOf(RazorpayGateway::class, $this->app->make('payments.gateway.razorpay'));
        $this->assertInstanceOf(StripeGateway::class, $this->app->make('payments.gateway.stripe'));
        $this->assertInstanceOf(PaymentGateway::class, $this->app->make('payments.gateway.stripe'));
    }

    public function test_the_factory_gets_its_adapters_from_the_container(): void
    {
        $this->assertInstanceOf(RazorpayGateway::class, Gateway::forProvider('razorpay'));
        $this->assertInstanceOf(StripeGateway::class, Gateway::forProvider('stripe'));
        $this->assertInstanceOf(ManualGateway::class, Gateway::forProvider('paypal'));
    }

    public function test_settings_changed_in_the_database_are_picked_up_without_restarting(): void
    {
        // Bound, not a singleton: a new object with the current keys each time.
        PaymentSetting::putField('stripe', 'enabled', '1');
        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_ONE');
        $first = Gateway::forProvider('stripe');

        PaymentSetting::putField('stripe', 'secret_key', 'sk_test_TWO');
        $second = Gateway::forProvider('stripe');

        $this->assertNotSame($first, $second);
        $read = fn (StripeGateway $g) => (new \ReflectionProperty($g, 'config'))->getValue($g)['secret_key'];
        $this->assertSame('sk_test_ONE', $read($first));
        $this->assertSame('sk_test_TWO', $read($second));
    }

    public function test_a_test_can_swap_a_gateway_for_a_fake(): void
    {
        $fake = new class extends ManualGateway {
            public function name(): string
            {
                return 'fake';
            }
        };
        $this->app->bind('payments.gateway.razorpay', fn () => $fake);

        $this->assertSame($fake, Gateway::forProvider('razorpay'));
    }

    public function test_webhook_handlers_and_the_session_service_come_from_the_container(): void
    {
        $this->assertInstanceOf(StripeWebhookHandler::class, Gateway::webhookHandler('stripe'));
        $this->assertInstanceOf(RazorpayWebhookHandler::class, Gateway::webhookHandler('razorpay'));
        $this->assertNull(Gateway::webhookHandler('paypal'));
        $this->assertSame($this->app->make(StripeWebhookHandler::class), $this->app->make(StripeWebhookHandler::class));
        $this->assertSame($this->app->make(PaymentSessionService::class), $this->app->make(PaymentSessionService::class));
    }
}
