<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer payment link for a booking (Day 29). The token itself is never stored, only its SHA-256 fingerprint. */
class PaymentRequest extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_request_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** open, paid, cancelled or expired. A link that cannot be used right now for another reason is handled by the service. */
    public function status(): string
    {
        if ($this->paid_at) {
            return 'paid';
        }
        if ($this->cancelled_at) {
            return 'cancelled';
        }

        return $this->expires_at->isPast() ? 'expired' : 'open';
    }
}
