<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per webhook the payment gateway sends us. This is a log: rows are only ever added.
 * Always create rows with PaymentEvent::record() so secrets and card data are stripped first.
 */
class PaymentEvent extends Model
{

    public const OUTCOMES = ['received', 'processed', 'ignored', 'failed'];

    /** Any payload key containing one of these words is replaced, at any depth. */
    private const SENSITIVE = ['card_number', 'number', 'cvc', 'cvv', 'secret', 'token', 'authorization', 'signature', 'password', 'api_key'];

    public const REDACTED = '[removed]';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Create a log row with the payload cleaned. Returns the existing row if the gateway sent the same event twice. */
    public static function record(array $attrs, ?array $payload = null): self
    {
        $attrs['payload'] = $payload === null ? null : self::clean($payload);

        if (! empty($attrs['external_id'])) {
            return static::firstOrCreate(['external_id' => $attrs['external_id']], $attrs);
        }

        return static::create($attrs);
    }

    public static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            $sensitive = collect(self::SENSITIVE)->contains(fn ($word) => str_contains($lower, $word));

            if ($sensitive) {
                $out[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $out[$key] = self::clean($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** Pretty JSON for the screen, cleaned again on the way out and cut to a safe length. */
    public function prettyPayload(int $limit = 4000): string
    {
        $json = json_encode(self::clean((array) $this->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        return mb_strlen($json) > $limit ? mb_substr($json, 0, $limit) . "\n... (cut)" : $json;
    }
}
