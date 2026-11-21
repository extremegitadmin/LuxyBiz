<?php

namespace App\Services\Bookings;

use App\Models\BookingTraveler;
use Illuminate\Support\Collection;

/**
 * The traveler fields for Anakha's fulfillment desk and manifest (Day 25 handoff).
 * Every row is a plain array holding ONLY what is safe to show staff in a list or print on a manifest.
 * It never contains the health text, the passport number or the date of birth, so a desk or manifest built on it
 * cannot leak them. "has_health_note" only says that a note exists; the text is on the restricted health page.
 */
class ManifestTravelerData
{
    /** Bookings still on, or about to be finished. A cancelled booking's travelers are not travelling. */
    public const STATUSES = ['pending', 'confirmed', 'cancellation_requested', 'completed'];

    /** The exact keys of every row. */
    public const KEYS = [
        'traveler_id', 'booking_id', 'booking_reference', 'booking_status', 'payment_status',
        'full_name', 'is_lead', 'phone', 'email', 'nationality',
        'arrival_date', 'arrival_time', 'arrival_flight', 'arrival_details',
        'flag_note', 'has_health_note', 'insurance_selected',
    ];

    /**
     * @param  list<string>|null  $statuses  booking statuses to include (default: self::STATUSES)
     * @return Collection<int, array<string, mixed>> sorted by arrival date, arrival time, then name; travelers without an arrival date come last
     */
    public function forDeparture(int $departureId, ?array $statuses = null): Collection
    {
        return BookingTraveler::query()
            ->whereHas('booking', fn ($q) => $q->where('tour_departure_id', $departureId)->whereIn('status', $statuses ?? self::STATUSES))
            ->with('booking:id,reference,status,payment_status,insurance_selected')
            ->get()
            ->map(fn (BookingTraveler $t) => $this->row($t))
            ->sortBy([
                fn ($a, $b) => [$a['arrival_date'] === null, $a['arrival_date']] <=> [$b['arrival_date'] === null, $b['arrival_date']],
                fn ($a, $b) => [$a['arrival_time'] === null, $a['arrival_time']] <=> [$b['arrival_time'] === null, $b['arrival_time']],
                fn ($a, $b) => strcasecmp($a['full_name'], $b['full_name']),
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    public function row(BookingTraveler $t): array
    {
        return [
            'traveler_id' => $t->id,
            'booking_id' => $t->booking_id,
            'booking_reference' => $t->booking?->reference,
            'booking_status' => $t->booking?->status,
            'payment_status' => $t->booking?->payment_status,
            'full_name' => $t->full_name,
            'is_lead' => (bool) $t->is_lead,
            'phone' => $t->phone,
            'email' => $t->email,
            'nationality' => $t->nationality,
            'arrival_date' => $t->arrival_date?->format('Y-m-d'),
            'arrival_time' => $t->arrival_time ? substr((string) $t->arrival_time, 0, 5) : null,
            'arrival_flight' => $t->arrival_flight,
            'arrival_details' => $t->arrival_details,
            'flag_note' => $t->flag_note,
            'has_health_note' => $t->hasHealthNote(),
            'insurance_selected' => (bool) $t->booking?->insurance_selected,
        ];
    }
}
