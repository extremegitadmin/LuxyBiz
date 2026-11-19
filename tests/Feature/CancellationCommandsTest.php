<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The two helper commands of Day 16: clean simulated test data, seed demo requests. */
class CancellationCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_simulated_dry_run_deletes_nothing(): void
    {
        $this->artisan('payments:sandbox-setup')->assertSuccessful();
        $b = Booking::factory()->create(['currency' => 'USD', 'total_amount' => 1000]);
        $this->artisan('payments:simulate', ['scenario' => 'paid', '--booking' => $b->id])->assertSuccessful();

        $this->artisan('payments:clean-simulated')->assertSuccessful();
        $this->assertSame(1, Payment::where('booking_id', $b->id)->count());
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_clean_simulated_removes_only_simulated_rows_and_fixes_the_booking(): void
    {
        $this->artisan('payments:sandbox-setup')->assertSuccessful();
        $b = Booking::factory()->create(['currency' => 'USD', 'subtotal' => 1450, 'insurance_amount' => 0, 'total_amount' => 1450]);
        $real = Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1450, 'currency' => 'USD', 'status' => 'succeeded', 'gateway_reference' => 'REF-REAL1']);
        $demoEvent = PaymentEvent::create(['gateway' => 'demo', 'external_id' => 'evt_demo_1', 'event' => 'payment.succeeded', 'outcome' => 'processed']);

        foreach (['paid', 'expired', 'refund'] as $s) {
            $this->artisan('payments:simulate', ['scenario' => $s, '--booking' => $b->id])->assertSuccessful();
        }
        $this->assertGreaterThan(2, Payment::where('booking_id', $b->id)->count());
        $this->assertSame('paid', $b->fresh()->payment_status);

        $this->artisan('payments:clean-simulated', ['--force' => true])->assertSuccessful();

        $this->assertSame([$real->id], Payment::where('booking_id', $b->id)->pluck('id')->all());
        $this->assertSame([$demoEvent->id], PaymentEvent::pluck('id')->all());
        $this->assertSame('paid', $b->fresh()->payment_status);   // recalculated from the one real payment
        $this->assertTrue(PaymentSetting::isEnabled('razorpay'));   // keys kept without --dummy-keys
    }

    public function test_clean_simulated_dummy_keys_only_when_asked_and_only_dummy(): void
    {
        $this->artisan('payments:sandbox-setup')->assertSuccessful();
        PaymentSetting::putField('stripe', 'public_key', 'pk_test_RealLookingKey99');   // a real-looking stripe key must survive
        $this->artisan('payments:clean-simulated', ['--force' => true, '--dummy-keys' => true])->assertSuccessful();

        $this->assertFalse(PaymentSetting::hasField('razorpay', 'public_key'));
        $this->assertSame('pk_test_RealLookingKey99', PaymentSetting::field('stripe', 'public_key'));
    }

    public function test_seed_demo_creates_pending_and_past_requests_and_is_repeatable(): void
    {
        $u = User::factory()->create();
        $waiting = Booking::factory()->create(['user_id' => $u->id, 'status' => 'cancellation_requested', 'tour_id' => 1]);
        $done = Booking::factory()->create(['user_id' => $u->id, 'status' => 'cancelled', 'payment_status' => 'refunded']);
        Payment::factory()->refund()->create(['booking_id' => $done->id, 'amount' => 700, 'currency' => 'USD', 'status' => 'succeeded']);

        // the demo departure needs a tour row in this test database
        \DB::table('tours')->insert(['id' => 1, 'slug' => 'demo-tour', 'title' => 'Demo', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('cancellations:seed-demo')->assertSuccessful();
        $this->assertSame('pending', CancellationRequest::where('booking_id', $waiting->id)->value('status'));
        $this->assertNotNull($waiting->fresh()->tour_departure_id);
        $this->assertSame('refunded', CancellationRequest::where('booking_id', $done->id)->value('status'));
        $this->assertEquals(700.00, (float) CancellationRequest::where('booking_id', $done->id)->value('final_refund'));

        $this->artisan('cancellations:seed-demo')->assertSuccessful();
        $this->assertSame(2, CancellationRequest::count());

        $this->artisan('cancellations:seed-demo', ['--remove' => true])->assertSuccessful();
        $this->assertSame(0, CancellationRequest::count());
    }
}
