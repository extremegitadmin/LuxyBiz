<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminRole;
use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingTraveler;
use App\Services\BookingChangeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Edit booking (E3): change history and audit entries (Day 5). */
class BookingChangeHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminRole::class);
    }

    private function booking(int $travelers = 2): Booking
    {
        $booking = Booking::factory()->create(['travelers_count' => $travelers, 'notes' => 'first note', 'insurance_selected' => false]);
        for ($i = 0; $i < $travelers; $i++) {
            $factory = BookingTraveler::factory();
            $factory = $i === 0 ? $factory->lead() : $factory;
            $factory->create(['booking_id' => $booking->id, 'passport_number' => 'OLDPASS' . $i, 'health_notes' => null]);
        }

        return $booking->fresh('travelers');
    }

    /** A fully valid form for the booking, with a few deliberate changes. */
    private function payload(Booking $booking, array $override = []): array
    {
        $rows = [];
        foreach ($booking->travelers as $t) {
            $rows[] = [
                'id' => $t->id, 'full_name' => $t->full_name, 'date_of_birth' => $t->date_of_birth?->format('Y-m-d'),
                'nationality' => $t->nationality, 'passport_number' => '', 'passport_expiry' => $t->passport_expiry?->format('Y-m-d'),
                'email' => $t->email, 'phone' => '+1 555 123 4567', 'arrival_date' => $t->arrival_date?->format('Y-m-d'),
                'arrival_flight' => 'EK 530', 'arrival_time' => '14:30', 'arrival_details' => $t->arrival_details,
                'flag_note' => $t->flag_note, 'health_notes' => $t->health_notes,
            ];
        }

        return array_replace_recursive([
            'insurance_selected' => '1', 'notes' => 'first note', 'reason' => '',
            'lead_traveler_id' => $booking->travelers->first()->id, 'travelers' => $rows,
        ], $override);
    }

    public function test_a_change_is_recorded_with_old_and_new_values_and_reason(): void
    {
        $booking = $this->booking();

        $this->put(route('admin.bookings.update', $booking), $this->payload($booking, [
            'reason' => 'Customer called to fix the email',
            'travelers' => [0 => ['email' => 'new.address@example.com']],
        ]))->assertSessionHasNoErrors();

        $change = BookingChange::where('booking_id', $booking->id)->firstOrFail();
        $firstId = $booking->travelers[0]->id;

        $this->assertSame('Customer called to fix the email', $change->reason);
        $this->assertSame('new.address@example.com', $change->new_values['travelers'][$firstId]['email']);
        $this->assertSame($booking->travelers[0]->email, $change->old_values['travelers'][$firstId]['email']);
        $this->assertSame(false, $change->old_values['booking']['insurance_selected']);
        $this->assertSame(true, $change->new_values['booking']['insurance_selected']);
        $this->assertArrayNotHasKey('notes', $change->new_values['booking']);      // notes did not change
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $booking = $this->booking(1);
        $payload = $this->payload($booking);

        $this->put(route('admin.bookings.update', $booking), $payload);          // first save changes some values
        $this->assertSame(1, BookingChange::count());

        $this->put(route('admin.bookings.update', $booking->fresh()), $payload)   // identical save
            ->assertSessionHas('success', 'No changes were made.');
        $this->assertSame(1, BookingChange::count());
    }

    public function test_sensitive_values_are_never_stored_in_the_history(): void
    {
        $booking = $this->booking(1);

        $this->put(route('admin.bookings.update', $booking), $this->payload($booking, [
            'travelers' => [0 => ['passport_number' => 'SECRET-PASS-99', 'health_notes' => 'Diabetic, carries insulin']],
        ]))->assertSessionHasNoErrors();

        $stored = json_encode(DB::table('booking_changes')->get());
        $this->assertStringNotContainsString('SECRET-PASS-99', $stored);
        $this->assertStringNotContainsString('Diabetic', $stored);
        $this->assertStringNotContainsString('OLDPASS0', $stored);
        $this->assertStringContainsString(BookingChangeTracker::HIDDEN, $stored);

        $lines = implode(' | ', BookingChange::first()->summaryLines());
        $this->assertStringContainsString('Passport number', $lines);
        $this->assertStringContainsString('Health notes', $lines);
    }

    public function test_history_is_shown_on_the_edit_page(): void
    {
        $booking = $this->booking(1);
        $this->put(route('admin.bookings.update', $booking), $this->payload($booking, ['reason' => 'Phone corrected']));

        $this->get(route('admin.bookings.edit', $booking))
            ->assertOk()
            ->assertSee('Change history')
            ->assertSee('Phone corrected')
            ->assertSee('Phone')
            ->assertSee('Travel insurance: No → Yes', false);
    }

    public function test_empty_history_message(): void
    {
        $this->get(route('admin.bookings.edit', $this->booking(1)))
            ->assertSee('No changes have been recorded for this booking yet.');
    }

    public function test_blank_and_null_are_treated_as_the_same_value(): void
    {
        $tracker = new BookingChangeTracker();
        $a = ['booking' => ['insurance_selected' => false, 'notes' => null], 'travelers' => []];
        $b = ['booking' => ['insurance_selected' => false, 'notes' => null], 'travelers' => []];

        $this->assertTrue($tracker->isEmpty($tracker->diff($a, $b)));
    }

    public function test_audit_entries_are_written_when_the_audit_helper_exists(): void
    {
        if (! class_exists(\App\Support\Audit::class) || ! Schema::hasTable('audit_logs')) {
            $this->markTestSkipped("Anakha's audit helper (App\\Support\\Audit and the audit_logs table) is not merged yet.");
        }

        $booking = $this->booking(1);
        $this->put(route('admin.bookings.update', $booking), $this->payload($booking, [
            'travelers' => [0 => ['passport_number' => 'SECRET-PASS-99']],
        ]));

        $actions = DB::table('audit_logs')->pluck('action')->all();
        $this->assertContains('booking.edited', $actions);
        $this->assertContains('booking.sensitive_edited', $actions);
        $this->assertStringNotContainsString('SECRET-PASS-99', json_encode(DB::table('audit_logs')->get()));
    }
}
