<?php

namespace App\Providers;

use App\Models\PaymentSetting;
use App\Services\Payments\Gateway;
use App\Services\Payments\PaymentSessionService;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the payment gateways in Laravel's container.
 *
 *   payments.gateway.razorpay   a RazorpayGateway built with the saved Razorpay settings
 *   payments.gateway.stripe     a StripeGateway built with the saved Stripe settings
 *
 * The gateway bindings use bind(), not singleton(): the keys live in the database and staff can change them
 * at any time, so every resolve reads the current settings. A test can replace a gateway with a fake:
 *   $this->app->bind('payments.gateway.stripe', fn () => $fake);
 *
 * Which provider takes which currency is decided per booking by Gateway::forCurrency(), because it depends on
 * the booking and on settings saved in the database, which are not known when the app boots.
 *
 * Add to bootstrap/providers.php:  App\Providers\PaymentServiceProvider::class,
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (Gateway::ADAPTERS as $provider => $class) {
            $this->app->bind(Gateway::bindingFor($provider), fn () => new $class(PaymentSetting::config($provider)));
        }

        foreach (Gateway::WEBHOOKS as $class) {
            $this->app->singleton($class);   // handlers keep no state between requests
        }

        $this->app->singleton(PaymentSessionService::class);
    }
}
