<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payments list (E4) and payment detail (E5). Money is charged and refunded elsewhere; these pages only read. */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $payments = $this->listQuery($filters)
            ->with('booking:id,reference,user_id', 'booking.user:id,name')
            ->latest('payments.created_at')->latest('payments.id')
            ->paginate(20)->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'totals' => $this->totals($filters),
            'methods' => Payment::query()->whereNotNull('method')->distinct()->orderBy('method')->pluck('method'),
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load(['booking.user:id,name,email', 'recorder:id,name']);
        $booking = $payment->booking;

        return view('admin.payments.show', [
            'payment' => $payment,
            'booking' => $booking,
            'timeline' => $this->timeline($payment),
            'siblings' => $booking
                ? $booking->payments()->where('id', '!=', $payment->id)->orderByDesc('created_at')->orderByDesc('id')->get()
                : collect(),
            'net' => $booking?->paidAmount(),
        ]);
    }

    /**
     * Status timeline built from the payment row itself. Gateway webhook events get their own
     * log on Day 9, so nothing here depends on a gateway being connected.
     *
     * @return array<int, array{label: string, at: ?\Illuminate\Support\Carbon, tone: string, detail: ?string}>
     */
    private function timeline(Payment $p): array
    {
        $isRefund = $p->type === 'refund';
        $steps = [[
            'label' => $isRefund ? 'Refund recorded' : 'Payment recorded',
            'at' => $p->created_at,
            'tone' => 'secondary',
            'detail' => $p->recorder ? 'by ' . $p->recorder->name : ($p->channel === 'online' ? 'by the customer, online' : null),
        ]];

        $steps[] = match ($p->status) {
            'succeeded' => ['label' => $isRefund ? 'Refund completed' : 'Payment received', 'at' => $p->paid_at ?? $p->updated_at, 'tone' => 'success', 'detail' => null],
            'failed' => ['label' => 'Payment failed', 'at' => $p->updated_at, 'tone' => 'danger', 'detail' => $p->failure_code ? 'Reason: ' . str_replace('_', ' ', $p->failure_code) : null],
            'refunded' => ['label' => 'Marked refunded', 'at' => $p->updated_at, 'tone' => 'secondary', 'detail' => null],
            default => ['label' => 'Waiting for the gateway', 'at' => null, 'tone' => 'warning', 'detail' => 'Still pending. It will update when the gateway confirms.'],
        };

        return $steps;
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Payment::TYPES)],
            'status' => ['nullable', Rule::in(Payment::STATUSES)],
            'channel' => ['nullable', Rule::in(Payment::CHANNELS)],
            'method' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /** Shared by the table and the totals so both always describe the same rows. */
    private function listQuery(array $f): Builder
    {
        return Payment::query()
            ->when($f['type'] ?? null, fn (Builder $q, $v) => $q->where('payments.type', $v))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('payments.status', $v))
            ->when($f['channel'] ?? null, fn (Builder $q, $v) => $q->where('payments.channel', $v))
            ->when($f['method'] ?? null, fn (Builder $q, $v) => $q->where('payments.method', $v))
            ->when($f['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('payments.created_at', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('payments.created_at', '<=', $v))
            ->when($f['q'] ?? null, function (Builder $q, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('payments.gateway_reference', 'like', $like)
                        ->orWhereHas('booking', function (Builder $b) use ($like) {
                            $b->where('reference', 'like', $like)
                                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));
                        });
                });
            });
    }

    /** Only succeeded rows count as money moved; failed and pending are shown but not summed. */
    private function totals(array $f): array
    {
        $row = $this->listQuery($f)->toBase()->selectRaw(
            "COALESCE(SUM(CASE WHEN payments.type = 'charge' AND payments.status = 'succeeded' THEN payments.amount END), 0) AS collected,
             COALESCE(SUM(CASE WHEN payments.type = 'refund' AND payments.status = 'succeeded' THEN payments.amount END), 0) AS refunded,
             COALESCE(SUM(CASE WHEN payments.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed,
             COALESCE(SUM(CASE WHEN payments.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending"
        )->first();

        return [
            'collected' => round((float) $row->collected, 2),
            'refunded' => round((float) $row->refunded, 2),
            'net' => round((float) $row->collected - (float) $row->refunded, 2),
            'failed' => (int) $row->failed,
            'pending' => (int) $row->pending,
        ];
    }
}
