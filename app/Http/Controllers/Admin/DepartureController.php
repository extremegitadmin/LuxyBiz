<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartureController extends Controller
{
    public function index(Request $request)
    {
        $departures = TourDeparture::with('tour:id,title,currency,base_price')
            ->withSeatsBooked()
            ->when($request->filled('tour_id'), fn ($q) => $q->where('tour_id', $request->tour_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when(! $request->boolean('past'), fn ($q) => $q->whereDate('departs_on', '>=', today()))
            ->orderBy('departs_on', $request->boolean('past') ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.departures.index', [
            'departures' => $departures,
            'tours' => Tour::where('status', '!=', 'archived')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function show(TourDeparture $departure)
    {
        $departure->load('tour:id,title,currency,base_price');

        $bookings = $departure->bookings()->where('status', '!=', 'cancelled')
            ->with(['user:id,name,email', 'travelers'])->orderBy('created_at')->get();

        return view('admin.departures.show', [
            'departure' => $departure,
            'bookings' => $bookings,
            'booked' => (int) $bookings->sum('travelers_count'),
        ]);
    }

    public function toggle(TourDeparture $departure)
    {
        $departure->update(['status' => $departure->status === 'open' ? 'closed' : 'open']);
        Audit::log('departure.' . ($departure->status === 'open' ? 'reopened' : 'closed'), $departure);

        return back()->with('success', $departure->status === 'open' ? 'Departure reopened for booking.' : 'Departure closed. No new bookings can be made.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tour_id' => ['required', 'exists:tours,id'],
            'departs_on' => ['required', 'date', 'after_or_equal:today'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $departure = TourDeparture::create($data + ['status' => 'open']);
        Audit::log('departure.created', $departure);

        return back()->with('success', 'Departure added.');
    }

    public function update(Request $request, TourDeparture $departure)
    {
        $data = $request->validate([
            'departs_on' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $booked = (int) $departure->bookings()->where('status', '!=', 'cancelled')->sum('travelers_count');
        if ($data['capacity'] < $booked) {
            return back()->with('error', "Capacity cannot be lower than the {$booked} seats already booked.");
        }

        $departure->update($data);
        Audit::log('departure.updated', $departure);

        return back()->with('success', 'Departure saved.');
    }

    public function destroy(TourDeparture $departure)
    {
        if ($departure->bookings()->exists()) {
            return back()->with('error', 'This departure has bookings. Close it instead of deleting it.');
        }

        Audit::log('departure.deleted', $departure, ['tour_id' => $departure->tour_id, 'date' => $departure->departs_on->toDateString()]);
        $departure->delete();

        return back()->with('success', 'Departure deleted.');
    }
}
