<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Cancellations\CancellationRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opening a cancellation request saves a snapshot and moves the booking (Day 16). */
class CancellationRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function booking(array $o = []): Booking
    {
        $b = Booking::factory()->create($o + ['user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'subtotal' => 5000, 'insurance_amount' => 0, 'total_amount' => 5000]);
        Payment::factory()->create(['booking_id' => $b->id, 'amount' => 5000, 'currency' => 'USD', 'status' => 'succeeded']);

        return $b;
    }

    public function test_open_saves_snapshot_and_moves_booking(): void
    {
        $b = $this->booking();
        $staff = User::factory()->create();
        $r = app(CancellationRequestService::class)->open($b, $staff, '  Medical  ');

        $this->assertSame('pending', $r->status);
        $this->assertSame('Medical', $r->reason);
        $this->assertSame($staff->id, $r->requested_by);
        $this->assertSame('USD', $r->currency);
        $this->assertSame(now()->toDateString(), $r->requested_on->toDateString());
        $this->assertSame((float) $r->calculated_refund, (float) $r->calculation['refund']);
        $this->assertArrayHasKey('tiers', $r->calculation);
        $this->assertSame('cancellation_requested', $b->fresh()->status);
        $this->assertMatchesRegularExpression('/^CR-\d{6}-[A-Z0-9]{4}$/', $r->reference);
    }

    public function test_second_open_request_is_refused(): void
    {
        $b = $this->booking();
        app(CancellationRequestService::class)->open($b);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already has an open cancellation request');
        app(CancellationRequestService::class)->open($b);
    }

    /** @dataProvider closed */
    public function test_finished_bookings_cannot_be_cancelled(string $status): void
    {
        $this->expectException(\DomainException::class);
        app(CancellationRequestService::class)->open($this->booking(['status' => $status]));
    }

    public static function closed(): array
    {
        return [['cancelled'], ['completed']];
    }

    public function test_a_new_request_is_allowed_after_a_rejection(): void
    {
        $b = $this->booking();
        $first = app(CancellationRequestService::class)->open($b);
        $first->update(['status' => 'rejected']);
        $b->update(['status' => 'confirmed']);

        $second = app(CancellationRequestService::class)->open($b);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, CancellationRequest::where('booking_id', $b->id)->count());
    }
}
