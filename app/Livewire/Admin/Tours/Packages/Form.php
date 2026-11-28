<?php

namespace App\Livewire\Admin\Tours\Packages;

use App\Models\Tour;
use App\Models\TourPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public Tour $tour;
    public ?TourPackage $package = null;

    public string $name = 'Silver';
    public $price = 0;
    public $description = '';
    public $inclusions = '';
    public $exclusions = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public array $days = [];

    public function mount(Tour $tour, ?TourPackage $package = null): void
    {
        $this->tour = $tour;

        if ($package) {
            abort_unless($package->tour_id === $tour->id, 404);

            $this->package = $package;

            $this->name = $package->name;
            $this->price = $package->price;
            $this->description = $package->description ?? '';
            $this->inclusions = $package->inclusions ?? '';
            $this->exclusions = $package->exclusions ?? '';
            $this->sort_order = $package->sort_order;
            $this->is_active = $package->is_active;

            $this->days = $package->days()
                ->orderBy('day_number')
                ->get()
                ->map(fn ($day) => [
                    'day_number' => $day->day_number,
                    'title' => $day->title,
                    'description' => $day->description ?? '',
                    'activities' => $day->activities ?? '',
                    'meals' => $day->meals ?? '',
                    'stay' => $day->stay ?? '',
                ])
                ->toArray();
        }
    }

    public function addDay(): void
    {
        $this->days[] = [
            'day_number' => count($this->days) + 1,
            'title' => '',
            'description' => '',
            'activities' => '',
            'meals' => '',
            'stay' => '',
        ];
    }

    public function removeDay(int $index): void
    {
        unset($this->days[$index]);

        $this->days = array_values($this->days);

        foreach ($this->days as $key => &$day) {
            $day['day_number'] = $key + 1;
        }

        unset($day);
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                Rule::in(['Silver', 'Gold', 'Platinum']),
                Rule::unique('tour_packages', 'name')
                    ->where(fn ($query) => $query->where('tour_id', $this->tour->id))
                    ->ignore($this->package?->id),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string'],
            'inclusions' => ['nullable', 'string'],
            'exclusions' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],

            'days' => ['array'],
            'days.*.day_number' => ['required', 'integer', 'min:1', 'distinct'],
            'days.*.title' => ['required', 'string', 'max:160'],
            'days.*.description' => ['nullable', 'string'],
            'days.*.activities' => ['nullable', 'string'],
            'days.*.meals' => ['nullable', 'string', 'max:120'],
            'days.*.stay' => ['nullable', 'string', 'max:160'],
        ]);

        DB::transaction(function () use ($validated) {
            $packageData = [
                'name' => $validated['name'],
                'price' => $validated['price'],
                'description' => $validated['description'] ?? null,
                'inclusions' => $validated['inclusions'] ?? null,
                'exclusions' => $validated['exclusions'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? false,
            ];

            if ($this->package) {
                $this->package->update($packageData);
                $package = $this->package;

                $package->days()->delete();
            } else {
                $package = $this->tour->packages()->create($packageData);
            }

            if (!empty($validated['days'])) {
                $package->days()->createMany($validated['days']);
            }
        });

        session()->flash(
            'success',
            $this->package
                ? 'Tour package updated successfully.'
                : 'Tour package created successfully.'
        );

        return redirect()->route(
            'admin.tours.packages.index',
            $this->tour->id
        );
    }

    public function render()
    {
        return view('livewire.admin.tours.packages.form')
            ->extends('admin.layouts.master')
            ->section('content');
    }
}
