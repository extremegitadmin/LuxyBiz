<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings for payments. Plain values with get()/put(); secrets with secret()/putSecret().
 * A secret is encrypted with the app key. If APP_KEY ever changes, saved secrets cannot be read
 * any more: secret() then returns null and the gateway screen shows "not saved", so you re-enter them.
 */
class PaymentSetting extends Model
{
    protected $guarded = [];

    /** Never include these in logs, audit entries, flashes or views. */
    public const SECRET_KEYS = ['gateway_secret_key', 'gateway_webhook_secret'];

    /** The payment providers the screen offers. Adapters are added when the client picks one. */
    public const PROVIDERS = [
        'manual' => 'Manual (offline payments only)',
        'stripe' => 'Stripe',
        'razorpay' => 'Razorpay',
        'paypal' => 'PayPal',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }

    /** True when a (non-empty) value is saved. Does not decrypt. */
    public static function has(string $key): bool
    {
        return filled(static::get($key));
    }

    public static function putSecret(string $key, ?string $plain): void
    {
        static::put($key, filled($plain) ? Crypt::encryptString($plain) : null);
    }

    public static function secret(string $key): ?string
    {
        $stored = static::get($key);
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Everything a gateway adapter needs (used from Day 11). Contains secrets: pass it to the adapter,
     * never to a view, a log line or an audit entry.
     */
    public static function gatewayConfig(): array
    {
        return [
            'provider' => (string) static::get('gateway_provider', 'manual'),
            'mode' => (string) static::get('gateway_mode', 'sandbox'),
            'public_key' => static::get('gateway_public_key'),
            'secret_key' => static::secret('gateway_secret_key'),
            'webhook_secret' => static::secret('gateway_webhook_secret'),
        ];
    }

    /** What the gateway must call. Day 12 adds the route that answers here. */
    public static function webhookUrl(): string
    {
        return url('/webhooks/payments');
    }
}
