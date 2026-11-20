<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Day 13: the simulate command plays the provider end to end, for both gateways. */
class PaymentSimulateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function setup13(string $currency): Booking
    {
        $this->artisan('payments:sandbox-setup')->assertSuccessful();

        return Booking::factory()->create(['status' => 'confirmed', 'total_amount' => 10000, 'currency' => $currency, 'payment_status' => 'unpaid']);
    }

    public function test_razorpay_paid_marks_payment_and_booking(): void
    {
        $b = $this->setup13('INR');
        $this->artisan('payments:simulate', ['scenario' => 'paid'])->assertSuccessful();

        $p = Payment::where('booking_id', $b->id)->where('type', 'charge')->first();
        $this->assertSame('razorpay', $p->gateway);
        $this->assertSame('succeeded', $p->status);
        $this->assertSame('paid', $b->fresh()->payment_status);
    }

    public function test_stripe_paid_for_usd(): void
    {
        $b = $this->setup13('USD');
        $this->artisan('payments:simulate', ['scenario' => 'paid'])->assertSuccessful();

        $p = Payment::where('booking_id', $b->id)->first();
        $this->assertSame('stripe', $p->gateway);
        $this->assertSame('succeeded', $p->status);
    }

    /** @dataProvider failures */
    public function test_failed_scenarios_never_book_money(string $scenario, string $provider): void
    {
        $b = $this->setup13($provider === 'razorpay' ? 'INR' : 'USD');
        $this->artisan('payments:simulate', ['scenario' => $scenario])->assertSuccessful();

        $p = Payment::where('booking_id', $b->id)->where('type', 'charge')->first();
        $this->assertNotSame('succeeded', $p->status);
        $this->assertSame('unpaid', $b->fresh()->payment_status);
    }

    public static function failures(): array
    {
        return [
            ['expired', 'razorpay'], ['cancelled', 'razorpay'], ['wrong-amount', 'razorpay'], ['bad-signature', 'razorpay'],
            ['expired', 'stripe'], ['wrong-amount', 'stripe'], ['bad-signature', 'stripe'],
        ];
    }

    public function test_expired_marks_failed_with_code(): void
    {
        $b = $this->setup13('INR');
        $this->artisan('payments:simulate', ['scenario' => 'expired'])->assertSuccessful();
        $p = Payment::where('booking_id', $b->id)->first();
        $this->assertSame('failed', $p->status);
        $this->assertSame('session_expired', $p->failure_code);
    }

    public function test_duplicate_event_changes_payment_only_once(): void
    {
        $b = $this->setup13('INR');
        $this->artisan('payments:simulate', ['scenario' => 'duplicate'])->assertSuccessful();
        $this->assertSame(1, Payment::where('booking_id', $b->id)->where('type', 'charge')->where('status', 'succeeded')->count());
    }

    /** @dataProvider providers */
    public function test_refund_creates_one_refund_row(string $currency): void
    {
        $b = $this->setup13($currency);
        $this->artisan('payments:simulate', ['scenario' => 'refund'])->assertSuccessful();
        $refund = Payment::where('booking_id', $b->id)->where('type', 'refund')->first();
        $this->assertNotNull($refund);
        $this->assertSame('succeeded', $refund->status);
        $this->assertEquals(5000.00, (float) $refund->amount);
    }

    public function test_refund_failed_is_recorded_as_failed(): void
    {
        $b = $this->setup13('INR');
        $this->artisan('payments:simulate', ['scenario' => 'refund-failed'])->assertSuccessful();
        $this->assertSame('failed', Payment::where('booking_id', $b->id)->where('type', 'refund')->first()->status);
    }

    public static function providers(): array
    {
        return [['INR'], ['USD']];
    }

    public function test_refused_in_live_mode(): void
    {
        $b = $this->setup13('INR');
        PaymentSetting::putField('razorpay', 'mode', 'live');
        $this->artisan('payments:simulate', ['scenario' => 'paid'])->assertFailed();
        $this->assertSame(0, Payment::where('booking_id', $b->id)->count());
    }

    public function test_setup_does_not_overwrite_real_looking_keys(): void
    {
        PaymentSetting::putField('razorpay', 'secret_key', 'RealLookingSecretValue123');
        $this->artisan('payments:sandbox-setup')->assertSuccessful();
        $this->assertSame('RealLookingSecretValue123', PaymentSetting::config('razorpay')['secret_key']);
    }
}
