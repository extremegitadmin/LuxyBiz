<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Payment detail (E5): gateway reference, status timeline (Day 8). */
class PaymentDetailTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    private function booking(array $attrs = []): Booking
    {
        return Booking::factory()->create($attrs + ['user_id' => User::factory()->create(['name' => 'Olivia Bennett'])->id, 'total_amount' => 1000]);
    }

    public function test_detail_shows_amount_reference_booking_and_customer(): void
    {
        $b = $this->booking();
        $p = Payment::factory()->create(['booking_id' => $b->id, 'amount' => 600, 'gateway_reference' => 'REF-DETAIL1', 'method' => 'bank_transfer', 'note' => 'Deposit']);

        $this->get(route('admin.payments.show', $p))
            ->assertOk()
            ->assertSee('USD 600.00')
            ->assertSee('REF-DETAIL1')
            ->assertSee($b->reference)
            ->assertSee('Olivia Bennett')
            ->assertSee('Bank transfer')
            ->assertSee('Deposit')
            ->assertSee('Payment received')
            ->assertSee(route('admin.bookings.show', $b), false);
    }

    public function test_failed_payment_shows_failure_reason_in_timeline(): void
    {
        $p = Payment::factory()->failed()->create(['booking_id' => $this->booking()->id]);

        $this->get(route('admin.payments.show', $p))
            ->assertOk()->assertSee('Payment failed')->assertSee('Reason: card declined');
    }

    public function test_pending_payment_shows_waiting_step(): void
    {
        $p = Payment::factory()->pending()->create(['booking_id' => $this->booking()->id]);

        $this->get(route('admin.payments.show', $p))
            ->assertOk()->assertSee('Waiting for the gateway')->assertSee('Not yet');
    }

    public function test_refund_is_labelled_and_negative(): void
    {
        $p = Payment::factory()->refund()->create(['booking_id' => $this->booking()->id, 'amount' => 250]);

        $this->get(route('admin.payments.show', $p))
            ->assertOk()->assertSee('-USD 250.00')->assertSee('Refund recorded')->assertSee('Refund completed');
    }

    public function test_timeline_names_the_staff_member_who_recorded_it(): void
    {
        $staff = User::factory()->create(['name' => 'Priya Staff']);
        $p = Payment::factory()->create(['booking_id' => $this->booking()->id, 'recorded_by' => $staff->id]);

        $this->get(route('admin.payments.show', $p))->assertSee('by Priya Staff');
    }

    public function test_other_payments_on_the_booking_and_net_paid_are_listed(): void
    {
        $b = $this->booking(['total_amount' => 1000]);
        $main = Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000]);
        $refund = Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 300]);
        $other = Payment::factory()->create(['booking_id' => $this->booking()->id, 'amount' => 777]);

        $this->get(route('admin.payments.show', $main))
            ->assertOk()
            ->assertSee(route('admin.payments.show', $refund), false)
            ->assertSee('-USD 300.00')
            ->assertSee('USD 700.00')                         // net paid
            ->assertDontSee(route('admin.payments.show', $other), false);
    }

    public function test_no_card_data_is_shown_even_if_it_exists_in_the_row(): void
    {
        $p = Payment::factory()->create(['booking_id' => $this->booking()->id, 'card_brand' => 'visa', 'card_last4' => '4242']);

        $this->get(route('admin.payments.show', $p))
            ->assertOk()->assertDontSee('4242')->assertDontSee('Visa');
    }

    public function test_refund_history_lists_refunds_with_a_total_of_succeeded_ones(): void
    {
        $b = $this->booking(['total_amount' => 1000]);
        $charge = Payment::factory()->create(['booking_id' => $b->id, 'amount' => 1000]);
        $r1 = Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 300, 'note' => 'Partial goodwill refund']);
        Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 100]);
        Payment::factory()->refund()->create(['booking_id' => $b->id, 'amount' => 999, 'status' => 'failed']);

        $this->get(route('admin.payments.show', $charge))
            ->assertOk()
            ->assertSee('Refund history')
            ->assertSee(route('admin.payments.show', $r1), false)
            ->assertSee('Partial goodwill refund')
            ->assertSee('USD 400.00')            // 300 + 100, the failed 999 is not counted
            ->assertDontSee('USD 1,399.00');
    }

    public function test_refund_history_says_so_when_there_are_none(): void
    {
        $p = Payment::factory()->create(['booking_id' => $this->booking()->id]);

        $this->get(route('admin.payments.show', $p))->assertSee('No refunds on this booking.');
    }

    public function test_notes_are_escaped(): void
    {
        $p = Payment::factory()->create(['booking_id' => $this->booking()->id, 'note' => '<script>alert(1)</script>']);

        $this->get(route('admin.payments.show', $p))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_missing_payment_is_404(): void
    {
        $this->get('/admin/payments/99999')->assertNotFound();
    }

    public function test_list_now_links_the_date_to_the_detail_page(): void
    {
        $p = Payment::factory()->create(['booking_id' => $this->booking()->id]);

        $this->get(route('admin.payments.index'))->assertSee(route('admin.payments.show', $p), false);
    }
}
