<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The fields Asha hands to Anakha for the fulfillment desk and the manifest (Day 22 handoff).
 * If a column named in HANDOFF_manifest_fields.md changes, this test fails first.
 */
class ManifestFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_columns_named_in_the_handoff_exist(): void
    {
        foreach (['booking_id', 'is_lead', 'full_name', 'nationality', 'phone', 'email', 'arrival_date', 'arrival_time', 'arrival_flight', 'arrival_details', 'flag_note', 'health_notes'] as $column) {
            $this->assertTrue(Schema::hasColumn('booking_travelers', $column), $column);
        }
        foreach (['tour_id', 'tour_departure_id', 'status', 'payment_status', 'insurance_selected', 'insurance_amount', 'notes', 'reference'] as $column) {
            $this->assertTrue(Schema::hasColumn('bookings', $column), $column);
        }
    }

    public function test_has_health_note_never_exposes_the_text(): void
    {
        $with = BookingTraveler::factory()->withHealthNote('Diabetic, carries insulin')->create();
        $without = BookingTraveler::factory()->create(['health_notes' => null]);
        $blank = BookingTraveler::factory()->create(['health_notes' => '  ']);

        $this->assertTrue($with->hasHealthNote());
        $this->assertFalse($without->hasHealthNote());
        $this->assertFalse($blank->hasHealthNote());

        // Anything that turns a traveler into JSON or an array leaves the text and the passport number out.
        $json = $with->toJson();
        $this->assertStringNotContainsString('insulin', $json);
        $this->assertArrayNotHasKey('health_notes', $with->toArray());
        $this->assertArrayNotHasKey('passport_number', $with->toArray());
    }

    /** The query written in the handoff: travelers of one departure, cancelled bookings left out. */
    public function test_the_handoff_query_returns_the_right_travelers(): void
    {
        $on = Booking::factory()->create(['tour_departure_id' => 7, 'status' => 'confirmed']);
        $pendingCancel = Booking::factory()->create(['tour_departure_id' => 7, 'status' => 'cancellation_requested']);
        $cancelled = Booking::factory()->create(['tour_departure_id' => 7, 'status' => 'cancelled']);
        $elsewhere = Booking::factory()->create(['tour_departure_id' => 8, 'status' => 'confirmed']);

        $mine = BookingTraveler::factory()->count(2)->create(['booking_id' => $on->id]);
        $stillTravelling = BookingTraveler::factory()->create(['booking_id' => $pendingCancel->id]);
        BookingTraveler::factory()->create(['booking_id' => $cancelled->id]);
        BookingTraveler::factory()->create(['booking_id' => $elsewhere->id]);

        $rows = BookingTraveler::query()
            ->whereHas('booking', fn ($q) => $q->where('tour_departure_id', 7)->whereIn('status', ['pending', 'confirmed', 'cancellation_requested', 'completed']))
            ->with('booking:id,reference,status,insurance_selected,insurance_amount,notes')
            ->orderBy('arrival_date')->orderBy('arrival_time')->orderBy('full_name')
            ->get();

        $this->assertEqualsCanonicalizing(
            $mine->pluck('id')->push($stillTravelling->id)->all(),
            $rows->pluck('id')->all(),
        );
    }
}
