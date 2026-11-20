<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Services\Cancellations\RefundCalculator;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cancellation requests: list (F1, Day 16), detail (F2, Day 17), refund override (Day 18). */
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
            'maxRefund' => $booking ? max(0, round($booking->paidAmount(), 2)) : 0,
        ]);
    }

    /**
     * Change the refund amount by hand. Only while the request is open, never above what the customer has paid,
     * and always with a reason. The calculated amount stays on the request, so both can be seen side by side.
     */
    public function override(Request $request, CancellationRequest $cancellation)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'amount.decimal' => 'The amount can have at most 2 decimal places.',
            'reason.required' => 'Write the reason for changing the refund.',
            'reason.min' => 'The reason is too short. Explain why the refund is changed (at least 10 characters).',
        ], ['amount' => 'refund amount', 'reason' => 'reason']);

        $reason = trim(preg_replace('/\s+/u', ' ', strip_tags($data['reason'])));
        if (mb_strlen($reason) < 10) {
            return back()->withInput()->withErrors(['reason' => 'The reason is too short. Explain why the refund is changed (at least 10 characters).']);
        }

        $error = null;
        DB::transaction(function () use ($cancellation, $data, $reason, &$error) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($cancellation->id);
            $booking = $row->booking;

            if (! in_array($row->status, CancellationRequest::OPEN, true)) {
                $error = 'This request is already ' . $row->status . ', so the refund can no longer be changed.';

                return;
            }

            $amount = round((float) $data['amount'], 2);
            $paid = max(0, round((float) $booking->paidAmount(), 2));
            if ($amount > $paid) {
                $error = 'The refund cannot be more than the customer has paid (' . $row->currency . ' ' . number_format($paid, 2) . ').';

                return;
            }
            if ($amount === round((float) $row->calculated_refund, 2)) {
                $error = 'That is the same as the calculated refund. Use "Reset to calculated" if you want to remove an override.';

                return;
            }

            $before = $row->refundAmount();
            $row->update([
                'final_refund' => $amount,
                'override_reason' => $reason,
                'overridden_by' => auth()->id(),
            ]);

            SafeAudit::log('cancellation.refund_overridden', 'Refund of ' . $row->reference . ' changed', [
                'subject_id' => $row->id, 'subject_type' => 'CancellationRequest',
                'calculated' => (float) $row->calculated_refund, 'from' => $before, 'to' => $amount, 'currency' => $row->currency,
            ]);
        });

        if ($error) {
            return back()->withInput()->withErrors(['amount' => $error]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with('success', 'Refund changed. The reason is saved on the request.');
    }

    /** Remove the override: the refund goes back to the calculated amount. */
    public function resetOverride(CancellationRequest $cancellation)
    {
        $error = null;
        DB::transaction(function () use ($cancellation, &$error) {
            $row = CancellationRequest::query()->lockForUpdate()->findOrFail($cancellation->id);

            if (! in_array($row->status, CancellationRequest::OPEN, true)) {
                $error = 'This request is already ' . $row->status . ', so the refund can no longer be changed.';

                return;
            }
            if ($row->final_refund === null) {
                $error = 'There is no override to remove.';

                return;
            }

            $before = $row->refundAmount();
            $row->update(['final_refund' => null, 'override_reason' => null, 'overridden_by' => null]);

            SafeAudit::log('cancellation.override_removed', 'Override of ' . $row->reference . ' removed', [
                'subject_id' => $row->id, 'subject_type' => 'CancellationRequest', 'from' => $before, 'to' => (float) $row->calculated_refund, 'currency' => $row->currency,
            ]);
        });

        if ($error) {
            return back()->withErrors(['amount' => $error]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with('success', 'Override removed. The refund is the calculated amount again.');
    }
}
