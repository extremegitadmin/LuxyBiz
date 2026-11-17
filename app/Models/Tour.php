<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public const CURRENCIES = ['USD', 'INR', 'EUR', 'GBP'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'price_inr' => 'decimal:2',
            'senior_friendly' => 'boolean',
            'gallery' => 'array',
        ];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'tour_category_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(TourDay::class)->orderBy('day_number');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class)->orderBy('departs_on');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourImage::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Convert a newline-separated database field into a clean list.
     */
    public function lines(string $field): array
    {
        $allowed = ['highlights', 'inclusions', 'exclusions'];

        if (! in_array($field, $allowed, true)) {
            return [];
        }

        $lines = preg_split('/\R/u', (string) $this->{$field}) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines)
        ));
    }
}
