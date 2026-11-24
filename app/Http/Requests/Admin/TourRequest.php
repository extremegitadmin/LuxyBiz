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
            'title' => ['required', 'string', 'max:191'],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slugRule,
            ],

            'tour_category_id' => ['nullable', 'integer', 'exists:tour_categories,id'],
            'objective_id' => ['nullable', 'integer', 'exists:objectives,id'],
            'status' => ['required', Rule::in(Tour::STATUSES)],
            'destination' => ['nullable', 'string', 'max:191'],
            // 'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'duration_days' => ['required', 'string', 'max:50'],

            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'highlights' => ['nullable', 'string'],

            // Itinerary
            // 'days' => ['sometimes', 'array'],
            // 'days.*.title' => ['required', 'string', 'max:160'],
            // 'days.*.description' => ['nullable', 'string'],
            // 'days.*.activities' => ['nullable', 'string'],
            // 'days.*.meals' => ['nullable', 'string', 'max:120'],
            // 'days.*.stay' => ['nullable', 'string', 'max:160'],

            // Details
            // 'inclusions' => ['nullable', 'string'],
            // 'exclusions' => ['nullable', 'string'],
            'expert_name' => ['nullable', 'string', 'max:191'],
            'expert_role' => ['nullable', 'string', 'max:191'],
            'expert_bio' => ['nullable', 'string'],

            // Pricing
            // 'base_price' => ['required', 'numeric', 'min:0'],
            // 'currency' => ['required', Rule::in(Tour::CURRENCIES)],

            // Main image
            'hero_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // Gallery images
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // SEO
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'base_price.required' => 'Enter a base price. Use 0 only if this is intentionally a free tour.',
            'slug.regex' => 'The URL slug may contain lowercase letters, numbers and hyphens only.',
            'gallery_images.max' => 'You can upload a maximum of 12 gallery images at a time.',
        ];
    }
}
