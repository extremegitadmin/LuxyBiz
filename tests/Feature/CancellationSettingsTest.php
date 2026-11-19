<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Services\Cancellations\CancellationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Cancellation rules settings (M2, Day 14). */
class CancellationSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function payload(array $o = []): array
    {
        return $o + [
            'tiers' => [['min_days' => 60, 'percent' => 80], ['min_days' => 30, 'percent' => 40], ['min_days' => 0, 'percent' => 0]],
            'base' => 'total', 'insurance_refundable' => '1', 'fixed_fee' => '25.50',
            'credit_percent' => 110, 'credit_months' => 18, 'refund_days_min' => 5, 'refund_days_max' => 14,
        ];
    }

    public function test_defaults_are_the_documented_placeholders(): void
    {
        $p = CancellationPolicy::current();
        $this->assertFalse($p->confirmed);
        $this->assertSame(90, $p->percentFor(60));
        $this->assertSame(90, $p->percentFor(100));
        $this->assertSame(50, $p->percentFor(59));
        $this->assertSame(50, $p->percentFor(30));
        $this->assertSame(0, $p->percentFor(29));
        $this->assertSame(0, $p->percentFor(0));
        $this->assertSame(0, $p->percentFor(-3));
        $this->assertFalse($p->insuranceRefundable);
        $this->assertSame('tour_price', $p->base);
    }

    public function test_page_shows_placeholder_warning_until_confirmed(): void
    {
        $this->signInAsSuperAdmin();
        $this->get(route('admin.settings.cancellation'))->assertOk()->assertSee('Placeholder rules')->assertSee('60+ days before departure: 90%');
    }

    public function test_saving_stores_values_and_confirms(): void
    {
        $this->signInAsSuperAdmin();
        $this->put(route('admin.settings.cancellation.update'), $this->payload(['confirmed' => '1']))
            ->assertRedirect(route('admin.settings.cancellation'))->assertSessionHas('success');

        $p = CancellationPolicy::current();
        $this->assertSame(80, $p->percentFor(70));
        $this->assertSame(40, $p->percentFor(45));
        $this->assertSame('total', $p->base);
        $this->assertTrue($p->insuranceRefundable);
        $this->assertSame(25.5, $p->fixedFee);
        $this->assertSame(110, $p->creditPercent);
        $this->assertSame(18, $p->creditMonths);
        $this->assertSame([5, 14], [$p->refundDaysMin, $p->refundDaysMax]);
        $this->assertTrue($p->confirmed);
        $this->assertNotNull($p->confirmedAt);

        $this->get(route('admin.settings.cancellation'))->assertDontSee('Placeholder rules')->assertSee('Rules confirmed');
    }

    public function test_unticking_confirmed_brings_the_warning_back(): void
    {
        $this->signInAsSuperAdmin();
        $this->put(route('admin.settings.cancellation.update'), $this->payload(['confirmed' => '1']));
        $this->put(route('admin.settings.cancellation.update'), $this->payload());
        $this->assertFalse(CancellationPolicy::current()->confirmed);
        $this->assertNull(PaymentSetting::get('cancel.confirmed_at'));
    }

    public function test_tier_rows_in_any_order_are_sorted(): void
    {
        $this->signInAsSuperAdmin();
        $this->put(route('admin.settings.cancellation.update'), $this->payload(['tiers' => [['min_days' => 0, 'percent' => 0], ['min_days' => 45, 'percent' => 70], ['min_days' => 90, 'percent' => 100]]]))->assertSessionHasNoErrors();
        $this->assertSame(100, CancellationPolicy::current()->percentFor(95));
        $this->assertSame(70, CancellationPolicy::current()->percentFor(50));
    }

    /** @dataProvider badTiers */
    public function test_bad_tiers_are_refused_and_nothing_is_saved(array $tiers): void
    {
        $this->signInAsSuperAdmin();
        $this->from(route('admin.settings.cancellation'))->put(route('admin.settings.cancellation.update'), $this->payload(['tiers' => $tiers]))->assertSessionHasErrors();
        $this->assertNull(PaymentSetting::get('cancel.tiers'));
    }

    public static function badTiers(): array
    {
        return [
            'no zero tier' => [[['min_days' => 60, 'percent' => 90], ['min_days' => 30, 'percent' => 50]]],
            'more notice refunds less' => [[['min_days' => 60, 'percent' => 40], ['min_days' => 30, 'percent' => 50], ['min_days' => 0, 'percent' => 0]]],
            'same days twice' => [[['min_days' => 30, 'percent' => 50], ['min_days' => 30, 'percent' => 40], ['min_days' => 0, 'percent' => 0]]],
            'percent over 100' => [[['min_days' => 30, 'percent' => 120], ['min_days' => 0, 'percent' => 0]]],
            'negative days' => [[['min_days' => -5, 'percent' => 50], ['min_days' => 0, 'percent' => 0]]],
            'text' => [[['min_days' => 'abc', 'percent' => 50], ['min_days' => 0, 'percent' => 0]]],
            'all blank' => [[['min_days' => '', 'percent' => '']]],
        ];
    }

    public function test_other_fields_are_validated(): void
    {
        $this->signInAsSuperAdmin();
        foreach ([['base' => 'bogus'], ['fixed_fee' => '-1'], ['credit_months' => 0], ['refund_days_min' => 10, 'refund_days_max' => 5]] as $bad) {
            $this->from(route('admin.settings.cancellation'))->put(route('admin.settings.cancellation.update'), $this->payload($bad))->assertSessionHasErrors();
        }
        $this->assertNull(PaymentSetting::get('cancel.base'));
    }

    public function test_try_it_uses_saved_rules(): void
    {
        $this->signInAsSuperAdmin();
        $this->get(route('admin.settings.cancellation', ['try_days' => 45]))->assertSee('refund is')->assertSee('50%');
    }

    public function test_corrupt_saved_tiers_fall_back_to_defaults(): void
    {
        PaymentSetting::put('cancel.tiers', '{not json');
        $this->assertSame(90, CancellationPolicy::current()->percentFor(70));
    }

    public function test_view_only_user_can_see_but_not_change(): void
    {
        $this->actingAs($this->userWith(['admin.settings.cancellation.view']));
        $this->get(route('admin.settings.cancellation'))->assertOk()->assertDontSee('Save cancellation rules');
        $this->put(route('admin.settings.cancellation.update'), $this->payload())->assertForbidden();
        $this->assertNull(PaymentSetting::get('cancel.tiers'));
    }

    public function test_user_without_permission_is_refused(): void
    {
        $this->actingAs($this->userWith(['admin.payments.view']));
        $this->get(route('admin.settings.cancellation'))->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.settings.cancellation'))->assertRedirect();
    }
}
