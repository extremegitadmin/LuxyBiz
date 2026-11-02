<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourDeparture extends Model
{
    protected $fillable = [
        'tour_id',
        'departs_on',
        'capacity',
        'price_override',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'departs_on' => 'date',
            'price_override' => 'decimal:2',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeWithSeatsBooked($query)
    {
        return $query->withSum([
            'bookings as seats_booked' => fn ($q) =>
                $q->where('status', '!=', 'cancelled')
        ], 'travelers_count');
    }
}