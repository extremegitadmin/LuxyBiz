<?php

namespace App\Models;

use App\Casts\ProtectedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTraveler extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** Sensitive fields never appear when a traveler is converted to JSON / arrays. */
    protected $hidden = ['passport_number', 'health_notes'];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'date_of_birth' => 'date',
            'passport_expiry' => 'date',
            'arrival_date' => 'date',
            'passport_number' => 'encrypted',   // stored encrypted in the database
            'health_notes' => ProtectedText::class,   // stored encrypted in the database (Day 24)
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * True when a health note exists. Safe for the manifest and the fulfillment desk: it never exposes the text.
     * The text itself is restricted data (Days 24 and 25 add the restricted view and the access log).
     */
    public function hasHealthNote(): bool
    {
        return filled($this->attributes['health_notes'] ?? null);
    }
}
