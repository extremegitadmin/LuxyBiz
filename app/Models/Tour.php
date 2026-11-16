<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    public const STATUSES = [
        'draft',
        'published',
        'archived',
    ];

    public const CURRENCIES = [
        'USD',
        'INR',
        'EUR',
        'GBP',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
        ];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
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

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function lines(string $field): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $this->{$field}))
        ));
    }
}