<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Services\Cancellations\CancellationCompletionService;
use App\Services\Cancellations\CancellationPolicy;
use App\Services\Cancellations\RefundCalculator;
use App\Support\SafeAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cancellation requests: list (F1, Day 16), detail (F2, Day 17), refund override (Day 18), approve/reject (Day 19), refunded/credit (Day 20). */
class CancellationController extends Controller
{
    /** Values used for any key an older or partial snapshot does not have, so the detail page never breaks. */
    private const EMPTY_CALC = [
        'currency' => null, 'days_before' => null, 'percent' => 0, 'total' => 0, 'refundable_base' => 0, 'fixed_fee' => 0,
        'entitlement' => 0, 'paid' => 0, 'retained' => 0, 'refund' => 0, 'credit' => 0, 'credit_valid_until' => null,
        'needs_review' => false, 'policy_confirmed' => false, 'notes' => [], 'warnings' => [], 'tiers' => [],
    ];

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
    public function show(CancellationRequest $cancellation, RefundCalculator $calculator, CancellationCompletionService $completion)
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
            'calc' => $cancellation->calculation ? array_replace(self::EMPTY_CALC, $cancellation->calculation) : null,   // the snapshot saved when the request was opened; missing keys filled in
            'current' => $current,
            'changed' => $current && round($current->refund, 2) !== round((float) $cancellation->calculated_refund, 2),
            'payments' => $booking ? $booking->payments()->latest()->latest('id')->get() : collect(),
            'maxRefund' => $booking ? max(0, round($booking->paidAmount(), 2)) : 0,
            'outstanding' => $cancellation->status === 'approved' ? $completion->outstanding($cancellation) : null,
            'methods' => CancellationCompletionService::METHODS,
            'policy' => CancellationPolicy::current(),
            'credit' => Schema::hasTable('travel_credits') ? DB::table('travel_credits')->where('cancellation_request_id', $cancellation->id)->first() : null,
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
                ...$row->auditContext(),
                'calculated' => (float) $row->calculated_refund, 'from' => $before, 'to' => $amount, 'reason' => $reason,
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
                ...$row->auditContext(), 'from' => $before, 'to' => (float) $row->calculated_refund,
            ]);
        });

        if ($error) {
            return back()->withErrors(['amount' => $error]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with('success', 'Override removed. The refund is the calculated amount again.');
    }

    /** Approve: fixes the refund amount and emails the customer. No money moves yet. */
    public function approve(Request $request, CancellationRequest $cancellation, CancellationDecisionService $decisions)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'acknowledged' => ['nullable', 'boolean'],
        ]);

        try {
            $row = $decisions->approve($cancellation, $request->user(), $data['note'] ?? null, $request->boolean('acknowledged'));
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with($row->notification_error ? 'warning' : 'success',
            $row->notification_error ? 'Approved, but the customer was not told: ' . $row->notification_error : 'Approved. The customer has been emailed.');
    }

    /** Reject: needs a reason, which the customer sees. The booking goes back to how it was. */
    public function reject(Request $request, CancellationRequest $cancellation, CancellationDecisionService $decisions)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Write the reason for rejecting. The customer will see it.']);

        try {
            $row = $decisions->reject($cancellation, $request->user(), $data['reason']);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['reject_reason' => $e->getMessage()]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with($row->notification_error ? 'warning' : 'success',
            $row->notification_error ? 'Rejected, but the customer was not told: ' . $row->notification_error : 'Rejected. The customer has been emailed.');
    }

    public function resendEmail(Request $request, CancellationRequest $cancellation, CancellationDecisionService $decisions)
    {
        try {
            $ok = $decisions->resend($cancellation, $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with($ok ? 'success' : 'warning',
            $ok ? 'The email was sent again.' : ($cancellation->fresh()->notification_error ?? 'The email could not be sent.'));
    }

    /** Record that the approved refund was paid, and cancel the booking. */
    public function markRefunded(Request $request, CancellationRequest $cancellation, CancellationCompletionService $completion)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(CancellationCompletionService::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'refunded_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . ($cancellation->decided_at?->toDateString() ?? '2000-01-01')],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'method.required' => 'Choose how the refund was paid.',
            'refunded_on.before_or_equal' => 'The refund date cannot be in the future.',
            'refunded_on.after_or_equal' => 'The refund date cannot be before the request was approved.',
        ], ['refunded_on' => 'refund date']);

        try {
            $completion->markRefunded($cancellation, $request->user(), $data);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['completion' => $e->getMessage()]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with('success', 'Marked as refunded. The booking is cancelled.');
    }

    /** End the request as travel credit instead of a refund. */
    public function issueCredit(Request $request, CancellationRequest $cancellation, CancellationCompletionService $completion)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $code = $completion->issueCredit($cancellation, $request->user(), $data['note'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['completion' => $e->getMessage()]);
        }

        return redirect()->route('admin.cancellations.show', $cancellation)->with('success', 'Travel credit ' . $code . ' issued. The booking is cancelled.');
    }
}
