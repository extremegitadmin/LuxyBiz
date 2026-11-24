<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourPackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TourPackageController extends Controller
{
    /**
     * Display all packages belonging to a tour.
     */
    public function index(Tour $tour)
    {
        $packages = $tour->packages()
            ->withCount('days')
            ->orderBy('sort_order')
            ->get();

        return view('admin.tours.packages.index', compact(
            'tour',
            'packages'
        ));
    }

    /**
     * Show package creation form.
     */
    public function create(Tour $tour)
    {
        $package = new TourPackage();

        return view('admin.tours.packages.form', compact(
            'tour',
            'package'
        ));
    }

    /**
     * Store a new package and its itinerary.
     */
    public function store(Request $request, Tour $tour)
    {
        $validated = $this->validatePackage($request, $tour);

        $days = $validated['days'] ?? [];

        unset($validated['days']);

        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use (
            $tour,
            $validated,
            $days
        ) {
            $package = $tour->packages()->create($validated);

            if (!empty($days)) {
                $package->days()->createMany($days);
            }
        });

        return redirect()
            ->route('admin.tours.packages.index', $tour->id)
            ->with('success', 'Tour package created successfully.');
    }

    /**
     * Show package editing form.
     */
    public function edit(Tour $tour, TourPackage $package)
    {
        abort_unless($package->tour_id === $tour->id, 404);

        $package->load('days');

        return view('admin.tours.packages.form', compact(
            'tour',
            'package'
        ));
    }

    /**
     * Update package and its itinerary.
     */
    public function update(
        Request $request,
        Tour $tour,
        TourPackage $package
    ) {
        abort_unless($package->tour_id === $tour->id, 404);

        $validated = $this->validatePackage(
            $request,
            $tour,
            $package
        );

        $days = $validated['days'] ?? null;

        unset($validated['days']);

        $validated['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use (
            $package,
            $validated,
            $days
        ) {
            $package->update($validated);

            // Update itinerary only when days were submitted.
            if ($days !== null) {
                $package->days()->delete();

                if (!empty($days)) {
                    $package->days()->createMany($days);
                }
            }
        });

        return redirect()
            ->route('admin.tours.packages.index', $tour->id)
            ->with('success', 'Tour package updated successfully.');
    }

    /**
     * Delete a package and its itinerary.
     */
    public function destroy(Tour $tour, TourPackage $package)
    {
        abort_unless($package->tour_id === $tour->id, 404);

        $package->delete();

        return redirect()
            ->route('admin.tours.packages.index', $tour->id)
            ->with('success', 'Tour package deleted successfully.');
    }

    /**
     * Validate package information and itinerary.
     */
    private function validatePackage(
        Request $request,
        Tour $tour,
        ?TourPackage $package = null
    ): array {
        $nameRule = Rule::unique('tour_packages', 'name')
            ->where(function ($query) use ($tour) {
                $query->where('tour_id', $tour->id);
            });

        if ($package) {
            $nameRule->ignore($package->id);
        }

        return $request->validate([
            // Package details
            'name' => [
                'required',
                'string',
                Rule::in(['Silver', 'Gold', 'Platinum']),
                $nameRule,
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'description' => ['nullable', 'string'],
            'inclusions' => ['nullable', 'string'],
            'exclusions' => ['nullable', 'string'],

            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // Package itinerary
            'days' => ['sometimes', 'array'],

            'days.*.day_number' => [
                'required',
                'integer',
                'min:1',
                'distinct',
            ],

            'days.*.title' => [
                'required',
                'string',
                'max:160',
            ],

            'days.*.description' => ['nullable', 'string'],
            'days.*.activities' => ['nullable', 'string'],
            'days.*.meals' => ['nullable', 'string', 'max:120'],
            'days.*.stay' => ['nullable', 'string', 'max:160'],
        ]);
    }
}
