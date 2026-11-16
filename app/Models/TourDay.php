<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDay extends Model
{
    protected $fillable = [
        'day_number',
        'title',
        'description',
        'activities',
        'meals',
        'stay',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}