<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
