<?php

namespace App\Services;

use App\Models\Booking;

/**
 * Edit booking (E3) change history.
 *
 * snapshot() reads the editable data of a booking, diff() compares two snapshots.
 * Sensitive fields (passport number, health notes) are compared by a hash, so the history
 * records THAT they changed but never WHAT they were. Plain values never reach booking_changes.
 */
class BookingChangeTracker
{
    public const SENSITIVE = ['passport_number', 'health_notes'];

    public const BOOKING_LABELS = [
        'insurance_selected' => 'Travel insurance',
        'notes' => 'Internal notes',
    ];

    public const TRAVELER_LABELS = [
        'is_lead' => 'Lead traveler',
        'full_name' => 'Full name',
        'date_of_birth' => 'Date of birth',
        'nationality' => 'Nationality',
        'passport_number' => 'Passport number',
        'passport_expiry' => 'Passport expiry',
        'email' => 'Email',
        'phone' => 'Phone',
        'arrival_date' => 'Arrival date',
        'arrival_flight' => 'Arrival flight',
        'arrival_time' => 'Arrival time',
        'arrival_details' => 'Arrival details',
        'flag_note' => 'Traveler flag',
        'health_notes' => 'Health notes',
    ];

    /** What the history stores in place of a sensitive value. */
    public const HIDDEN = '[changed, value not stored]';

    /** @return array{booking: array, travelers: array} */
    public function snapshot(Booking $booking): array
    {
        $booking = $booking->fresh(['travelers']);

        $travelers = [];
        foreach ($booking->travelers as $t) {
            $row = ['_name' => $t->full_name];
            foreach (array_keys(self::TRAVELER_LABELS) as $field) {
                $row[$field] = $this->normalize($field, $t->{$field});
            }
            $travelers[$t->id] = $row;
        }

        return [
            'booking' => [
                'insurance_selected' => (bool) $booking->insurance_selected,
                'notes' => $this->normalize('notes', $booking->notes),
            ],
            'travelers' => $travelers,
        ];
    }

    /**
     * Compare two snapshots and return only what changed.
     *
     * @return array{old: array, new: array, sensitive: array<int, string>}
     */
    public function diff(array $before, array $after): array
    {
        $old = ['booking' => [], 'travelers' => []];
        $new = ['booking' => [], 'travelers' => []];
        $sensitive = [];

        foreach ($after['booking'] as $field => $value) {
            if (($before['booking'][$field] ?? null) !== $value) {
                $old['booking'][$field] = $before['booking'][$field] ?? null;
                $new['booking'][$field] = $value;
            }
        }

        foreach ($after['travelers'] as $id => $row) {
            foreach (self::TRAVELER_LABELS as $field => $label) {
                $was = $before['travelers'][$id][$field] ?? null;
                $now = $row[$field] ?? null;
                if ($was === $now) {
                    continue;
                }

                $isSensitive = in_array($field, self::SENSITIVE, true);
                $old['travelers'][$id]['_name'] = $before['travelers'][$id]['_name'] ?? $row['_name'];
                $new['travelers'][$id]['_name'] = $row['_name'];
                $old['travelers'][$id][$field] = $isSensitive ? self::HIDDEN : $was;
                $new['travelers'][$id][$field] = $isSensitive ? self::HIDDEN : $now;

                if ($isSensitive) {
                    $sensitive[] = $field;
                }
            }
        }

        return [
            'old' => $old,
            'new' => $new,
            'sensitive' => array_values(array_unique($sensitive)),
        ];
    }

    public function isEmpty(array $diff): bool
    {
        return empty($diff['new']['booking']) && empty($diff['new']['travelers']);
    }

    /** Make values comparable: '' and null are the same, dates as Y-m-d, times as H:i, secrets as hashes. */
    private function normalize(string $field, mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            return $field === 'is_lead' ? false : null;
        }
        if ($field === 'is_lead') {
            return (bool) $value;
        }
        if ($field === 'arrival_time') {
            return substr((string) $value, 0, 5);
        }
        if (in_array($field, self::SENSITIVE, true)) {
            return hash('sha256', (string) $value);
        }

        return $value;
    }
}
