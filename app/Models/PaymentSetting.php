<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings for payments. Plain values with get()/put(); secrets with secret()/putSecret().
 *
 * Every payment provider has its own settings, stored as  gw.<provider>.<field>
 * (enabled, mode, public_key, secret_key, webhook_secret). Two routing settings choose which provider
 * takes which currency:  route.inr  and  route.other.
 *
 * A secret is encrypted with the app key. If APP_KEY ever changes, saved secrets cannot be read
 * any more: secret() then returns null and the screen shows "not saved", so you re-enter them.
 */
class PaymentSetting extends Model
{
    protected $guarded = [];

    /** Providers the screen shows. Only the ones with an adapter (see Gateway::ADAPTERS) can be switched on. */
    public const PROVIDERS = [
        'razorpay' => 'Razorpay',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
    ];

    /** Fields of one provider that are encrypted. Never include these in logs, audit entries, flashes or views. */
    public const SECRET_FIELDS = ['secret_key', 'webhook_secret'];

    public const ROUTE_DEFAULTS = ['inr' => 'razorpay', 'other' => 'stripe'];

    // ---- generic values ---------------------------------------------------

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

    // ---- one provider -----------------------------------------------------

    public static function key(string $provider, string $field): string
    {
        return 'gw.' . $provider . '.' . $field;
    }

    public static function field(string $provider, string $field, mixed $default = null): mixed
    {
        return static::get(static::key($provider, $field), $default);
    }

    public static function putField(string $provider, string $field, ?string $value): void
    {
        in_array($field, self::SECRET_FIELDS, true)
            ? static::putSecret(static::key($provider, $field), $value)
            : static::put(static::key($provider, $field), $value);
    }

    public static function hasField(string $provider, string $field): bool
    {
        return static::has(static::key($provider, $field));
    }

    public static function isEnabled(string $provider): bool
    {
        return static::field($provider, 'enabled') === '1';
    }

    /** Removes every saved value of one provider (keys included). */
    public static function forgetProvider(string $provider): void
    {
        static::query()->where('key', 'like', 'gw.' . $provider . '.%')->delete();
    }

    /**
     * Everything an adapter needs. Contains secrets: pass it to the adapter,
     * never to a view, a log line or an audit entry.
     */
    public static function config(string $provider): array
    {
        return [
            'provider' => $provider,
            'enabled' => static::isEnabled($provider),
            'mode' => static::field($provider, 'mode', 'sandbox') === 'live' ? 'live' : 'sandbox',
            'public_key' => static::field($provider, 'public_key'),
            'secret_key' => static::secret(static::key($provider, 'secret_key')),
            'webhook_secret' => static::secret(static::key($provider, 'webhook_secret')),
        ];
    }

    // ---- routing ----------------------------------------------------------

    /** Which provider the settings choose for a currency (it may not be switched on yet). */
    public static function routedProvider(string $currency): string
    {
        $slot = strtoupper($currency) === 'INR' ? 'inr' : 'other';

        return (string) static::get('route.' . $slot, self::ROUTE_DEFAULTS[$slot]);
    }

    /** The address the provider must call. */
    public static function webhookUrl(string $provider): string
    {
        return url('/webhooks/payments/' . $provider);
    }
}
