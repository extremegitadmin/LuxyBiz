<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\HealthAccessLog;
use App\Services\BookingChangeTracker;
use App\Services\Health\HealthAccess;
use Illuminate\Http\Request;

/** Restricted health and intake view of a booking (G4, Days 24 and 25). */
class HealthIntakeController extends Controller
{
    public function show(Request $request, Booking $booking, HealthAccess $access)
    {
        // A HEAD request would show nothing but would look like an access, so only a real GET is accepted.
        abort_unless($request->isMethod('GET'), 405);

        try {
            $access->recordView($booking, $request->user(), $request);   // log first, then show
        } catch (\DomainException $e) {
            return redirect()->route('admin.bookings.show', $booking)->with('error', $e->getMessage());
        }

        $booking->load(['user', 'travelers' => fn ($q) => $q->orderByDesc('is_lead')->orderBy('id')]);

        $history = $request->user()->can('admin.health.access_log')
            ? HealthAccessLog::query()->where('booking_id', $booking->id)->orderByDesc('id')->limit(30)->get()
            : collect();

        return response()
            ->view('admin.health.show', ['booking' => $booking, 'history' => $history, 'maxLength' => HealthAccess::MAX_LENGTH])
            ->withHeaders(['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function update(Request $request, Booking $booking, BookingTraveler $traveler, HealthAccess $access, BookingChangeTracker $tracker)
    {
        abort_unless($traveler->booking_id === $booking->id, 404);

        $data = $request->validate(['health_notes' => ['nullable', 'string', 'max:' . HealthAccess::MAX_LENGTH]], [
            'health_notes.max' => 'The health notes are too long. The limit is ' . HealthAccess::MAX_LENGTH . ' characters.',
        ]);

        try {
            $changed = $access->update($booking, $traveler, $request->user(), $data['health_notes'] ?? null, $request, $tracker);
        } catch (\DomainException $e) {
            return redirect()->route('admin.health.show', $booking)->withErrors(['health_notes_' . $traveler->id => $e->getMessage()]);
        }

        return redirect()->route('admin.health.show', $booking)->with('success', $changed ? 'Health notes of ' . $traveler->full_name . ' saved.' : 'No changes were made.');
    }
}
