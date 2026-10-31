<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side validation for Edit booking (E3).
 * The route is already protected by the role middleware, so authorize() simply allows it.
 */
class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ids = $this->route('booking')->travelers()->pluck('id')->all();

        return [
            'insurance_selected' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lead_traveler_id' => ['required', Rule::in($ids)],

            'travelers' => ['required', 'array', 'min:1'],
            'travelers.*.id' => ['required', 'distinct', Rule::in($ids)],
            'travelers.*.full_name' => ['required', 'string', 'max:140'],
            'travelers.*.date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'travelers.*.nationality' => ['nullable', 'string', 'max:80'],
            // Blank means "keep the passport number already stored".
            'travelers.*.passport_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\- ]*$/'],
            'travelers.*.passport_expiry' => ['nullable', 'date', 'after:1900-01-01'],
            'travelers.*.email' => ['nullable', 'email:rfc', 'max:160'],
            'travelers.*.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]{6,30}$/'],
            'travelers.*.arrival_date' => ['nullable', 'date'],
            'travelers.*.arrival_flight' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'travelers.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'travelers.*.arrival_details' => ['nullable', 'string', 'max:255'],
            'travelers.*.flag_note' => ['nullable', 'string', 'max:255'],
            'travelers.*.health_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lead_traveler_id.required' => 'Choose which traveler is the lead traveler.',
            'lead_traveler_id.in' => 'The lead traveler must be one of the travelers on this booking.',
            'travelers.*.id.in' => 'One of the travelers does not belong to this booking.',
            'travelers.*.full_name.required' => 'Traveler :position needs a full name.',
            'travelers.*.date_of_birth.before' => 'Traveler :position: date of birth must be in the past.',
            'travelers.*.passport_number.regex' => 'Traveler :position: passport number may contain only letters, numbers, spaces and dashes.',
            'travelers.*.email.email' => 'Traveler :position: enter a valid email address.',
            'travelers.*.phone.regex' => 'Traveler :position: enter a valid phone number (digits, +, spaces, dashes, brackets).',
            'travelers.*.arrival_flight.regex' => 'Traveler :position: flight number may contain only letters, numbers, spaces and dashes.',
            'travelers.*.arrival_time.date_format' => 'Traveler :position: arrival time must look like 14:30.',
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'notes',
            'travelers.*.date_of_birth' => 'date of birth',
            'travelers.*.passport_expiry' => 'passport expiry',
            'travelers.*.arrival_date' => 'arrival date',
        ];
    }
}
