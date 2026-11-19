<?php

namespace App\Services\Cancellations;

use Carbon\CarbonImmutable;

/**
 * The answer of one refund calculation. Read only. All money is in the booking's currency.
 * toArray() is safe to save as a snapshot on a cancellation request (Day 16), so later changes to the rules
 * do not change what was shown to the customer.
 */
final class RefundCalculation
{
    /**
     * @param  list<string>  $notes  plain sentences for the staff screen, in order
     * @param  list<string>  $warnings  things a person must look at before approving
     * @param  list<string>  $tiers  the rules used, e.g. "60+ days before departure: 90%"
     */
    public function __construct(
        public readonly string $currency,
        public readonly ?int $daysBefore,
        public readonly int $percent,
        public readonly float $total,
        public readonly float $refundableBase,
        public readonly float $fixedFee,
        public readonly float $entitlement,
        public readonly float $paid,
        public readonly float $retained,
        public readonly float $refund,
        public readonly float $credit,
        public readonly ?CarbonImmutable $creditValidUntil,
        public readonly bool $needsReview,
        public readonly bool $policyConfirmed,
        public readonly array $notes,
        public readonly array $warnings,
        public readonly array $tiers,
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'days_before' => $this->daysBefore,
            'percent' => $this->percent,
            'total' => $this->total,
            'refundable_base' => $this->refundableBase,
            'fixed_fee' => $this->fixedFee,
            'entitlement' => $this->entitlement,
            'paid' => $this->paid,
            'retained' => $this->retained,
            'refund' => $this->refund,
            'credit' => $this->credit,
            'credit_valid_until' => $this->creditValidUntil?->toDateString(),
            'needs_review' => $this->needsReview,
            'policy_confirmed' => $this->policyConfirmed,
            'notes' => $this->notes,
            'warnings' => $this->warnings,
            'tiers' => $this->tiers,
        ];
    }
}
