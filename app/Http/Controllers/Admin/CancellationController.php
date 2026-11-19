<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Services\Cancellations\RefundCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cancellation requests: list (F1, Day 16) and detail (F2, Day 17). */
class CancellationController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(CancellationRequest::STATUSES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $base = CancellationRequest::query()
            ->when($f['q'] ?? null, function (Builder $query, string $term) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term)) . '%';
                $query->where(function (Builder $w) use ($like) {
                    $w->where('reference', 'like', $like)
                        ->orWhereHas('booking', function (Builder $b) use ($like) {
                            $b->where('reference', 'like', $like)
                                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                                ->orWhereHas('travelers', fn ($t) => $t->where('full_name', 'like', $like));
                        });
                });
            })
            ->when($f['from'] ?? null, fn (Builder $q, string $v) => $q->whereDate('requested_on', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, string $v) => $q->whereDate('requested_on', '<=', $v));

        // Tab counts follow the search and dates, not the status tab itself.
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $requests = (clone $base)
            ->with(['booking.user', 'booking.leadTraveler', 'booking.tour', 'booking.departure'])
            ->when($f['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")   // waiting requests first
            ->orderBy('requested_on')                                         // oldest waiting first
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.cancellations.index', [
            'requests' => $requests,
            'filters' => $f,
            'counts' => $counts,
        ]);
    }

    /** One request: who, what was asked, the saved refund calculation, and the booking's money. */
    public function show(CancellationRequest $cancellation, RefundCalculator $calculator)
    {
        $cancellation->load(['booking.user', 'booking.leadTraveler', 'booking.tour', 'booking.departure', 'booking.travelers', 'requester', 'decider']);
        $booking = $cancellation->booking;

        // While the request is still open, check whether today's rules or payments would now give another amount.
        // A finished request is history: it keeps the amount it was decided with and is not compared.
        $current = null;
        if ($booking && in_array($cancellation->status, CancellationRequest::OPEN, true)) {
            $current = $calculator->calculate($booking, $cancellation->requested_on);
        }

        return view('admin.cancellations.show', [
            'r' => $cancellation,
            'booking' => $booking,
            'calc' => $cancellation->calculation,          // the snapshot saved when the request was opened (may be null on old rows)
            'current' => $current,
            'changed' => $current && round($current->refund, 2) !== round((float) $cancellation->calculated_refund, 2),
            'payments' => $booking ? $booking->payments()->latest()->latest('id')->get() : collect(),
        ]);
    }
}
