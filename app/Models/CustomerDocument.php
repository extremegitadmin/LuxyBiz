<?php

namespace App\Models;

use App\Services\Documents\DocumentStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    use HasFactory;

    /** Document types and the label staff see. */
    public const TYPES = [
        'passport' => 'Passport',
        'visa' => 'Visa',
        'id_card' => 'ID card',
        'insurance' => 'Travel insurance',
        'flight_ticket' => 'Flight ticket',
        'other' => 'Other',
    ];

    protected $guarded = [];

    /** The path and the disk never leave the server, even if a document is turned into JSON by mistake. */
    protected $hidden = ['stored_path', 'disk', 'sha256'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(BookingTraveler::class, 'booking_traveler_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1) . ' MB'
            : number_format(max(1, $this->size / 1024), 0) . ' KB';
    }

    protected static function booted(): void
    {
        // Deleting a document row also deletes its file, so no orphan is left on the disk.
        static::deleted(function (self $doc) {
            DocumentStorage::forget($doc);
        });
    }
}
