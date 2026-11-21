<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Services\Bookings\ManifestTravelerData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The safe traveler data handed to the fulfillment desk and manifest (Day 25). */
class ManifestTravelerDataTest extends TestCase
{
    use RefreshDatabase;

    private function booking(int $departure, string $status = 'confirmed', array $o = []): Booking
    {
        return Booking::factory()->create($o + ['tour_departure_id' => $departure, 'status' => $status]);
    }

    private function traveler(Booking $b, array $o = []): BookingTraveler
    {
        return BookingTraveler::factory()->create($o + ['booking_id' => $b->id, 'health_notes' => null]);
    }

    public function test_rows_have_exactly_the_agreed_keys(): void
    {
        $b = $this->booking(7, 'confirmed', ['insurance_selected' => true, 'payment_status' => 'paid']);
        $this->traveler($b, ['full_name' => 'Mariam Said', 'is_lead' => true, 'arrival_date' => '2027-03-10', 'arrival_time' => '14:30:00',
            'arrival_flight' => 'BA143', 'arrival_details' => 'Hotel pickup', 'flag_note' => 'Needs wheelchair', 'phone' => '+44 20 7946 0958', 'nationality' => 'British']);

        $rows = (new ManifestTravelerData())->forDeparture(7);

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame(ManifestTravelerData::KEYS, array_keys($row));
        $this->assertSame('Mariam Said', $row['full_name']);
        $this->assertTrue($row['is_lead']);
        $this->assertSame('2027-03-10', $row['arrival_date']);
        $this->assertSame('14:30', $row['arrival_time']);
        $this->assertSame('BA143', $row['arrival_flight']);
        $this->assertSame('Hotel pickup', $row['arrival_details']);
        $this->assertSame('Needs wheelchair', $row['flag_note']);
        $this->assertSame($b->reference, $row['booking_reference']);
        $this->assertSame('confirmed', $row['booking_status']);
        $this->assertSame('paid', $row['payment_status']);
        $this->assertTrue($row['insurance_selected']);
        $this->assertFalse($row['has_health_note']);
    }

    public function test_it_says_that_a_health_note_exists_but_never_what_it_says(): void
    {
        $b = $this->booking(7);
        $this->traveler($b, ['health_notes' => 'Diabetic, carries insulin SECRETHEALTHTEXT', 'passport_number' => 'SECRETPASS77', 'full_name' => 'Mariam Said']);
        $this->traveler($b, ['full_name' => 'Omar Said']);

        $rows = (new ManifestTravelerData())->forDeparture(7);

        $byName = $rows->keyBy('full_name');
        $this->assertTrue($byName['Mariam Said']['has_health_note']);
        $this->assertFalse($byName['Omar Said']['has_health_note']);

        $dump = json_encode($rows) . serialize($rows->all()) . var_export($rows->all(), true);
        foreach (['SECRETHEALTHTEXT', 'insulin', 'SECRETPASS77'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
        foreach ($rows as $row) {
            foreach (['health_notes', 'passport_number', 'passport_expiry', 'date_of_birth'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $row);
            }
        }
    }

    public function test_only_this_departure_and_only_travelling_bookings(): void
    {
        $on = $this->traveler($this->booking(7, 'confirmed'), ['full_name' => 'A confirmed']);
        $pending = $this->traveler($this->booking(7, 'pending'), ['full_name' => 'B pending']);
        $leaving = $this->traveler($this->booking(7, 'cancellation_requested'), ['full_name' => 'C leaving']);
        $done = $this->traveler($this->booking(7, 'completed'), ['full_name' => 'D done']);
        $this->traveler($this->booking(7, 'cancelled'), ['full_name' => 'E cancelled']);
        $this->traveler($this->booking(8, 'confirmed'), ['full_name' => 'F elsewhere']);
        $this->traveler(Booking::factory()->create(['tour_departure_id' => null, 'status' => 'confirmed']), ['full_name' => 'G no departure']);

        $names = (new ManifestTravelerData())->forDeparture(7)->pluck('full_name')->all();
        sort($names);
        $this->assertSame(['A confirmed', 'B pending', 'C leaving', 'D done'], $names);

        $only = (new ManifestTravelerData())->forDeparture(7, ['confirmed'])->pluck('full_name')->all();
        $this->assertSame(['A confirmed'], $only);
    }

    public function test_sorted_by_arrival_then_time_then_name_with_unknown_arrivals_last(): void
    {
        $b = $this->booking(7);
        $this->traveler($b, ['full_name' => 'Zed', 'arrival_date' => '2027-03-10', 'arrival_time' => '09:00:00']);
        $this->traveler($b, ['full_name' => 'Amy', 'arrival_date' => '2027-03-10', 'arrival_time' => '09:00:00']);
        $this->traveler($b, ['full_name' => 'Late', 'arrival_date' => '2027-03-10', 'arrival_time' => '18:00:00']);
        $this->traveler($b, ['full_name' => 'NoTime', 'arrival_date' => '2027-03-10', 'arrival_time' => null]);
        $this->traveler($b, ['full_name' => 'Early', 'arrival_date' => '2027-03-09', 'arrival_time' => '23:00:00']);
        $this->traveler($b, ['full_name' => 'Unknown', 'arrival_date' => null, 'arrival_time' => null]);

        $names = (new ManifestTravelerData())->forDeparture(7)->pluck('full_name')->all();

        $this->assertSame(['Early', 'Amy', 'Zed', 'Late', 'NoTime', 'Unknown'], $names);
    }

    public function test_empty_departure_and_missing_values_do_not_break_it(): void
    {
        $this->assertCount(0, (new ManifestTravelerData())->forDeparture(999));

        $b = $this->booking(7);
        $this->traveler($b, ['full_name' => 'Bare', 'arrival_date' => null, 'arrival_time' => null, 'arrival_flight' => null, 'phone' => null, 'email' => null, 'nationality' => null]);
        $row = (new ManifestTravelerData())->forDeparture(7)[0];

        $this->assertNull($row['arrival_date']);
        $this->assertNull($row['arrival_time']);
        $this->assertNull($row['phone']);
    }

    public function test_it_does_one_query_for_travelers_and_one_for_bookings(): void
    {
        $b = $this->booking(7);
        BookingTraveler::factory()->count(5)->create(['booking_id' => $b->id]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        (new ManifestTravelerData())->forDeparture(7);
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $queries);
    }
}
