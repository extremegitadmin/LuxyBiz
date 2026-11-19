<?php

namespace App\Services\Cancellations;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Refund calculation (Day 15). Pure arithmetic on a booking and the saved rules (CancellationPolicy). It saves nothing
 * and calls no gateway: the cancellation screens (Days 16 to 20) show the result, a person may override it with a reason (Day 18).
 *
 * How the amount is worked out (all in whole cents, so there is no rounding drift):
 *   1. days   = whole days from the cancellation date to the departure date.
 *   2. percent = the tier for those days (after departure it is 0%).
 *   3. base   = what the rules say is refundable: tour price only, or the booking total; insurance counts only if refundable.
 *   4. entitlement = (base - fixed cancellation fee) x percent        <- what the customer is entitled to get back in total
 *   5. retained    = booking total - entitlement                      <- what LuxyBiz keeps of the price
 *   6. refund      = paid - retained, never below 0 and never more than paid
 * "paid" is what the customer has paid and not yet been refunded. So someone who only paid a deposit that is smaller than
 * what LuxyBiz keeps gets 0 (and the screen says so), and someone who paid in full gets exactly the entitlement.
 *
 * Travel credit instead of a refund = refund x credit percent, valid for the saved number of months.
 */
class RefundCalculator
{
    public function calculate(
        Booking $booking,
        ?CarbonInterface $cancelledOn = null,
        ?CancellationPolicy $policy = null,
        ?CarbonInterface $departsOn = null,
    ): RefundCalculation {
        $policy ??= CancellationPolicy::current();
        $cancelled = CarbonImmutable::instance($cancelledOn ?? now())->startOfDay();

        $departsOn ??= $booking->departure?->departs_on;
        $departs = $departsOn ? CarbonImmutable::instance($departsOn)->startOfDay() : null;

        $notes = [];
        $warnings = [];

        // 1 and 2: days and percent
        $days = null;
        $percent = 0;
        if ($departs === null) {
            $warnings[] = 'This booking has no departure date, so the refund percent cannot be worked out. Set it by hand with an override.';
        } else {
            $days = (int) $cancelled->diffInDays($departs, false);
            $percent = $policy->percentFor($days);
            $notes[] = $days < 0
                ? 'The departure date has passed, so the refund is 0%.'
                : "Cancelled $days day" . ($days === 1 ? '' : 's') . " before departure: $percent% refund.";
        }

        // 3: base
        $totalC = $this->cents($booking->total_amount);
        $subtotalC = $this->cents($booking->subtotal);
        $insuranceC = $this->cents($booking->insurance_amount);

        $baseC = $policy->base === 'tour_price'
            ? $subtotalC + ($policy->insuranceRefundable ? $insuranceC : 0)
            : $totalC - ($policy->insuranceRefundable ? 0 : $insuranceC);
        $baseC = max(0, min($baseC, $totalC));

        if ($insuranceC > 0 && ! $policy->insuranceRefundable) {
            $notes[] = 'Travel insurance (' . $this->money($insuranceC, $booking->currency) . ') is not refundable.';
        }
        if ($policy->base === 'tour_price') {
            $notes[] = 'The refund is worked out on the tour price only.';
        }

        // 4: entitlement
        $feeC = min($this->cents($policy->fixedFee), $baseC);
        if ($feeC > 0) {
            $notes[] = 'A fixed cancellation fee of ' . $this->money($feeC, $booking->currency) . ' is taken first.';
        }
        $entitlementC = $this->percentOf($baseC - $feeC, $percent);

        // 5 and 6: against what was really paid
        $paidC = max(0, $this->cents($booking->paidAmount()));
        $retainedC = max(0, $totalC - $entitlementC);
        $refundC = max(0, min($paidC, $paidC - $retainedC));

        if ($paidC === 0) {
            $notes[] = 'Nothing has been paid on this booking, so there is nothing to refund.';
        } elseif ($refundC === 0 && $entitlementC > 0) {
            $notes[] = 'The amount paid is not more than what LuxyBiz keeps under the rules, so the refund is 0.';
        } elseif ($paidC < $totalC && $refundC > 0) {
            $notes[] = 'The booking was only partly paid, so the refund is worked out on the amount actually paid.';
        }

        // Things a person must look at
        if (! $policy->confirmed) {
            $warnings[] = 'The cancellation rules are still placeholders and are not confirmed by LuxyBiz.';
        }
        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            $warnings[] = 'This booking is already ' . $booking->status . '.';
        }
        if ($paidC > $totalC) {
            $warnings[] = 'More was paid than the booking total. Check the payments before refunding.';
        }
        if ($booking->payments()->where('type', 'charge')->where('status', 'pending')->exists()) {
            $warnings[] = 'A payment on this booking is still pending. The refund is worked out on money already received.';
        }

        $credit = $this->percentOf($refundC, $policy->creditPercent);

        return new RefundCalculation(
            currency: strtoupper((string) $booking->currency),
            daysBefore: $days,
            percent: $percent,
            total: $totalC / 100,
            refundableBase: $baseC / 100,
            fixedFee: $feeC / 100,
            entitlement: $entitlementC / 100,
            paid: $paidC / 100,
            retained: $retainedC / 100,
            refund: $refundC / 100,
            credit: $credit / 100,
            creditValidUntil: $credit > 0 ? $cancelled->addMonthsNoOverflow($policy->creditMonths) : null,
            needsReview: $warnings !== [],
            policyConfirmed: $policy->confirmed,
            notes: $notes,
            warnings: $warnings,
            tiers: $policy->describeTiers(),
        );
    }

    private function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Whole cents x percent, rounded half up. */
    private function percentOf(int $cents, int $percent): int
    {
        return intdiv(max(0, $cents) * $percent + 50, 100);
    }

    private function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2) . ' ' . strtoupper($currency);
    }
}
