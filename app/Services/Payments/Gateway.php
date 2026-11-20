<?php

namespace App\Services\Payments;

use App\Models\PaymentSetting;

/**
 * Finds the right provider adapter. The adapters themselves are registered in the container by
 * App\Providers\PaymentServiceProvider. Each provider has its own settings; the settings also say which provider
 * takes INR and which takes every other currency (for example USD).
 */
class Gateway
{
    /** Providers that have a working adapter. PayPal is added when the client chooses it. */
    public const ADAPTERS = [
        'razorpay' => RazorpayGateway::class,
        'stripe' => StripeGateway::class,
    ];

    /** The class that reads each provider's webhooks. */
    public const WEBHOOKS = [
        'razorpay' => RazorpayWebhookHandler::class,
        'stripe' => StripeWebhookHandler::class,
    ];

    /** @return list<string> */
    public static function providers(): array
    {
        return array_keys(self::ADAPTERS);
    }

    public static function hasAdapter(string $provider): bool
    {
        return isset(self::ADAPTERS[$provider]);
    }

    /** The container name that App\Providers\PaymentServiceProvider registers each adapter under. */
    public static function bindingFor(string $provider): string
    {
        return 'payments.gateway.' . $provider;
    }

    /** Asks the container for the adapter (so a test can swap in a fake). Manual when there is no adapter. */
    public static function forProvider(string $provider): PaymentGateway
    {
        if (! isset(self::ADAPTERS[$provider])) {
            return new ManualGateway();
        }
        if (! app()->bound(self::bindingFor($provider))) {
            throw new \RuntimeException('PaymentServiceProvider is not registered. Add App\\Providers\\PaymentServiceProvider::class to bootstrap/providers.php.');
        }

        return app(self::bindingFor($provider));
    }

    public static function webhookHandler(string $provider): ?WebhookHandler
    {
        $class = self::WEBHOOKS[$provider] ?? null;

        return $class ? app($class) : null;
    }

    /** Switched on AND a secret key is saved. */
    public static function isReady(string $provider): bool
    {
        return self::hasAdapter($provider) && PaymentSetting::isEnabled($provider) && PaymentSetting::hasField($provider, 'secret_key');
    }

    /** The provider that takes this currency, or null when the chosen one is not ready. */
    public static function providerFor(string $currency): ?string
    {
        $provider = PaymentSetting::routedProvider($currency);

        return self::isReady($provider) ? $provider : null;
    }

    public static function forCurrency(string $currency): PaymentGateway
    {
        $provider = self::providerFor($currency);

        return $provider ? self::forProvider($provider) : new ManualGateway();
    }

    /** True when staff can create an online payment page for a booking in this currency. */
    public static function isOnlineFor(string $currency): bool
    {
        return self::providerFor($currency) !== null;
    }
}
