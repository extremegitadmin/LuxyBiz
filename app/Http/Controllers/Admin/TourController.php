<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourRequest;
use App\Models\Objective;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDay;
use App\Models\TourImage;
use App\Support\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(Tour::STATUSES)],
            'objective_id' => ['nullable', 'integer', 'exists:objectives,id'],
            'tour_category_id' => ['nullable', 'integer', 'exists:tour_categories,id'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $tours = Tour::query()
            ->with(['objective', 'category'])
            ->withCount(['days', 'departures', 'images'])
            ->when($filters['q'] ?? null, fn ($query, $value) =>
                $query->where('title', 'like', '%' . $value . '%')
            )
            ->when($filters['status'] ?? null, fn ($query, $value) =>
                $query->where('status', $value)
            )
            ->when($filters['objective_id'] ?? null, fn ($query, $value) =>
                $query->where('objective_id', $value)
            )
            ->when($filters['tour_category_id'] ?? null, fn ($query, $value) =>
                $query->where('tour_category_id', $value)
            )
            ->when($filters['duration'] ?? null, fn ($query, $value) =>
                $query->where('duration_days', $value)
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.tours.index', [
            'tours' => $tours,
            'objectives' => Objective::orderBy('name')->get(),
            'categories' => TourCategory::orderBy('sort_order')->orderBy('name')->get(),
            'durations' => Tour::query()
                ->select('duration_days')
                ->distinct()
                ->orderBy('duration_days')
                ->pluck('duration_days'),
            'stats' => [
                'total' => Tour::count(),
                'published' => Tour::where('status', 'published')->count(),
                'draft' => Tour::where('status', 'draft')->count(),
                'archived' => Tour::where('status', 'archived')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.tours.form', [
            'tour' => new Tour([
                'status' => 'draft',
                'currency' => 'USD',
                'base_price' => 0,
                'duration_days' => 7,
            ]),
            'objectives' => Objective::orderBy('name')->get(),
            'categories' => TourCategory::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(TourRequest $request)
    {
        $storedPaths = [];

        try {
            $tour = DB::transaction(function () use ($request, &$storedPaths) {
                $data = $this->payload($request);

                if ($request->hasFile('hero_image')) {
                    $path = $request->file('hero_image')->store('tours/hero', 'public');
                    $storedPaths[] = $path;
                    $data['hero_image'] = $path;
                }

                $tour = Tour::create($data);

                $this->storeGalleryImages($request, $tour, $storedPaths);

                return $tour;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        Audit::log('tour.created', $tour);

        return redirect()
            ->route('admin.tours.edit', $tour)
            ->with('success', 'Tour created. Continue adding the itinerary, gallery and departure dates.');
    }

    public function edit(Tour $tour)
    {
        $tour->load(['category', 'objective', 'images']);

        return view('admin.tours.form', [
            'tour' => $tour,
            'objectives' => Objective::orderBy('name')->get(),
            'categories' => TourCategory::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(TourRequest $request, Tour $tour)
    {
        $oldHeroImage = $tour->hero_image;
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $tour, &$storedPaths) {
                $data = $this->payload($request);

                if ($request->hasFile('hero_image')) {
                    $path = $request->file('hero_image')->store('tours/hero', 'public');
                    $storedPaths[] = $path;
                    $data['hero_image'] = $path;
                }

                $tour->update($data);
                $this->storeGalleryImages($request, $tour, $storedPaths);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if ($request->hasFile('hero_image') && $oldHeroImage) {
            Storage::disk('public')->delete($oldHeroImage);
        }

        Audit::log('tour.updated', $tour->fresh());

        return redirect()
            ->route('admin.tours.edit', $tour)
            ->with('success', 'Tour details saved successfully.');
    }

    /**
     * Itinerary is managed separately so editing tour details never deletes
     * and recreates existing day records.
     */
    public function itinerary(Tour $tour)
    {
        $tour->loadCount('days');

        $days = $tour->days()->get();

        return view('admin.tours.itinerary', compact('tour', 'days'));
    }

    public function storeDay(Request $request, Tour $tour)
    {
        $validated = $request->validate($this->dayRules($tour));

        $tour->days()->create($validated);

        Audit::log('tour.itinerary_day_created', $tour, [
            'day_number' => $validated['day_number'],
        ]);

        return redirect()
            ->route('admin.tours.itinerary', $tour)
            ->with('success', 'Itinerary day added.');
    }

    public function updateDay(Request $request, Tour $tour, TourDay $day)
    {
        abort_unless((int) $day->tour_id === (int) $tour->id, 404);

        $validated = $request->validate($this->dayRules($tour, $day));

        $day->update($validated);

        Audit::log('tour.itinerary_day_updated', $tour, [
            'day_id' => $day->id,
        ]);

        return redirect()
            ->route('admin.tours.itinerary', $tour)
            ->with('success', 'Itinerary day updated.');
    }

    public function destroyDay(Tour $tour, TourDay $day)
    {
        abort_unless((int) $day->tour_id === (int) $tour->id, 404);

        $day->delete();

        Audit::log('tour.itinerary_day_deleted', $tour, [
            'day_id' => $day->id,
        ]);

        return redirect()
            ->route('admin.tours.itinerary', $tour)
            ->with('success', 'Itinerary day deleted.');
    }

    public function destroyGalleryImage(Tour $tour, TourImage $image)
    {
        abort_unless((int) $image->tour_id === (int) $tour->id, 404);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        Audit::log('tour.gallery_image_deleted', $tour, [
            'image_id' => $image->id,
        ]);

        return redirect()
            ->route('admin.tours.edit', [$tour, 'tab' => 'gallery'])
            ->with('success', 'Gallery image deleted.');
    }

    /**
     * Archive instead of hard-deleting a tour that may be referenced by bookings.
     */
    public function destroy(Tour $tour)
    {
        $tour->update(['status' => 'archived']);

        Audit::log('tour.archived', $tour);

        return redirect()
            ->route('admin.tours.index')
            ->with('success', 'Tour archived. Existing booking records are preserved.');
    }

    public function preview(Tour $tour)
    {
        $tour->load([
            'category',
            'objective',
            'days',
            'images' => fn ($query) => $query->where('is_active', true),
            'departures' => fn ($query) => $query
                ->where('status', 'open')
                ->whereDate('departs_on', '>=', today())
                ->withSeatsBooked(),
        ]);

        return view('admin.tours.preview', compact('tour'));
    }

    public function togglePublish(Tour $tour)
    {
        if ($tour->status === 'archived') {
            return back()->with('error', 'An archived tour cannot be published. Edit the tour and restore it to draft first.');
        }

        $newStatus = $tour->status === 'published' ? 'draft' : 'published';

        $tour->update(['status' => $newStatus]);

        Audit::log(
            $newStatus === 'published' ? 'tour.published' : 'tour.unpublished',
            $tour
        );

        return back()->with(
            'success',
            $newStatus === 'published' ? 'Tour published.' : 'Tour moved to draft.'
        );
    }

    public function duplicate(Tour $tour)
    {
        $tour->load(['days', 'images']);
        $copiedFiles = [];

        try {
            $copy = DB::transaction(function () use ($tour, &$copiedFiles) {
                $copy = $tour->replicate();
                $copy->title = $tour->title . ' (copy)';
                $copy->slug = $this->uniqueSlug($copy->title);
                $copy->status = 'draft';
                $copy->save();

                // Copy files, not just their paths. Deleting an image from the
                // duplicate must never delete the original tour's image.
                if ($tour->hero_image && Storage::disk('public')->exists($tour->hero_image)) {
                    $extension = pathinfo($tour->hero_image, PATHINFO_EXTENSION);
                    $newHeroPath = 'tours/hero/' . $copy->id . '-' . Str::uuid() . ($extension ? '.' . $extension : '');
                    if (! Storage::disk('public')->copy($tour->hero_image, $newHeroPath)) {
                        throw new \RuntimeException('Unable to copy the tour cover image.');
                    }
                    $copiedFiles[] = $newHeroPath;
                    $copy->update(['hero_image' => $newHeroPath]);
                }

                foreach ($tour->days as $day) {
                    $copy->days()->create($day->only([
                        'day_number',
                        'title',
                        'description',
                        'activities',
                        'meals',
                        'stay',
                    ]));
                }

                foreach ($tour->images as $image) {
                    $newImagePath = $image->image_path;

                    if (Storage::disk('public')->exists($image->image_path)) {
                        $extension = pathinfo($image->image_path, PATHINFO_EXTENSION);
                        $newImagePath = 'tours/gallery/' . $copy->id . '-' . Str::uuid() . ($extension ? '.' . $extension : '');
                        if (! Storage::disk('public')->copy($image->image_path, $newImagePath)) {
                            throw new \RuntimeException('Unable to copy a gallery image.');
                        }
                        $copiedFiles[] = $newImagePath;
                    }

                    $copy->images()->create([
                        'image_path' => $newImagePath,
                        'alt_text' => $image->alt_text,
                        'sort_order' => $image->sort_order,
                        'is_active' => $image->is_active,
                    ]);
                }

                return $copy;
            });
        } catch (Throwable $exception) {
            foreach ($copiedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        Audit::log('tour.duplicated', $copy, ['source_tour_id' => $tour->id]);

        return redirect()
            ->route('admin.tours.edit', $copy)
            ->with('success', 'Tour duplicated as a draft, including separate copies of its images.');
    }

    private function payload(TourRequest $request): array
    {
        $data = $request->safe()->except([
            'hero_image',
            'gallery_images',
        ]);

        $currentTour = $request->route('tour');

        $requestedSlug = $data['slug'] ?? null;

        $data['slug'] = filled($requestedSlug)
            ? Str::slug($requestedSlug)
            : $this->uniqueSlug($data['title'], $currentTour?->id);

        // Existing tours.base_price is NOT NULL; never send null to MySQL.
        $data['base_price'] = $data['base_price'] ?? 0;
        $data['currency'] = $data['currency'] ?? 'USD';

        return $data;
    }

    private function storeGalleryImages(TourRequest $request, Tour $tour, array &$storedPaths): void
    {
        if (! $request->hasFile('gallery_images')) {
            return;
        }

        $nextSortOrder = (int) $tour->images()->max('sort_order') + 1;

        foreach ($request->file('gallery_images') as $index => $file) {
            $path = $file->store('tours/gallery', 'public');
            $storedPaths[] = $path;

            $tour->images()->create([
                'image_path' => $path,
                'alt_text' => $tour->title . ' gallery image ' . ($index + 1),
                'sort_order' => $nextSortOrder++,
                'is_active' => true,
            ]);
        }
    }

    private function dayRules(Tour $tour, ?TourDay $day = null): array
    {
        return [
            'day_number' => [
                'required',
                'integer',
                'min:1',
                'max:365',
                Rule::unique('tour_days', 'day_number')
                    ->where(fn (Builder $query) => $query->where('tour_id', $tour->id))
                    ->ignore($day?->id),
            ],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'activities' => ['nullable', 'string'],
            'meals' => ['nullable', 'string', 'max:1000'],
            'stay' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'tour';
        $slug = $base;
        $number = 2;

        while (
            Tour::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $number++;
        }

        return $slug;
    }
    //delete tour 
    public function permanentDelete(Tour $tour)
{
    // Prevent deleting tours with existing bookings
    if ($tour->bookings()->exists()) {
        return redirect()
            ->route('admin.tours.index')
            ->with('error', 'This tour cannot be deleted because bookings exist.');
    }

    // Delete gallery files from storage
    foreach ($tour->images as $image) {
        if ($image->image_path &&
            \Illuminate\Support\Facades\Storage::disk('public')->exists($image->image_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();
    }

    // Delete hero image
    if ($tour->hero_image &&
        \Illuminate\Support\Facades\Storage::disk('public')->exists($tour->hero_image)) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($tour->hero_image);
    }

    // Delete itinerary
    $tour->days()->delete();

    // Delete tour
    $tour->delete();

    return redirect()
        ->route('admin.tours.index')
        ->with('success', 'Tour permanently deleted.');
}
}
