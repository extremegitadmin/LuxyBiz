<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourPackageDay extends Model
{
    protected $fillable = [
        'tour_package_id',
        'day_number',
        'title',
        'description',
        'activities',
        'meals',
        'stay',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }
}
