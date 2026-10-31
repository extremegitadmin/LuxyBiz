<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_has_travelers_and_payments(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 1000]);
        BookingTraveler::factory()->lead()->create(['booking_id' => $booking->id]);
        BookingTraveler::factory()->create(['booking_id' => $booking->id]);
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 1000]);

        $this->assertCount(2, $booking->travelers);
        $this->assertTrue($booking->leadTraveler->is_lead);
        $this->assertCount(1, $booking->payments);
    }

    public function test_payment_status_follows_payments(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 1000]);
        $booking->syncPaymentStatus();
        $this->assertSame('unpaid', $booking->fresh()->payment_status);

        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 400]);
        $booking->syncPaymentStatus();
        $this->assertSame('partial', $booking->fresh()->payment_status);

        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 600]);
        $booking->syncPaymentStatus();
        $this->assertSame('paid', $booking->fresh()->payment_status);

        Payment::factory()->refund()->create(['booking_id' => $booking->id, 'amount' => 1000]);
        $booking->syncPaymentStatus();
        $this->assertSame('refunded', $booking->fresh()->payment_status);
        $this->assertSame(0.0, $booking->paidAmount());
    }

    public function test_failed_payment_does_not_count(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 500]);
        Payment::factory()->failed()->create(['booking_id' => $booking->id, 'amount' => 500]);
        $this->assertSame(0.0, $booking->paidAmount());
    }

    public function test_passport_is_encrypted_in_database_and_hidden_in_json(): void
    {
        $traveler = BookingTraveler::factory()->create(['passport_number' => 'P1234567']);

        $raw = DB::table('booking_travelers')->where('id', $traveler->id)->value('passport_number');
        $this->assertNotSame('P1234567', $raw);                         // not readable in the database
        $this->assertSame('P1234567', $traveler->fresh()->passport_number); // readable through the model
        $this->assertArrayNotHasKey('passport_number', $traveler->toArray());
        $this->assertArrayNotHasKey('health_notes', $traveler->toArray());
    }

    public function test_deleting_a_booking_removes_travelers_and_payments(): void
    {
        $booking = Booking::factory()->create();
        BookingTraveler::factory()->create(['booking_id' => $booking->id]);
        Payment::factory()->create(['booking_id' => $booking->id]);

        $booking->delete();

        $this->assertSame(0, BookingTraveler::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_reference_is_unique_and_formatted(): void
    {
        $a = Booking::generateReference();
        $this->assertMatchesRegularExpression('/^LB-\d{6}-[A-Z0-9]{4}$/', $a);
    }
}
