<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Webhook events log: what the gateway told us, newest first. Read only. */
class PaymentEventController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:80'],
            'outcome' => ['nullable', Rule::in(PaymentEvent::OUTCOMES)],
            'linked' => ['nullable', Rule::in(['yes', 'no'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = PaymentEvent::query()
            ->with('payment:id,booking_id,type,amount,currency', 'payment.booking:id,reference')
            ->when($f['event'] ?? null, fn (Builder $q, $v) => $q->where('event', $v))
            ->when($f['outcome'] ?? null, fn (Builder $q, $v) => $q->where('outcome', $v))
            ->when(($f['linked'] ?? null) === 'yes', fn (Builder $q) => $q->whereNotNull('payment_id'))
            ->when(($f['linked'] ?? null) === 'no', fn (Builder $q) => $q->whereNull('payment_id'))
            ->when($f['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($f['q'] ?? null, function (Builder $q, $term) {
                // "escape ?" makes % and _ literal on MySQL and SQLite alike
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $q->where(fn (Builder $w) => $w->whereRaw('gateway_reference like ? escape ?', [$like, '\\'])
                    ->orWhereRaw('external_id like ? escape ?', [$like, '\\'])
                    ->orWhereHas('payment.booking', fn (Builder $b) => $b->whereRaw('reference like ? escape ?', [$like, '\\'])));
            })
            ->latest()->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.payments.events.index', [
            'events' => $events,
            'filters' => $f,
            'eventNames' => PaymentEvent::query()->distinct()->orderBy('event')->pluck('event'),
            'problems' => PaymentEvent::whereIn('outcome', ['failed', 'ignored'])->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }
}
