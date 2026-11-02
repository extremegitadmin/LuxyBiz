<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\BookingChange;
use App\Services\BookingChangeTracker;
use Illuminate\Support\Facades\DB;

/**
 * Owner: Asha. Day 4: edit() and update(). Day 5: change history and audit entries.
 * index() and show() arrive on Day 6.
 */
class BookingController extends Controller
{
    /** Edit booking (E3): show the form and the change history. */
    public function edit(Booking $booking)
    {
        $booking->load([
            'user',
            'travelers' => fn ($q) => $q->orderByDesc('is_lead')->orderBy('id'),
        ]);
        $changes = $booking->changes()->with('user')->limit(20)->get();

        return view('admin.bookings.edit', compact('booking', 'changes'));
    }

    /** Edit booking (E3): validate, save, record what changed. */
    public function update(UpdateBookingRequest $request, Booking $booking, BookingChangeTracker $tracker)
    {
        $data = $request->validated();

        $diff = DB::transaction(function () use ($data, $booking, $request, $tracker) {
            $before = $tracker->snapshot($booking);

            $booking->update([
                'insurance_selected' => $request->boolean('insurance_selected'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['travelers'] as $row) {
                $traveler = $booking->travelers()->findOrFail($row['id']);

                $fields = [
                    'is_lead' => (int) $row['id'] === (int) $data['lead_traveler_id'],
                    'full_name' => $row['full_name'],
                    'date_of_birth' => $row['date_of_birth'] ?? null,
                    'nationality' => $row['nationality'] ?? null,
                    'passport_expiry' => $row['passport_expiry'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'arrival_date' => $row['arrival_date'] ?? null,
                    'arrival_flight' => $row['arrival_flight'] ?? null,
                    'arrival_time' => $row['arrival_time'] ?? null,
                    'arrival_details' => $row['arrival_details'] ?? null,
                    'flag_note' => $row['flag_note'] ?? null,
                    'health_notes' => $row['health_notes'] ?? null,
                ];

                // A blank passport field keeps the stored (encrypted) number.
                if (! empty($row['passport_number'])) {
                    $fields['passport_number'] = $row['passport_number'];
                }

                $traveler->update($fields);
            }

            $diff = $tracker->diff($before, $tracker->snapshot($booking));

            if (! $tracker->isEmpty($diff)) {
                BookingChange::create([
                    'booking_id' => $booking->id,
                    'changed_by' => $request->user()?->id,
                    'old_values' => $diff['old'],
                    'new_values' => $diff['new'],
                    'reason' => $data['reason'] ?? null,
                ]);
            }

            return $diff;
        });

        if ($tracker->isEmpty($diff)) {
            return redirect()->route('admin.bookings.edit', $booking)->with('success', 'No changes were made.');
        }

        $this->audit('booking.edited', $booking, [
            'reference' => $booking->reference,
            'changed' => $this->changedFieldNames($diff),
            'reason' => $data['reason'] ?? null,
        ]);
        if ($diff['sensitive']) {
            // Which sensitive fields changed, never their values.
            $this->audit('booking.sensitive_edited', $booking, [
                'reference' => $booking->reference,
                'fields' => $diff['sensitive'],
            ]);
        }

        return redirect()->route('admin.bookings.edit', $booking)->with('success', 'Booking ' . $booking->reference . ' updated.');
    }

    /** Field names that changed, for the audit entry (no values). */
    private function changedFieldNames(array $diff): array
    {
        $names = array_keys($diff['new']['booking']);
        foreach ($diff['new']['travelers'] as $id => $fields) {
            foreach (array_keys($fields) as $field) {
                if ($field !== '_name') {
                    $names[] = "traveler.$id.$field";
                }
            }
        }

        return $names;
    }

    /**
     * Writes to Anakha's audit log. Until Anakha's App\Support\Audit helper is merged to develop
     * this does nothing, so the screen keeps working. Once it is merged, entries appear automatically.
     */
    private function audit(string $action, Booking $booking, array $meta): void
    {
        if (class_exists(\App\Support\Audit::class)) {
            \App\Support\Audit::log($action, $booking, $meta);
        }
    }
}
