<?php

namespace App\Console\Commands;

use App\Services\Cancellations\CancellationPolicy;
use Illuminate\Console\Command;

/** Puts the refund tiers the client sent on 23 Nov into the cancellation settings. Safe to run again. */
class CancellationApplyClientRules extends Command
{
    protected $signature = 'cancellation:apply-client-rules {--confirm : Also tick "confirmed" on the settings}';

    protected $description = 'Set the refund tiers to the client rules: 31+ days 90%, 15-30 75%, 3-14 50%, 2 days 25%, under 2 days 0%';

    public const TIERS = [
        ['min_days' => 31, 'percent' => 90],
        ['min_days' => 15, 'percent' => 75],
        ['min_days' => 3, 'percent' => 50],
        ['min_days' => 2, 'percent' => 25],
        ['min_days' => 0, 'percent' => 0],
    ];

    public function handle(): int
    {
        $current = CancellationPolicy::current();

        CancellationPolicy::save([
            'tiers' => self::TIERS,
            'base' => 'tour_price',
            'insurance_refundable' => $current->insuranceRefundable,   // not decided yet: left as it was
            'fixed_fee' => $current->fixedFee,
            'credit_percent' => $current->creditPercent,
            'credit_months' => $current->creditMonths,
            'refund_days_min' => 7,
            'refund_days_max' => 10,
            'confirmed' => $this->option('confirm') ? true : $current->confirmed,
        ]);

        foreach (CancellationPolicy::current()->describeTiers() as $line) {
            $this->line($line);
        }
        $this->info('Client refund rules saved.' . ($this->option('confirm') ? ' Marked as confirmed.' : ' Tick "confirmed" on the settings screen when ready.'));

        return self::SUCCESS;
    }
}
