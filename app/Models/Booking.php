<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Owner: Asha (Bookings, Payments & Cancellations).
 */
class Booking extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'cancellation_requested', 'cancelled', 'completed'];
    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid', 'refunded'];
    public const SOURCES = ['web', 'csr'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'insurance_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'insurance_selected' => 'boolean',
        ];
    }

    /** Unique reference such as LB-261103-AB12. */
    public static function generateReference(): string
    {
        do {
            $ref = 'LB-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    // ---- Relations -------------------------------------------------------

    /** The customer. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Staff member who created a CSR-assisted booking. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Works once Sini's Tour model is merged to develop. */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** Works once Sini's TourDeparture model is merged to develop. */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(BookingTraveler::class);
    }

    public function leadTraveler(): HasOne
    {
        return $this->hasOne(BookingTraveler::class)->where('is_lead', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(BookingChange::class)->latest();
    }

    // ---- Money helpers ---------------------------------------------------

    /** Net amount the customer has paid: successful charges minus successful refunds. */
    public function paidAmount(): float
    {
        $charged = (float) $this->payments()->where('type', 'charge')->where('status', 'succeeded')->sum('amount');
        $refunded = (float) $this->payments()->where('type', 'refund')->where('status', 'succeeded')->sum('amount');

        return round($charged - $refunded, 2);
    }

    /** Recalculate payment_status from the payment rows. Call after recording a payment or refund. */
    public function syncPaymentStatus(): void
    {
        $charged = (float) $this->payments()->where('type', 'charge')->where('status', 'succeeded')->sum('amount');
        $refunded = (float) $this->payments()->where('type', 'refund')->where('status', 'succeeded')->sum('amount');
        $net = round($charged - $refunded, 2);

        $status = match (true) {
            $charged > 0 && $net <= 0 && $refunded > 0 => 'refunded',
            $net > 0 && $net >= (float) $this->total_amount => 'paid',
            $net > 0 => 'partial',
            default => 'unpaid',
        };

        $this->update(['payment_status' => $status]);
    }
}
