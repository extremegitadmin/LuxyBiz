<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per charge or refund. NEVER store card number, CVV or expiry here.
 */
class Payment extends Model
{
    use HasFactory;

    public const TYPES = ['charge', 'refund'];
    public const STATUSES = ['pending', 'succeeded', 'failed', 'refunded'];
    public const CHANNELS = ['offline', 'online', 'csr_link', 'csr_hosted'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeCharges(Builder $query): Builder
    {
        return $query->where('type', 'charge');
    }

    public function scopeRefunds(Builder $query): Builder
    {
        return $query->where('type', 'refund');
    }

    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', 'succeeded');
    }
}
