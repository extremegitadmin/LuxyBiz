<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourCategory extends Model
{
    use HasFactory;

    protected $table = 'tour_categories';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'tour_category_id');
    }
}
