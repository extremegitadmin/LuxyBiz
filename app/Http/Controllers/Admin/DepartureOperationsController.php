<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartureOperationsController extends Controller
{
    /**
     * Display all departures with confirmed traveler counts.
     */
    public function index(Request $request)
    {
        $tours = Tour::where('status', '!=', 'archived')
            ->orderBy('title')
            ->get(['id', 'title']);

        $departures = TourDeparture::query()
            ->with('tour:id,title,currency,base_price')
            ->withSeatsBooked()
            ->when($request->filled('tour_id'), function ($query) use ($request) {
                $query->where('tour_id', $request->tour_id);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when(! $request->boolean('past'), function ($query) {
                $query->whereDate('departs_on', '>=', today());
            })
            ->orderBy('departs_on')
            ->paginate(15)
            ->withQueryString();

        return view('admin.departure-operations.index', compact(
            'departures',
            'tours'
        ));
    }

    /**
     * Display departure operations and confirmed travelers.
     */
    public function show(TourDeparture $departure)
    {
        $departure->load('tour:id,title,currency,base_price');

        $seatsBooked = $this->confirmedTravelerCount($departure);

        $seatsLeft = max(
            0,
            (int) $departure->capacity - $seatsBooked
        );

        $travelers = DB::table('booking_travelers')
            ->join(
                'bookings',
                'bookings.id',
                '=',
                'booking_travelers.booking_id'
            )
            ->where('bookings.tour_departure_id', $departure->id)
            ->where('bookings.status', 'confirmed')
            ->select(
                'booking_travelers.id',
                'booking_travelers.full_name',
                'booking_travelers.email',
                'booking_travelers.phone',
                'booking_travelers.arrival_details',
                'booking_travelers.flag_note',
                'bookings.reference',
                'bookings.payment_status'
            )
            ->orderBy('booking_travelers.full_name')
            ->paginate(20);

        return view('admin.departure-operations.show', compact(
            'departure',
            'travelers',
            'seatsBooked',
            'seatsLeft'
        ));
    }

    /**
     * Close or reopen departure sales.
     */
    public function toggleSales(TourDeparture $departure)
    {
        if ($departure->status === 'open') {
            $departure->update(['status' => 'closed']);

            Audit::log('departure.sales_closed', $departure);

            return back()->with(
                'success',
                'Departure sales closed successfully.'
            );
        }

        $seatsBooked = $this->confirmedTravelerCount($departure);

        $seatsLeft = (int) $departure->capacity - $seatsBooked;

        if ($seatsLeft <= 0) {
            return back()->with(
                'error',
                'Cannot reopen sales. This departure is fully booked.'
            );
        }

        if ($departure->departs_on->isPast()) {
            return back()->with(
                'error',
                'Cannot reopen sales for a past departure.'
            );
        }

        $departure->update(['status' => 'open']);

        Audit::log('departure.sales_reopened', $departure);

        return back()->with(
            'success',
            'Departure sales reopened successfully.'
        );
    }

    /**
     * Display a printable traveler manifest.
     */
    public function manifest(TourDeparture $departure)
    {
        $departure->load('tour:id,title,currency');

        $travelers = DB::table('booking_travelers')
            ->join(
                'bookings',
                'bookings.id',
                '=',
                'booking_travelers.booking_id'
            )
            ->where('bookings.tour_departure_id', $departure->id)
            ->where('bookings.status', 'confirmed')
            ->select(
                'booking_travelers.full_name',
                'booking_travelers.email',
                'booking_travelers.phone',
                'booking_travelers.arrival_details',
                'booking_travelers.flag_note',
                'bookings.reference',
                'bookings.payment_status'
            )
            ->orderBy('booking_travelers.full_name')
            ->get();

        $seatsBooked = $travelers->count();

        return view('admin.departure-operations.manifest', compact(
            'departure',
            'travelers',
            'seatsBooked'
        ));
    }

    /**
     * Count individual traveler records from confirmed bookings.
     */
    private function confirmedTravelerCount(TourDeparture $departure): int
    {
        return DB::table('booking_travelers')
            ->join(
                'bookings',
                'bookings.id',
                '=',
                'booking_travelers.booking_id'
            )
            ->where('bookings.tour_departure_id', $departure->id)
            ->where('bookings.status', 'confirmed')
            ->count('booking_travelers.id');
    }
}
