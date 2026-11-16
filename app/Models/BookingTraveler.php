<?php

namespace App\Models;

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
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
