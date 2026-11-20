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
            'capacity' => 'integer',
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

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeWithSeatsBooked($query)
{
    return $query->addSelect([
        'seats_booked' => \Illuminate\Support\Facades\DB::table('booking_travelers')
            ->join('bookings', 'bookings.id', '=', 'booking_travelers.booking_id')
            ->whereColumn('bookings.tour_departure_id', 'tour_departures.id')
            ->where('bookings.status', 'confirmed')
            ->selectRaw('COUNT(booking_travelers.id)')
    ]);
}
}
