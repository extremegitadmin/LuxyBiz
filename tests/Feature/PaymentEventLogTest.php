<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Webhook events log (Day 9). */
class PaymentEventLogTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    private function payment(array $attrs = []): Payment
    {
        return Payment::factory()->create($attrs + ['booking_id' => Booking::factory()->create()->id]);
    }

    /** Event names in the table rows (the Event dropdown lists every name, so page text is not a fair check). */
    private function listed(array $query = []): array
    {
        return $this->get(route('admin.payments.events', $query))->assertOk()->viewData('events')->pluck('event')->all();
    }

    public function test_record_removes_secrets_and_card_numbers_at_any_depth(): void
    {
        $event = PaymentEvent::record(['event' => 'payment.succeeded', 'external_id' => 'evt_1'], [
            'id' => 'evt_1',
            'signature' => 'SIG-SECRET',
            'data' => ['amount' => 100, 'card' => ['brand' => 'visa', 'last4' => '4242', 'number' => '4242424242424242', 'cvc' => '123']],
            'headers' => ['Authorization' => 'Bearer TOP-SECRET'],
        ]);

        $stored = json_encode(\DB::table('payment_events')->get());
        foreach (['SIG-SECRET', '4242424242424242', '123"', 'TOP-SECRET'] as $leak) {
            $this->assertStringNotContainsString($leak, $stored);
        }
        $this->assertSame('visa', $event->payload['data']['card']['brand']);
        $this->assertSame('4242', $event->payload['data']['card']['last4']);
        $this->assertSame(PaymentEvent::REDACTED, $event->payload['signature']);
    }

    public function test_the_same_gateway_event_sent_twice_is_stored_once(): void
    {
        PaymentEvent::record(['event' => 'payment.succeeded', 'external_id' => 'evt_dup'], ['a' => 1]);
        PaymentEvent::record(['event' => 'payment.succeeded', 'external_id' => 'evt_dup'], ['a' => 1]);

        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_log_lists_events_with_outcome_and_payment_link(): void
    {
        $p = $this->payment();
        PaymentEvent::record(['payment_id' => $p->id, 'gateway' => 'stripe', 'gateway_reference' => 'REF-EVT1', 'event' => 'payment.succeeded', 'outcome' => 'processed']);

        $this->get(route('admin.payments.events'))
            ->assertOk()
            ->assertSee('Processed')
            ->assertSee('REF-EVT1')
            ->assertSee($p->booking->reference)
            ->assertSee(route('admin.payments.show', $p), false);
    }

    public function test_unmatched_events_are_marked(): void
    {
        PaymentEvent::record(['event' => 'payment.succeeded', 'outcome' => 'ignored', 'note' => 'No payment found for this reference.']);

        $this->get(route('admin.payments.events'))
            ->assertSee('Not matched')->assertSee('No payment found for this reference.');
    }

    public function test_filters_by_event_outcome_linked_and_dates(): void
    {
        $p = $this->payment();
        PaymentEvent::record(['payment_id' => $p->id, 'event' => 'evt.alpha', 'outcome' => 'processed']);
        PaymentEvent::record(['event' => 'evt.beta', 'outcome' => 'failed']);
        PaymentEvent::record(['event' => 'evt.old', 'outcome' => 'processed', 'created_at' => now()->subDays(40)]);

        $this->assertSame(['evt.beta'], $this->listed(['event' => 'evt.beta']));
        $this->assertSame(['evt.beta'], $this->listed(['outcome' => 'failed']));
        $this->assertSame(['evt.alpha'], $this->listed(['linked' => 'yes']));
        $this->assertEqualsCanonicalizing(['evt.beta', 'evt.old'], $this->listed(['linked' => 'no']));
        $this->assertEqualsCanonicalizing(['evt.alpha', 'evt.beta'], $this->listed(['from' => now()->subDays(5)->format('Y-m-d')]));
        $this->assertSame(['evt.old'], $this->listed(['to' => now()->subDays(30)->format('Y-m-d')]));
    }

    public function test_search_by_gateway_reference_event_id_and_booking_reference(): void
    {
        $p = $this->payment();
        PaymentEvent::record(['payment_id' => $p->id, 'gateway_reference' => 'REF-AAA', 'external_id' => 'evt_aaa', 'event' => 'evt.one']);
        PaymentEvent::record(['gateway_reference' => 'REF-BBB', 'external_id' => 'evt_bbb', 'event' => 'evt.two']);

        $this->assertSame(['evt.two'], $this->listed(['q' => 'REF-BBB']));
        $this->assertSame(['evt.one'], $this->listed(['q' => 'evt_aaa']));
        $this->assertSame(['evt.one'], $this->listed(['q' => $p->booking->reference]));
        $this->assertSame([], $this->listed(['q' => '%']));
        $this->get(route('admin.payments.events', ['q' => '%']))->assertSee('No webhook events match these filters.');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->from(route('admin.payments.events'))
            ->get(route('admin.payments.events', ['outcome' => 'bogus', 'linked' => 'maybe', 'from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertSessionHasErrors(['outcome', 'linked', 'to']);
    }

    public function test_payload_is_shown_escaped_and_cleaned(): void
    {
        PaymentEvent::record(['event' => 'evt.x'], ['note' => '<script>alert(1)</script>']);
        // a row written without record() (old data) must still be cleaned on the way out
        PaymentEvent::create(['event' => 'evt.raw', 'payload' => ['secret_key' => 'LEAKED-VALUE']]);

        $this->get(route('admin.payments.events'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('LEAKED-VALUE');
    }

    public function test_warning_banner_counts_recent_failed_or_ignored_events(): void
    {
        PaymentEvent::record(['event' => 'a', 'outcome' => 'failed']);
        PaymentEvent::record(['event' => 'b', 'outcome' => 'ignored']);
        PaymentEvent::record(['event' => 'c', 'outcome' => 'failed', 'created_at' => now()->subDays(30)]);
        PaymentEvent::record(['event' => 'd', 'outcome' => 'processed']);

        $this->get(route('admin.payments.events'))->assertSee('2 events in the last 7 days were ignored or failed.');
    }

    public function test_empty_log_message(): void
    {
        $this->get(route('admin.payments.events'))->assertOk()->assertSee('Events appear here once an online gateway is connected');
    }

    public function test_payment_detail_lists_its_own_events_only(): void
    {
        $p = $this->payment();
        $other = $this->payment();
        PaymentEvent::record(['payment_id' => $p->id, 'event' => 'evt.mine', 'outcome' => 'processed']);
        PaymentEvent::record(['payment_id' => $other->id, 'event' => 'evt.theirs', 'outcome' => 'processed']);

        $this->get(route('admin.payments.show', $p))->assertOk()->assertSee('evt.mine')->assertDontSee('evt.theirs');
        $this->get(route('admin.payments.show', $this->payment()))->assertSee('No gateway events yet.');
    }

    public function test_events_route_is_not_swallowed_by_the_payment_id_route(): void
    {
        $this->get('/admin/payments/events')->assertOk();
        $this->get('/admin/payments/abc')->assertNotFound();
    }

    public function test_deleting_a_payment_keeps_its_events(): void
    {
        $p = $this->payment();
        PaymentEvent::record(['payment_id' => $p->id, 'event' => 'evt.keep']);

        $p->delete();

        $this->assertNull(PaymentEvent::first()->payment_id);
        $this->assertSame(1, PaymentEvent::count());
    }
}
