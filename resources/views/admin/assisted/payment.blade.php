@extends('admin.layouts.master')
@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Assisted bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.assisted.index') }}">Assisted bookings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Take payment · {{ $booking->reference }}</li>
                </ol>
            </nav>
        </div>
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls)
        @if (session($key))<div class="alert alert-{{ $cls }} border-0">{{ session($key) }}</div>@endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger border-0"><ul class="mb-0 ps-3">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
    @endif

    @if (session('customer_link'))
        <div class="alert alert-info border-0">
            <div class="fw-semibold mb-1">Customer payment link (shown only now, valid {{ session('customer_link_hours') }} hours)</div>
            <input type="text" readonly class="form-control" value="{{ session('customer_link') }}" onclick="this.select()">
            <div class="small mt-1">Anyone with this link can pay for this booking. Share it only with the customer. It is not stored and cannot be shown again, but you can send a new one.</div>
        </div>
    @endif

    <div class="card rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-secondary small">Booking</div>
                    <a class="fw-semibold" href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->reference }}</a>
                    <div>{{ $booking->tour->title ?? 'Tour' }} · {{ $booking->departure?->departs_on?->format('d M Y') }}</div></div>
                <div class="col-md-3"><div class="text-secondary small">Customer</div>{{ $booking->user->name ?? 'Guest' }}</div>
                <div class="col-md-5">
                    <div class="d-flex gap-4">
                        <div><div class="text-secondary small">Total</div>{{ $booking->currency }} {{ number_format($booking->total_amount, 2) }}</div>
                        <div><div class="text-secondary small">Paid</div>{{ number_format($paid, 2) }}</div>
                        <div><div class="text-secondary small">Balance</div><span class="fw-semibold">{{ number_format($balance, 2) }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (! $payable)
        <div class="alert alert-secondary border-0">This booking is {{ str_replace('_', ' ', $booking->status) }}, so it cannot take payment.</div>
    @elseif ($balance <= 0)
        <div class="alert alert-success border-0">Nothing is owed. This booking is fully paid.</div>
    @else
    @if ($openProviderLinks->isNotEmpty())
        <div class="alert alert-warning border-0">{{ $openProviderLinks->count() }} payment page(s) for this booking are still open. If the customer pays on two of them they will be charged twice.</div>
    @endif
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Amount to collect</h5>
            <form method="POST" id="pay-form">
                @csrf
                <div class="form-check"><input class="form-check-input" type="radio" name="choice" value="balance" id="c-balance" @checked(old('choice', 'balance') === 'balance')>
                    <label class="form-check-label" for="c-balance">Full balance: {{ $booking->currency }} {{ number_format($balance, 2) }}</label></div>
                <div class="form-check mt-1"><input class="form-check-input" type="radio" name="choice" value="custom" id="c-custom" @checked(old('choice') === 'custom')>
                    <label class="form-check-label" for="c-custom">Another amount (for example a deposit)</label></div>
                <div class="mt-2" style="max-width:240px"><input type="number" step="0.01" min="0.5" max="{{ $balance }}" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" placeholder="Amount in {{ $booking->currency }}"></div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4" formaction="{{ route('admin.assisted.hosted', $booking) }}" formtarget="_blank">Open payment page (customer is here)</button>
                    <button type="submit" class="btn btn-outline-primary px-4" formaction="{{ route('admin.assisted.send-link', $booking) }}">Email payment link to customer</button>
                </div>
                <div class="form-text mt-2">The payment page is hosted by the payment provider. The customer enters their card there. Do not ask for card numbers and do not type them anywhere.</div>
            </form>
        </div>
    </div>
    @endif

    @if ($requests->isNotEmpty())
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Customer payment links</h5>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Created</th><th class="text-end">Amount</th><th>Status</th><th>Emailed</th><th>Opened</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach ($requests as $r)
                    @php $st = $r->status(); @endphp
                    <tr>
                        <td class="text-nowrap">{{ $r->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-end">{{ $r->currency }} {{ number_format($r->amount, 2) }}</td>
                        <td><span class="badge bg-{{ ['open' => 'primary', 'paid' => 'success', 'cancelled' => 'secondary', 'expired' => 'warning'][$st] ?? 'secondary' }}">{{ ucfirst($st) }}</span>
                            @if ($st === 'open')<div class="small text-secondary">until {{ $r->expires_at?->format('d M H:i') }}</div>@endif</td>
                        <td>@if ($r->send_error)<span class="text-danger small">Not sent</span>@elseif ($r->sent_at)<span class="small">{{ $r->sent_at->format('d M H:i') }}</span>@else<span class="text-secondary small">No</span>@endif</td>
                        <td class="small">{{ $r->open_count }}×</td>
                        <td class="text-end text-nowrap">
                            @if (in_array($st, ['open', 'expired'], true) && $payable && $balance > 0)
                                <form method="POST" action="{{ route('admin.assisted.resend-link', [$booking, $r]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">Send new link</button></form>
                            @endif
                            @if ($st === 'open')
                                <form method="POST" action="{{ route('admin.assisted.cancel-link', [$booking, $r]) }}" class="d-inline" onsubmit="return confirm('Cancel this link? It will stop working.')">@csrf<button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody></table></div>
        </div>
    </div>
    @endif

    @if ($payments->isNotEmpty())
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Payment attempts</h5>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>When</th><th class="text-end">Amount</th><th>Status</th><th>Channel</th><th></th></tr></thead>
                <tbody>@foreach ($payments as $p)
                    <tr><td class="text-nowrap">{{ $p->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-end">{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                        <td>{{ ucfirst($p->status) }}</td><td>{{ $p->channel ?? '-' }}</td>
                        <td class="text-end">@can('admin.payments.view')<a href="{{ route('admin.payments.show', $p) }}" class="btn btn-sm btn-outline-secondary">View</a>@endcan</td></tr>
                @endforeach</tbody></table></div>
        </div>
    </div>
    @endif

    <a href="{{ route('admin.assisted.index') }}" class="btn btn-outline-secondary px-4 mb-4">Back to assisted bookings</a>
@endsection
