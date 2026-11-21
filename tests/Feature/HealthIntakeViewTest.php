<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingTraveler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SignsInAsStaff;
use Tests\TestCase;

/** Restricted health and intake view, encrypted health notes, and keeping health out of Edit booking (G4, Day 24). */
class HealthIntakeViewTest extends TestCase
{
    use RefreshDatabase;
    use SignsInAsStaff;

    private const NOTE = 'Diabetic, carries insulin. Allergic to penicillin.';

    private function booking(array $traveler = []): Booking
    {
        $b = Booking::factory()->create(['user_id' => User::factory()->create(['name' => 'Olivia Bennett'])->id, 'insurance_selected' => true]);
        BookingTraveler::factory()->lead()->create($traveler + ['booking_id' => $b->id, 'full_name' => 'Mariam Said', 'health_notes' => self::NOTE, 'flag_note' => 'Needs wheelchair assistance']);

        return $b;
    }

    private function raw(int $travelerId): ?string
    {
        return DB::table('booking_travelers')->where('id', $travelerId)->value('health_notes');
    }

    private function viewer(array $extra = []): User
    {
        return $this->userWith(array_merge(['admin.health.view'], $extra), 'h' . count($extra) . uniqid() . '@example.test', 'Health ' . implode(',', $extra));
    }

    // ------------------------------------------------------------------ encryption

    public function test_health_notes_are_stored_encrypted_and_read_back_as_text(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();

        $raw = $this->raw($t->id);
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('insulin', $raw);
        $this->assertStringNotContainsString('Diabetic', $raw);
        $this->assertSame(self::NOTE, Crypt::decryptString($raw));
        $this->assertSame(self::NOTE, $t->fresh()->health_notes);
    }

    public function test_blank_notes_are_stored_as_null(): void
    {
        $b = $this->booking(['health_notes' => '   ']);
        $t = $b->travelers->first();

        $this->assertNull($this->raw($t->id));
        $this->assertNull($t->fresh()->health_notes);
        $this->assertFalse($t->fresh()->hasHealthNote());
    }

    public function test_a_note_saved_before_encryption_existed_is_still_readable(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        DB::table('booking_travelers')->where('id', $t->id)->update(['health_notes' => 'Old plain note: asthma']);

        $this->assertSame('Old plain note: asthma', $t->fresh()->health_notes);
        $this->assertTrue($t->fresh()->hasHealthNote());
    }

    public function test_the_command_encrypts_old_plain_notes_and_is_safe_to_repeat(): void
    {
        $b = $this->booking();
        $encrypted = $b->travelers->first();
        $plain = BookingTraveler::factory()->create(['booking_id' => $b->id]);
        DB::table('booking_travelers')->where('id', $plain->id)->update(['health_notes' => 'Old plain note: asthma']);
        $before = $this->raw($encrypted->id);

        $this->artisan('health:encrypt-existing', ['--dry-run' => true])->expectsOutputToContain('Plain text: 1. Encrypted now: 0. Already encrypted: 1.')->assertSuccessful();
        $this->assertSame('Old plain note: asthma', $this->raw($plain->id));

        $this->artisan('health:encrypt-existing')->expectsOutputToContain('Plain text: 1. Encrypted now: 1. Already encrypted: 1.')->assertSuccessful();
        $this->assertStringNotContainsString('asthma', $this->raw($plain->id));
        $this->assertSame('Old plain note: asthma', $plain->fresh()->health_notes);
        $this->assertSame($before, $this->raw($encrypted->id));   // already encrypted: left alone

        $this->artisan('health:encrypt-existing')->expectsOutputToContain('Plain text: 0. Encrypted now: 0. Already encrypted: 2.')->assertSuccessful();
    }

    public function test_a_database_dump_of_the_column_holds_no_readable_health_text(): void
    {
        $this->booking();
        $dump = json_encode(DB::table('booking_travelers')->get());

        $this->assertStringNotContainsString('insulin', $dump);
        $this->assertStringNotContainsString('penicillin', $dump);
    }

    // ------------------------------------------------------------------ the page

    public function test_the_page_shows_health_notes_flag_arrival_and_insurance(): void
    {
        $b = $this->booking(['arrival_date' => '2027-03-10', 'arrival_time' => '14:30:00', 'arrival_flight' => 'BA143', 'arrival_details' => 'Hotel pickup at gate 2']);
        $this->actingAs($this->viewer());

        $this->get(route('admin.health.show', $b))->assertOk()
            ->assertSee('Mariam Said')->assertSee('Lead')
            ->assertSee(self::NOTE)
            ->assertSee('Needs wheelchair assistance')
            ->assertSee('10 Mar 2027')->assertSee('14:30')->assertSee('BA143')->assertSee('Hotel pickup at gate 2')
            ->assertSee('Restricted information')
            ->assertDontSee('<textarea', false);          // view-only: no edit form
    }

    public function test_the_page_is_not_kept_by_browsers(): void
    {
        $b = $this->booking();
        $this->actingAs($this->viewer());

        $r = $this->get(route('admin.health.show', $b))->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $r->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_notes_are_escaped_so_markup_is_shown_as_text(): void
    {
        $b = $this->booking(['health_notes' => '<script>alert(1)</script> asthma']);
        $this->actingAs($this->viewer());

        $html = $this->get(route('admin.health.show', $b))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_an_editor_sees_a_form_with_the_current_note(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));

        $this->get(route('admin.health.show', $b))->assertOk()->assertSee('<textarea', false)->assertSee(self::NOTE)
            ->assertSee('action="' . route('admin.health.update', [$b, $t]) . '"', false);
    }

    public function test_a_booking_without_travelers_still_opens(): void
    {
        $b = Booking::factory()->create();
        $this->actingAs($this->viewer());

        $this->get(route('admin.health.show', $b))->assertOk()->assertSee('No travelers on this booking.');
    }

    public function test_the_booking_page_has_a_link_but_never_the_health_text(): void
    {
        $b = $this->booking();

        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.health.view'], 'a@example.test', 'Bookings and health'));
        $page = $this->get(route('admin.bookings.show', $b))->assertOk()->assertSee(route('admin.health.show', $b), false);
        $this->assertStringNotContainsString('insulin', $page->getContent());

        $this->actingAs($this->userWith(['admin.bookings.view'], 'b@example.test', 'Bookings only'));
        $this->get(route('admin.bookings.show', $b))->assertOk()->assertDontSee(route('admin.health.show', $b), false)->assertDontSee('insulin');
    }

    // ------------------------------------------------------------------ editing

    public function test_an_editor_saves_a_note_which_is_stored_encrypted(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));

        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => "Asthma, inhaler in hand luggage.\r\nNo long treks."])
            ->assertRedirect(route('admin.health.show', $b))->assertSessionHas('success');

        $this->assertSame("Asthma, inhaler in hand luggage.\nNo long treks.", $t->fresh()->health_notes);
        $this->assertStringNotContainsString('Asthma', $this->raw($t->id));
    }

    public function test_clearing_a_note_and_saving_the_same_note_again(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));

        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => self::NOTE])->assertSessionHas('success', 'No changes were made.');
        $this->assertSame(0, BookingChange::count());

        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => ''])->assertSessionHas('success');
        $this->assertNull($this->raw($t->id));
        $this->assertSame(1, BookingChange::count());
    }

    public function test_a_note_over_the_limit_is_refused(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));

        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => str_repeat('a', 3001)])->assertSessionHasErrors('health_notes');
        $this->assertSame(self::NOTE, $t->fresh()->health_notes);
    }

    public function test_a_traveler_of_another_booking_cannot_be_changed(): void
    {
        $b = $this->booking();
        $other = $this->booking(['full_name' => 'Someone Else'])->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));

        $this->put(route('admin.health.update', [$b, $other]), ['health_notes' => 'changed'])->assertNotFound();
        $this->assertSame(self::NOTE, $other->fresh()->health_notes);
    }

    public function test_a_change_shows_in_the_booking_history_without_the_text(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->viewer(['admin.health.edit']));
        $this->put(route('admin.health.update', [$b, $t]), ['health_notes' => 'Now a completely different SECRETNOTE']);

        $stored = json_encode(DB::table('booking_changes')->get());
        $this->assertStringNotContainsString('SECRETNOTE', $stored);
        $this->assertStringNotContainsString('insulin', $stored);

        $change = BookingChange::sole();
        $this->assertSame('Health notes changed on the restricted health page', $change->reason);
        $this->assertStringContainsString('Health notes', implode(' | ', $change->summaryLines()));
    }

    // ------------------------------------------------------------------ Edit booking no longer shows or changes health

    public function test_the_edit_booking_page_has_no_health_notes(): void
    {
        $b = $this->booking();
        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.bookings.edit'], 'e@example.test', 'Editor'));

        $html = $this->get(route('admin.bookings.edit', $b))->assertOk()->assertSee('Health notes are not edited here')->getContent();
        $this->assertStringNotContainsString('insulin', $html);
        $this->assertStringNotContainsString('[health_notes]', $html);
        $this->assertStringNotContainsString('<textarea id="t0_health"', $html);
    }

    public function test_health_notes_sent_to_edit_booking_are_ignored_and_the_note_is_kept(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.bookings.edit'], 'e@example.test', 'Editor'));

        $this->put(route('admin.bookings.update', $b), [
            'insurance_selected' => 1, 'notes' => 'x', 'lead_traveler_id' => $t->id,
            'travelers' => [['id' => $t->id, 'full_name' => 'Mariam Said', 'phone' => '+44 20 7946 0958', 'health_notes' => 'OVERWRITTEN']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::NOTE, $t->fresh()->health_notes);
        $this->assertSame('+44 20 7946 0958', $t->fresh()->phone);   // the rest of the edit still works
    }

    public function test_saving_edit_booking_does_not_wipe_a_health_note(): void
    {
        $b = $this->booking();
        $t = $b->travelers->first();
        $this->actingAs($this->userWith(['admin.bookings.view', 'admin.bookings.edit'], 'e@example.test', 'Editor'));

        // The real form no longer sends health_notes at all.
        $this->put(route('admin.bookings.update', $b), [
            'insurance_selected' => 0, 'notes' => 'y', 'lead_traveler_id' => $t->id,
            'travelers' => [['id' => $t->id, 'full_name' => 'Mariam Said']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::NOTE, $t->fresh()->health_notes);
    }
}
