<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payments list (E4). Money is charged and refunded elsewhere; this page only reads. */
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
