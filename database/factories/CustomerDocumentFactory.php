<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\CustomerDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CustomerDocument> */
class CustomerDocumentFactory extends Factory
{
    protected $model = CustomerDocument::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'booking_traveler_id' => null,
            'type' => 'passport',
            'label' => null,
            'original_name' => 'passport.pdf',
            'stored_path' => 'bookings/1/' . Str::lower((string) Str::ulid()) . '.pdf',
            'disk' => 'documents',
            'mime' => 'application/pdf',
            'size' => 120000,
            'sha256' => hash('sha256', Str::random(20)),
            'uploaded_by' => null,
        ];
    }
}
