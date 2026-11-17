<?php

namespace App\Services\Payments;

use App\Models\PaymentSetting;

/** Picks the adapter chosen under Settings, Payment gateway. */
class Gateway
{
    /** Providers that have a working adapter. Razorpay and PayPal are added when the client picks them. */
    private const ADAPTERS = ['stripe' => StripeGateway::class];

    public static function hasAdapter(string $provider): bool
    {
        return $provider === 'manual' || isset(self::ADAPTERS[$provider]);
    }

    public static function resolve(): PaymentGateway
    {
        $config = PaymentSetting::gatewayConfig();
        $class = self::ADAPTERS[$config['provider']] ?? null;

        return $class ? new $class($config) : new ManualGateway();
    }

    /** True when staff can create online payment pages: an adapter exists and its secret key is saved. */
    public static function isOnline(): bool
    {
        return self::resolve()->supportsPaymentSessions() && PaymentSetting::has('gateway_secret_key');
    }
}
