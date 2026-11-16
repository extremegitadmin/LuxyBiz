<?php

namespace App\Models;

use App\Services\BookingChangeTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Before/after history of an Edit booking (E3). Sensitive values are never stored (see BookingChangeTracker). */
class BookingChange extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** Human-readable lines for the history list, e.g. "Traveler Jane Doe: Email: a@x.com → b@x.com". */
    public function summaryLines(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $lines = [];

        foreach ($new['booking'] ?? [] as $field => $value) {
            $label = BookingChangeTracker::BOOKING_LABELS[$field] ?? $field;
            $lines[] = $label . ': ' . $this->show($field, $old['booking'][$field] ?? null) . ' → ' . $this->show($field, $value);
        }

        foreach ($new['travelers'] ?? [] as $id => $fields) {
            $name = $fields['_name'] ?? ('#' . $id);
            foreach ($fields as $field => $value) {
                if ($field === '_name') {
                    continue;
                }
                $label = BookingChangeTracker::TRAVELER_LABELS[$field] ?? $field;
                $lines[] = 'Traveler ' . $name . ': ' . $label . ': '
                    . $this->show($field, $old['travelers'][$id][$field] ?? null) . ' → ' . $this->show($field, $value);
            }
        }

        return $lines;
    }

    private function show(string $field, mixed $value): string
    {
        if (is_bool($value) || $field === 'insurance_selected' || $field === 'is_lead') {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || $value === '') {
            return 'empty';
        }
        if ($field === 'notes' || $field === 'arrival_details' || $field === 'flag_note') {
            return \Illuminate\Support\Str::limit((string) $value, 60);
        }

        return (string) $value;
    }
}
