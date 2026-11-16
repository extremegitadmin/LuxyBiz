<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * Owner: Asha. Day 4 adds edit() and update(). index() and show() arrive on Day 6.
 */
class BookingController extends Controller
{
    /** Edit booking (E3): show the form. */
    public function edit(Booking $booking)
    {
        $booking->load(['user', 'travelers' => fn ($q) => $q->orderByDesc('is_lead')->orderBy('id')]);

        return view('admin.bookings.edit', compact('booking'));
    }

    /** Edit booking (E3): validate and save. */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $booking, $request) {
            // Day 5: capture $before here, then write a BookingChange row and an audit entry after saving.

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
        });

        return redirect()->route('admin.bookings.edit', $booking)->with('success', 'Booking ' . $booking->reference . ' updated.');
    }
}
