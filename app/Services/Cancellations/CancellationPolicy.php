<?php

namespace App\Services\Cancellations;

use App\Models\PaymentSetting;

/**
 * The cancellation and refund rules, read from and saved to the settings table (keys "cancel.*").
 * Day 14 only stores and shows them. Day 15 (refund calculation) reads them through current().
 *
 * Tiers: "cancelled at least N days before departure  ->  P% refunded".
 *   [60 => 90, 30 => 50, 0 => 0]  means 60+ days 90%, 30 to 59 days 50%, under 30 days 0%.
 * After the departure date the refund is always 0%.
 *
 * The values below are PLACEHOLDERS until the client confirms them (open client questions C1 to C10).
 * "confirmed" stays off until a person ticks it on the settings screen, and the screen shows a warning while it is off.
 */
final class CancellationPolicy
{
    public const BASES = [
        'tour_price' => 'Tour price only (before insurance and taxes)',
        'total' => 'Total of the booking (tour price, taxes and insurance)',
    ];

    /** @param list<array{min_days:int, percent:int}> $tiers  highest min_days first */
    public function __construct(
        public readonly array $tiers,
        public readonly string $base,
        public readonly bool $insuranceRefundable,
        public readonly float $fixedFee,
        public readonly int $creditPercent,
        public readonly int $creditMonths,
        public readonly int $refundDaysMin,
        public readonly int $refundDaysMax,
        public readonly bool $confirmed,
        public readonly ?string $confirmedAt,
    ) {
    }

    public static function defaults(): self
    {
        return new self(
            tiers: [['min_days' => 60, 'percent' => 90], ['min_days' => 30, 'percent' => 50], ['min_days' => 0, 'percent' => 0]],
            base: 'tour_price',
            insuranceRefundable: false,
            fixedFee: 0.0,
            creditPercent: 100,
            creditMonths: 12,
            refundDaysMin: 7,
            refundDaysMax: 10,
            confirmed: false,
            confirmedAt: null,
        );
    }

    public static function current(): self
    {
        $d = self::defaults();

        $tiers = json_decode((string) PaymentSetting::get('cancel.tiers'), true);
        $tiers = self::cleanTiers(is_array($tiers) ? $tiers : []) ?: $d->tiers;

        $base = (string) PaymentSetting::get('cancel.base', $d->base);

        return new self(
            tiers: $tiers,
            base: array_key_exists($base, self::BASES) ? $base : $d->base,
            insuranceRefundable: PaymentSetting::get('cancel.insurance_refundable', $d->insuranceRefundable ? '1' : '0') === '1',
            fixedFee: max(0, (float) PaymentSetting::get('cancel.fixed_fee', $d->fixedFee)),
            creditPercent: min(200, max(0, (int) PaymentSetting::get('cancel.credit_percent', $d->creditPercent))),
            creditMonths: max(1, (int) PaymentSetting::get('cancel.credit_months', $d->creditMonths)),
            refundDaysMin: max(0, (int) PaymentSetting::get('cancel.refund_days_min', $d->refundDaysMin)),
            refundDaysMax: max(0, (int) PaymentSetting::get('cancel.refund_days_max', $d->refundDaysMax)),
            confirmed: PaymentSetting::get('cancel.confirmed') === '1',
            confirmedAt: PaymentSetting::get('cancel.confirmed_at'),
        );
    }

    /** Save already validated values (see CancellationSettingsController). */
    public static function save(array $v): void
    {
        PaymentSetting::put('cancel.tiers', json_encode(self::cleanTiers($v['tiers'])));
        PaymentSetting::put('cancel.base', $v['base']);
        PaymentSetting::put('cancel.insurance_refundable', $v['insurance_refundable'] ? '1' : '0');
        PaymentSetting::put('cancel.fixed_fee', number_format((float) $v['fixed_fee'], 2, '.', ''));
        PaymentSetting::put('cancel.credit_percent', (string) (int) $v['credit_percent']);
        PaymentSetting::put('cancel.credit_months', (string) (int) $v['credit_months']);
        PaymentSetting::put('cancel.refund_days_min', (string) (int) $v['refund_days_min']);
        PaymentSetting::put('cancel.refund_days_max', (string) (int) $v['refund_days_max']);

        $wasConfirmed = PaymentSetting::get('cancel.confirmed') === '1';
        PaymentSetting::put('cancel.confirmed', $v['confirmed'] ? '1' : '0');
        if ($v['confirmed'] && ! $wasConfirmed) {
            PaymentSetting::put('cancel.confirmed_at', now()->toDateTimeString());
        }
        if (! $v['confirmed']) {
            PaymentSetting::forget('cancel.confirmed_at');
        }
    }

    /** The refund percent for a cancellation this many days before departure (negative = departure has passed). */
    public function percentFor(int $daysBeforeDeparture): int
    {
        if ($daysBeforeDeparture < 0) {
            return 0;
        }
        foreach ($this->tiers as $tier) {
            if ($daysBeforeDeparture >= $tier['min_days']) {
                return $tier['percent'];
            }
        }

        return 0;
    }

    /** A short readable summary for the screen and for messages: "60+ days: 90%". */
    public function describeTiers(): array
    {
        $out = [];
        $upper = null;
        foreach ($this->tiers as $t) {
            $label = $upper === null ? $t['min_days'] . '+ days before departure' : ($t['min_days'] === 0 ? 'Under ' . ($upper) . ' days' : $t['min_days'] . ' to ' . ($upper - 1) . ' days');
            $out[] = $label . ': ' . $t['percent'] . '%';
            $upper = $t['min_days'];
        }

        return $out;
    }

    /**
     * Normalise rows: whole numbers, no blank or repeated day counts, highest days first.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{min_days:int, percent:int}>
     */
    public static function cleanTiers(array $rows): array
    {
        $byDays = [];
        foreach ($rows as $r) {
            if (! is_array($r) || ! isset($r['min_days'], $r['percent']) || $r['min_days'] === '' || $r['percent'] === '') {
                continue;
            }
            if (! is_numeric($r['min_days']) || ! is_numeric($r['percent'])) {
                continue;
            }
            $byDays[(int) $r['min_days']] = ['min_days' => (int) $r['min_days'], 'percent' => (int) $r['percent']];
        }
        krsort($byDays);

        return array_values($byDays);
    }
}
