<?php

namespace Tests\Feature;

use Tests\Concerns\SignsInAsStaff;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edit booking (E3). The role middleware is switched off here because roles are tested on Day 19;
 * these tests only check the form and its validation.
 */
class EditBookingTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private function bookingWithTravelers(int $count = 2): Booking
    {
        $booking = Booking::factory()->create(['travelers_count' => $count]);
        for ($i = 0; $i < $count; $i++) {
            $factory = BookingTraveler::factory();
            $factory = $i === 0 ? $factory->lead() : $factory;
            $factory->create(['booking_id' => $booking->id, 'passport_number' => 'OLD' . $i . '1234']);
        }

        return $booking->fresh('travelers');
    }

    /** Valid form data built from the booking's current values. */
    private function payload(Booking $booking, array $override = []): array
    {
        $rows = [];
        foreach ($booking->travelers as $t) {
            $rows[] = [
                'id' => $t->id,
                'full_name' => $t->full_name,
                'date_of_birth' => $t->date_of_birth?->format('Y-m-d'),
                'nationality' => $t->nationality,
                'passport_number' => '',
                'passport_expiry' => $t->passport_expiry?->format('Y-m-d'),
                'email' => $t->email,
                'phone' => '+1 555 123 4567',
                'arrival_date' => $t->arrival_date?->format('Y-m-d'),
                'arrival_flight' => 'EK 530',
                'arrival_time' => '14:30',
                'arrival_details' => $t->arrival_details,
                'flag_note' => $t->flag_note,
                'health_notes' => $t->health_notes,
            ];
        }

        return array_replace_recursive([
            'insurance_selected' => '1',
            'notes' => 'Updated by test',
            'lead_traveler_id' => $booking->travelers->first()->id,
            'travelers' => $rows,
        ], $override);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsSuperAdmin();
    }

    public function test_edit_form_shows_booking_and_travelers(): void
    {
        $booking = $this->bookingWithTravelers();

        $this->get(route('admin.bookings.edit', $booking))
            ->assertOk()
            ->assertSee($booking->reference)
            ->assertSee($booking->travelers[0]->full_name)
            ->assertSee('Traveler 2')
            ->assertSee('On file: ending')          // masked passport hint
            ->assertDontSee('OLD01234');            // the passport number itself is never printed
    }

    public function test_valid_update_saves_changes_and_keeps_passport_when_blank(): void
    {
        $booking = $this->bookingWithTravelers();
        $second = $booking->travelers[1];

        $payload = $this->payload($booking, [
            'lead_traveler_id' => $second->id,
            'travelers' => [1 => ['full_name' => 'Renamed Traveler', 'phone' => '+44 20 7946 0958']],
        ]);

        $this->put(route('admin.bookings.update', $booking), $payload)
            ->assertRedirect(route('admin.bookings.edit', $booking))
            ->assertSessionHas('success');

        $second->refresh();
        $this->assertSame('Renamed Traveler', $second->full_name);
        $this->assertTrue($second->is_lead);
        $this->assertFalse($booking->travelers[0]->fresh()->is_lead);
        $this->assertSame('OLD11234', $second->passport_number);   // blank field kept the stored value
        $this->assertTrue($booking->fresh()->insurance_selected);
        $this->assertSame('Updated by test', $booking->fresh()->notes);
    }

    public function test_new_passport_number_replaces_the_old_one(): void
    {
        $booking = $this->bookingWithTravelers(1);
        $payload = $this->payload($booking, ['travelers' => [0 => ['passport_number' => 'Z9876543']]]);

        $this->put(route('admin.bookings.update', $booking), $payload)->assertSessionHasNoErrors();

        $this->assertSame('Z9876543', $booking->travelers[0]->fresh()->passport_number);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $booking = $this->bookingWithTravelers(1);
        $payload = $this->payload($booking, ['travelers' => [0 => [
            'full_name' => '',
            'email' => 'not-an-email',
            'phone' => 'abc',
            'date_of_birth' => now()->addYear()->format('Y-m-d'),
            'passport_number' => '<script>',
            'arrival_time' => '25:99',
            'arrival_flight' => 'EK@530',
        ]]]);

        $this->from(route('admin.bookings.edit', $booking))
            ->put(route('admin.bookings.update', $booking), $payload)
            ->assertRedirect(route('admin.bookings.edit', $booking))
            ->assertSessionHasErrors([
                'travelers.0.full_name', 'travelers.0.email', 'travelers.0.phone',
                'travelers.0.date_of_birth', 'travelers.0.passport_number',
                'travelers.0.arrival_time', 'travelers.0.arrival_flight',
            ]);

        $this->assertNotSame('', $booking->travelers[0]->fresh()->full_name);   // nothing was saved
    }

    public function test_cannot_edit_a_traveler_from_another_booking(): void
    {
        $booking = $this->bookingWithTravelers(1);
        $other = $this->bookingWithTravelers(1);

        $payload = $this->payload($booking, ['travelers' => [0 => ['id' => $other->travelers[0]->id]]]);

        $this->put(route('admin.bookings.update', $booking), $payload)
            ->assertSessionHasErrors('travelers.0.id');
        $this->assertNotSame('Hacked', $other->travelers[0]->fresh()->full_name);
    }

    public function test_lead_traveler_is_required_and_must_belong_to_booking(): void
    {
        $booking = $this->bookingWithTravelers(1);
        $other = $this->bookingWithTravelers(1);

        $this->put(route('admin.bookings.update', $booking), $this->payload($booking, ['lead_traveler_id' => $other->travelers[0]->id]))
            ->assertSessionHasErrors('lead_traveler_id');
    }

    public function test_price_and_status_cannot_be_changed_through_this_form(): void
    {
        $booking = $this->bookingWithTravelers(1);
        $before = $booking->only(['total_amount', 'status', 'payment_status']);

        $payload = $this->payload($booking) + ['total_amount' => 1, 'status' => 'cancelled', 'payment_status' => 'paid'];
        $this->put(route('admin.bookings.update', $booking), $payload)->assertSessionHasNoErrors();

        $this->assertEquals($before, $booking->fresh()->only(['total_amount', 'status', 'payment_status']));
    }
}
