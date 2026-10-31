<?php

namespace App\Http\Requests\Admin;

use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tours.manage') ?? false;
    }

    public function rules(): array
    {
        $tourId = $this->route('tour')?->id;

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'alpha_dash', 'max:180', Rule::unique('tours', 'slug')->ignore($tourId)],
            'objective_id' => ['nullable', 'exists:objectives,id'],
            'destination' => ['nullable', 'string', 'max:120'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:60'],
            'short_description' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:20000'],
            'highlights' => ['nullable', 'string', 'max:3000'],
            'inclusions' => ['nullable', 'string', 'max:3000'],
            'exclusions' => ['nullable', 'string', 'max:3000'],
            'expert_name' => ['nullable', 'string', 'max:120'],
            'expert_role' => ['nullable', 'string', 'max:120'],
            'expert_bio' => ['nullable', 'string', 'max:3000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', Rule::in(Tour::CURRENCIES)],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(Tour::STATUSES)],

            'days' => ['nullable', 'array', 'max:60'],
            'days.*.title' => ['required', 'string', 'max:160'],
            'days.*.description' => ['nullable', 'string', 'max:5000'],
            'days.*.activities' => ['nullable', 'string', 'max:2000'],
            'days.*.meals' => ['nullable', 'string', 'max:120'],
            'days.*.stay' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function attributes(): array
    {
        return ['days.*.title' => 'day title', 'duration_days' => 'duration'];
    }
}
