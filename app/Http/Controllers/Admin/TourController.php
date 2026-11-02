<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourRequest;
use App\Models\Objective;
use App\Models\Tour;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $tours = Tour::with('objective')
            ->withCount(['days', 'departures'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->q . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('objective_id'), fn ($q) => $q->where('objective_id', $request->objective_id))
            ->when($request->filled('duration'), fn ($q) => $q->where('duration_days', $request->duration))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tours.index', [
            'tours' => $tours,
            'objectives' => Objective::orderBy('name')->get(),
            'durations' => Tour::select('duration_days')->distinct()->orderBy('duration_days')->pluck('duration_days'),
        ]);
    }

    public function create()
    {
        return view('admin.tours.form', [
            'tour' => new Tour(['status' => 'draft', 'currency' => 'USD', 'duration_days' => 7]),
            'objectives' => Objective::orderBy('name')->get(),
        ]);
    }

    public function store(TourRequest $request)
    {
        $tour = DB::transaction(function () use ($request) {
            $tour = Tour::create($this->payload($request));
            $this->syncDays($tour, $request->input('days', []));

            return $tour;
        });

        Audit::log('tour.created', $tour);

        return redirect()->route('admin.tours.edit', $tour)->with('success', 'Tour created.');
    }

    public function edit(Tour $tour)
    {
        $tour->load('days');

        return view('admin.tours.form', [
            'tour' => $tour,
            'objectives' => Objective::orderBy('name')->get(),
        ]);
    }

    public function update(TourRequest $request, Tour $tour)
    {
        $oldPrice = $tour->base_price;
        $oldImage = $tour->hero_image;

        DB::transaction(function () use ($request, $tour) {
            $tour->update($this->payload($request));
            $this->syncDays($tour, $request->input('days', []));
        });

        if ($request->hasFile('hero_image') && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        if ((float) $oldPrice !== (float) $tour->base_price) {
            Audit::log('tour.price_changed', $tour, ['from' => $oldPrice, 'to' => $tour->base_price]);
        }
        Audit::log('tour.updated', $tour);

        return back()->with('success', 'Tour saved.');
    }

    /** Archive instead of deleting, so past bookings keep their tour. */
    public function destroy(Tour $tour)
    {
        $tour->update(['status' => 'archived']);
        Audit::log('tour.archived', $tour);

        return redirect()->route('admin.tours.index')->with('success', 'Tour archived.');
    }

    /** Draft-safe preview of how the public tour page will read. */
    public function preview(Tour $tour)
    {
        $tour->load(['days', 'objective', 'departures' => fn ($q) => $q->where('status', 'open')->whereDate('departs_on', '>=', today())->withSeatsBooked()]);

        return view('admin.tours.preview', compact('tour'));
    }

    public function togglePublish(Tour $tour)
    {
        $new = $tour->status === 'published' ? 'draft' : 'published';
        $tour->update(['status' => $new]);
        Audit::log('tour.' . ($new === 'published' ? 'published' : 'unpublished'), $tour);

        return back()->with('success', $new === 'published' ? 'Tour is now live.' : 'Tour moved to draft.');
    }

    public function duplicate(Tour $tour)
    {
        $copy = DB::transaction(function () use ($tour) {
            $copy = $tour->replicate();
            $copy->title = $tour->title . ' (copy)';
            $copy->slug = $this->uniqueSlug($copy->title);
            $copy->status = 'draft';
            $copy->save();

            foreach ($tour->days as $day) {
                $copy->days()->create($day->only(['day_number', 'title', 'description', 'activities', 'meals', 'stay']));
            }

            return $copy;
        });

        Audit::log('tour.duplicated', $copy, ['from' => $tour->id]);

        return redirect()->route('admin.tours.edit', $copy)->with('success', 'Copy created as a draft.');
    }

    private function payload(TourRequest $request): array
    {
        $data = $request->safe()->except(['days', 'hero_image']);
        $current = $request->route('tour');

        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : ($current?->slug ?: $this->uniqueSlug($data['title']));

        if ($request->hasFile('hero_image')) {
            $data['hero_image'] = $request->file('hero_image')->store('tours', 'public');
        }

        return $data;
    }

    private function syncDays(Tour $tour, array $days): void
    {
        $tour->days()->delete();

        foreach (array_values($days) as $i => $day) {
            $tour->days()->create([
                'day_number' => $i + 1,
                'title' => $day['title'],
                'description' => $day['description'] ?? null,
                'activities' => $day['activities'] ?? null,
                'meals' => $day['meals'] ?? null,
                'stay' => $day['stay'] ?? null,
            ]);
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'tour';
        $slug = $base;
        $n = 2;

        while (Tour::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
