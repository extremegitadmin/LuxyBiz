<?php

namespace App\Services\Health;

use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\BookingTraveler;
use App\Models\HealthAccessLog;
use App\Models\User;
use App\Services\BookingChangeTracker;
use App\Support\SafeAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Showing and changing traveler health information (G4, Days 24 and 25). The rules:
 *  - what is shown is written to health_access_logs BEFORE it is shown. If that cannot be written, nothing is shown;
 *  - a change and its log line are saved together or not at all;
 *  - the log, the booking history and the audit log say THAT a note was seen or changed, never WHAT it said.
 * Rule failures throw a DomainException whose message is safe to show to staff.
 */
class HealthAccess
{
    public const MAX_LENGTH = 3000;

    /** Record that this person is about to see the health information of every traveler of the booking. */
    public function recordView(Booking $booking, User $by, Request $request): void
    {
        $travelerIds = $booking->travelers()->pluck('id')->all() ?: [null];

        try {
            DB::transaction(function () use ($booking, $by, $request, $travelerIds) {
                foreach ($travelerIds as $id) {
                    $this->log($booking->id, $id, $by, 'view', $request);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            throw new \DomainException('The access could not be recorded, so the health information was not shown. Try again, and tell the administrator if it keeps happening.');
        }

        SafeAudit::log('health.viewed', 'Health information of booking ' . $booking->reference . ' viewed', [
            'subject_id' => $booking->id, 'subject_type' => 'Booking', 'booking_reference' => $booking->reference, 'travelers' => count(array_filter($travelerIds)),
        ]);
    }

    /** Save the health notes of one traveler. Returns false when nothing changed. */
    public function update(Booking $booking, BookingTraveler $traveler, User $by, ?string $notes, Request $request, BookingChangeTracker $tracker): bool
    {
        $notes = $this->clean($notes);
        if ($notes !== null && mb_strlen($notes) > self::MAX_LENGTH) {
            throw new \DomainException('The health notes are too long. The limit is ' . self::MAX_LENGTH . ' characters.');
        }

        $changed = false;
        try {
            DB::transaction(function () use ($booking, $traveler, $by, $notes, $request, $tracker, &$changed) {
                $before = $tracker->snapshot($booking);
                $traveler->update(['health_notes' => $notes]);
                $diff = $tracker->diff($before, $tracker->snapshot($booking));

                if ($tracker->isEmpty($diff)) {
                    return;
                }
                $changed = true;

                // Shown in the booking history as "Health notes: [changed, value not stored]".
                BookingChange::create([
                    'booking_id' => $booking->id,
                    'changed_by' => $by->id,
                    'old_values' => $diff['old'],
                    'new_values' => $diff['new'],
                    'reason' => 'Health notes changed on the restricted health page',
                ]);
                $this->log($booking->id, $traveler->id, $by, 'edit', $request);
            });
        } catch (\Throwable $e) {
            report($e);
            throw new \DomainException('The change could not be saved and recorded, so nothing was changed. Try again.');
        }

        if ($changed) {
            SafeAudit::log('health.updated', 'Health notes of booking ' . $booking->reference . ' changed', [
                'subject_id' => $traveler->id, 'subject_type' => 'BookingTraveler',
                'booking_id' => $booking->id, 'booking_reference' => $booking->reference, 'cleared' => $notes === null,
            ]);
        }

        return $changed;
    }

    private function log(int $bookingId, ?int $travelerId, User $by, string $action, Request $request): void
    {
        HealthAccessLog::create([
            'booking_id' => $bookingId,
            'booking_traveler_id' => $travelerId,
            'user_id' => $by->id,
            'user_name' => mb_substr((string) $by->name, 0, 140),
            'action' => $action,
            'ip' => mb_substr((string) $request->ip(), 0, 45),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }

    /** Plain text with tidy line breaks. Markup is not removed: it is shown as typed and always escaped on screen. */
    private function clean(?string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));

        return $text === '' ? null : $text;
    }
}
