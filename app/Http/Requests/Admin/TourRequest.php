<?php

namespace App\Http\Requests\Admin;

use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tour = $this->route('tour');

        $slugRule = Rule::unique('tours', 'slug');

        if ($tour instanceof Tour) {
            $slugRule->ignore($tour->id);
        }

        return [
            // Basic information
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', $slugRule],
            'status' => ['required', Rule::in(Tour::STATUSES)],
            'objective_id' => ['nullable', 'integer', 'exists:objectives,id'],
            'destination' => ['nullable', 'string', 'max:255'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:60'],
            'short_description' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string'],
            'highlights' => ['nullable', 'string'],

            // Itinerary
            'days' => ['sometimes', 'array'],
            'days.*.title' => ['required', 'string', 'max:160'],
            'days.*.description' => ['nullable', 'string'],
            'days.*.activities' => ['nullable', 'string'],
            'days.*.meals' => ['nullable', 'string', 'max:120'],
            'days.*.stay' => ['nullable', 'string', 'max:160'],

            // Details
            'inclusions' => ['nullable', 'string'],
            'exclusions' => ['nullable', 'string'],
            'expert_name' => ['nullable', 'string', 'max:160'],
            'expert_role' => ['nullable', 'string', 'max:160'],
            'expert_bio' => ['nullable', 'string'],

            // Pricing and media
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(Tour::CURRENCIES)],
            'hero_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            // SEO
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
        ];
    }
}
