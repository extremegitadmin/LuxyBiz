<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A request to cancel a booking (Day 16). See the migration for what each column is for. */
class CancellationRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected', 'refunded', 'credited'];
    public const SOURCES = ['customer', 'staff'];

    /** Statuses that mean the request is still open (a booking can have only one open request). */
    public const OPEN = ['pending', 'approved'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date',
            'calculated_refund' => 'decimal:2',
            'final_refund' => 'decimal:2',
            'calculation' => 'array',
            'decided_at' => 'datetime',
            'completed_at' => 'datetime',
            'customer_notified_at' => 'datetime',
        ];
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'CR-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));
        } while (static::query()->where('reference', $ref)->exists());

        return $ref;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN);
    }

    /** The refund that will be paid: the override or approved amount if set, else what the rules calculated. */
    public function refundAmount(): float
    {
        return (float) ($this->final_refund ?? $this->calculated_refund);
    }

    public function isOverridden(): bool
    {
        return $this->final_refund !== null && round((float) $this->final_refund, 2) !== round((float) $this->calculated_refund, 2);
    }

    /** Days the request has waited (pending only). */
    public function waitingDays(): ?int
    {
        return $this->status === 'pending' ? (int) $this->created_at?->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    /** Pending requests for the sidebar badge. Never breaks a page, even before the table exists. */
    public static function pendingCount(): int
    {
        try {
            return static::query()->where('status', 'pending')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
