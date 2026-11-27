<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Support\Audit;
use Carbon\Carbon;

class FulfillmentController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
        ? Carbon::parse($request->date)->toDateString()
        : today()->toDateString();

        // Tour dates that have bookings, for a dropdown if you want one
        $dates = Booking::query()
            ->whereDate('tour_date', '>=', today()->subDays(2))
            ->whereIn('status', ['confirmed', 'paid'])        // adjust to your statuses
            ->selectRaw('DATE(tour_date) as d, COUNT(*) as bookings_count')
            ->groupBy('d')
            ->orderBy('d')
            ->limit(60)
            ->get();

        $bookings = Booking::query()
            ->whereDate('tour_date', $date)
            ->whereIn('status', ['confirmed', 'paid'])
            ->with('travelers')
            ->orderBy('booking_date')
            ->get();

        $travelers = $bookings->flatMap->travelers;

        // if (auth()->user()->can('admin.health.view')
        //     && $travelers->contains(fn ($t) => filled($t->health_notes))) {
        //     Audit::log('manifest.health_viewed', null, ['date' => $date]);
        // }

        return view('admin.fulfillment.index', compact('dates', 'date', 'bookings', 'travelers'));
    }
}
