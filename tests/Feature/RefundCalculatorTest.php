<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TourDeparture;
use App\Models\User;
use App\Services\Cancellations\CancellationPolicy;
use App\Services\Cancellations\RefundCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Refund calculation (Day 15). Every number below is worked out by hand in the comments. */
class RefundCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-12-01';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::TODAY);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function policy(array $o = []): CancellationPolicy
    {
        $d = CancellationPolicy::defaults();

        return new CancellationPolicy(
            tiers: $o['tiers'] ?? $d->tiers,
            base: $o['base'] ?? $d->base,
            insuranceRefundable: $o['insuranceRefundable'] ?? $d->insuranceRefundable,
            fixedFee: $o['fixedFee'] ?? $d->fixedFee,
            creditPercent: $o['creditPercent'] ?? $d->creditPercent,
            creditMonths: $o['creditMonths'] ?? $d->creditMonths,
            refundDaysMin: 7, refundDaysMax: 10,
            confirmed: $o['confirmed'] ?? true,
            confirmedAt: null,
        );
    }

    /** subtotal 5000 + insurance 200 + tax 300 = total 5500, paid in full by default. */
    private function booking(float $paid = 5500, array $o = []): Booking
    {
        $b = Booking::factory()->create($o + [
            'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'currency' => 'USD',
            'subtotal' => 5000, 'insurance_amount' => 200, 'total_amount' => 5500, 'insurance_selected' => true,
        ]);
        if ($paid > 0) {
            Payment::factory()->create(['booking_id' => $b->id, 'amount' => $paid, 'currency' => 'USD', 'status' => 'succeeded']);
        }

        return $b;
    }

    private function calc(Booking $b, int $daysToDeparture, ?CancellationPolicy $p = null)
    {
        return (new RefundCalculator())->calculate($b, now(), $p ?? $this->policy(), now()->addDays($daysToDeparture));
    }

    public function test_top_tier_tour_price_only_insurance_kept(): void
    {
        // 70 days -> 90%. base = tour price 5000. entitlement 4500. retained 5500-4500 = 1000. paid 5500 -> refund 4500.
        $r = $this->calc($this->booking(), 70);
        $this->assertSame(70, $r->daysBefore);
        $this->assertSame(90, $r->percent);
        $this->assertSame(5000.0, $r->refundableBase);
        $this->assertSame(4500.0, $r->entitlement);
        $this->assertSame(1000.0, $r->retained);
        $this->assertSame(4500.0, $r->refund);
    }

    public function test_middle_and_lowest_tiers(): void
    {
        $this->assertSame(2500.0, $this->calc($this->booking(), 45)->refund);   // 50% of 5000
        $this->assertSame(0.0, $this->calc($this->booking(), 10)->refund);      // 0%
    }

    /** @dataProvider boundaries */
    public function test_tier_boundaries(int $days, int $percent): void
    {
        $this->assertSame($percent, $this->calc($this->booking(), $days)->percent);
    }

    public static function boundaries(): array
    {
        return [[60, 90], [61, 90], [59, 50], [30, 50], [29, 0], [1, 0], [0, 0], [-1, 0], [-40, 0]];
    }

    public function test_departure_today_and_passed(): void
    {
        $this->assertSame(0.0, $this->calc($this->booking(), 0)->refund);
        $r = $this->calc($this->booking(), -2);
        $this->assertSame(0.0, $r->refund);
        $this->assertStringContainsString('passed', $r->notes[0]);
    }

    public function test_total_base_with_insurance_not_refundable(): void
    {
        // base total = 5500 - 200 insurance = 5300. 90% -> 4770. retained 5500-4770 = 730. refund 4770.
        $r = $this->calc($this->booking(), 70, $this->policy(['base' => 'total']));
        $this->assertSame(5300.0, $r->refundableBase);
        $this->assertSame(4770.0, $r->refund);
    }

    public function test_insurance_refundable(): void
    {
        // tour price + insurance = 5200 -> 90% = 4680
        $r = $this->calc($this->booking(), 70, $this->policy(['insuranceRefundable' => true]));
        $this->assertSame(5200.0, $r->refundableBase);
        $this->assertSame(4680.0, $r->refund);
        // total base, insurance refundable = whole 5500 -> 4950
        $this->assertSame(4950.0, $this->calc($this->booking(), 70, $this->policy(['base' => 'total', 'insuranceRefundable' => true]))->refund);
    }

    public function test_fixed_fee_is_taken_before_the_percent(): void
    {
        // (5000 - 100) x 90% = 4410
        $r = $this->calc($this->booking(), 70, $this->policy(['fixedFee' => 100]));
        $this->assertSame(100.0, $r->fixedFee);
        $this->assertSame(4410.0, $r->refund);
    }

    public function test_fee_larger_than_base_gives_zero(): void
    {
        $this->assertSame(0.0, $this->calc($this->booking(), 70, $this->policy(['fixedFee' => 99999]))->refund);
    }

    public function test_deposit_only_smaller_than_retained_gives_zero(): void
    {
        // paid 1000, entitlement 2500 (45 days) -> retained 3000 -> refund max(0, 1000-3000) = 0
        $r = $this->calc($this->booking(1000), 45);
        $this->assertSame(0.0, $r->refund);
        $this->assertSame(1000.0, $r->paid);
    }

    public function test_partial_payment_larger_than_retained_gets_the_rest(): void
    {
        // 70 days: entitlement 4500, retained 1000. paid 3000 -> refund 2000 (not more than paid).
        $this->assertSame(2000.0, $this->calc($this->booking(3000), 70)->refund);
    }

    public function test_nothing_paid(): void
    {
        $r = $this->calc($this->booking(0), 70);
        $this->assertSame(0.0, $r->refund);
        $this->assertContains('Nothing has been paid on this booking, so there is nothing to refund.', $r->notes);
    }

    public function test_refund_never_exceeds_paid(): void
    {
        // 100% tier, no insurance or tax: LuxyBiz keeps nothing, so the whole 100 paid comes back, not a cent more.
        $b = $this->booking(100, ['subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000, 'insurance_selected' => false]);
        $r = $this->calc($b, 90, $this->policy(['tiers' => [['min_days' => 0, 'percent' => 100]]]));
        $this->assertSame(100.0, $r->refund);
        // With 500 of insurance and tax that LuxyBiz keeps, a 100 payment is all kept.
        $this->assertSame(0.0, $this->calc($this->booking(100), 90, $this->policy(['tiers' => [['min_days' => 0, 'percent' => 100]]]))->refund);
    }

    public function test_earlier_refunds_are_not_refunded_twice(): void
    {
        $b = $this->booking();
        Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 1000, 'currency' => 'USD', 'status' => 'succeeded']);
        // paid net 4500, entitlement 4500, retained 1000 -> refund 3500
        $this->assertSame(3500.0, $this->calc($b, 70)->refund);
    }

    public function test_failed_and_pending_payments_do_not_count(): void
    {
        $b = $this->booking(1000);
        Payment::factory()->failed()->create(['booking_id' => $b->id, 'amount' => 4500, 'currency' => 'USD']);
        Payment::factory()->pending()->create(['booking_id' => $b->id, 'amount' => 500, 'currency' => 'USD']);
        $r = $this->calc($b, 70);
        $this->assertSame(1000.0, $r->paid);
        $this->assertTrue($r->needsReview);
        $this->assertStringContainsString('still pending', implode(' ', $r->warnings));
    }

    public function test_rounding_is_half_up_to_the_cent(): void
    {
        // base 333.33, 33% -> 109.9989 -> 110.00 ; paid 333.33 (total 333.33, no insurance)
        $b = Booking::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'subtotal' => 333.33, 'insurance_amount' => 0, 'total_amount' => 333.33, 'insurance_selected' => false]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 333.33, 'currency' => 'USD', 'status' => 'succeeded']);
        $r = $this->calc($b, 5, $this->policy(['tiers' => [['min_days' => 0, 'percent' => 33]]]));
        $this->assertSame(110.0, $r->entitlement);
        $this->assertSame(110.0, $r->refund);
    }

    public function test_credit_amount_and_expiry(): void
    {
        $r = $this->calc($this->booking(), 70, $this->policy(['creditPercent' => 110, 'creditMonths' => 12]));
        $this->assertSame(4950.0, $r->credit);                       // 4500 x 110%
        $this->assertSame('2027-12-01', $r->creditValidUntil->toDateString());
        $this->assertNull($this->calc($this->booking(), 10)->creditValidUntil);   // no refund, no credit
    }

    public function test_no_departure_date_needs_review(): void
    {
        $r = (new RefundCalculator())->calculate($this->booking(), now(), $this->policy());
        $this->assertNull($r->daysBefore);
        $this->assertTrue($r->needsReview);
        $this->assertSame(0.0, $r->refund);
        $this->assertStringContainsString('no departure date', $r->warnings[0]);
    }

    public function test_departure_date_is_read_from_the_booking(): void
    {
        $b = $this->booking();
        $b->setRelation('departure', new TourDeparture(['departs_on' => now()->addDays(65)]));
        $r = (new RefundCalculator())->calculate($b, now(), $this->policy());
        $this->assertSame(65, $r->daysBefore);
        $this->assertSame(90, $r->percent);
    }

    public function test_time_of_day_does_not_change_the_days(): void
    {
        $b = $this->booking();
        $r = (new RefundCalculator())->calculate($b, CarbonImmutable::parse('2026-12-01 23:59:00'), $this->policy(), CarbonImmutable::parse('2027-01-30 00:01:00'));
        $this->assertSame(60, $r->daysBefore);
        $this->assertSame(90, $r->percent);
    }

    public function test_placeholder_rules_and_finished_bookings_are_flagged(): void
    {
        $r = $this->calc($this->booking(5500, ['status' => 'cancelled']), 70, $this->policy(['confirmed' => false]));
        $this->assertTrue($r->needsReview);
        $this->assertFalse($r->policyConfirmed);
        $this->assertCount(2, $r->warnings);
    }

    public function test_clean_case_needs_no_review_and_snapshot_is_complete(): void
    {
        $r = $this->calc($this->booking(), 70);
        $this->assertFalse($r->needsReview);
        $a = $r->toArray();
        foreach (['currency', 'days_before', 'percent', 'total', 'refundable_base', 'entitlement', 'paid', 'retained', 'refund', 'credit', 'notes', 'warnings', 'tiers'] as $k) {
            $this->assertArrayHasKey($k, $a);
        }
        $this->assertSame('USD', $a['currency']);
        $this->assertJson(json_encode($a));
    }

    public function test_uses_saved_rules_by_default(): void
    {
        // saved defaults are placeholders -> same maths, flagged as unconfirmed
        $b = $this->booking();
        $r = (new RefundCalculator())->calculate($b, now(), null, now()->addDays(70));
        $this->assertSame(4500.0, $r->refund);
        $this->assertFalse($r->policyConfirmed);
    }
}
