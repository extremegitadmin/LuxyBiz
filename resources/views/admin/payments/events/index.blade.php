@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Payments</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.payments.index') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment activity</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            @can('admin.payments.view')
                <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary px-4">Back to payments</a>
            @endcan
        </div>
    </div>
    <!--end breadcrumb-->

    @if ($errors->any())
        <div class="alert alert-danger border-0" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($problems > 0)
        <div class="alert alert-warning border-0" role="alert">
            {{ $problems }} {{ \Illuminate\Support\Str::plural('event', $problems) }} in the last 7 days {{ $problems === 1 ? 'was' : 'were' }} ignored or failed.
            <a href="{{ route('admin.payments.events', ['outcome' => 'failed']) }}" class="alert-link">Show failed</a>
        </div>
    @endif

    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.payments.events') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Gateway reference, event id, booking reference">
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="event">Event</label>
                    <select id="event" name="event" class="form-select">
                        <option value="">All</option>
                        @foreach ($eventNames as $n)
                            <option value="{{ $n }}" @selected(($filters['event'] ?? '') === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="outcome">Outcome</label>
                    <select id="outcome" name="outcome" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\PaymentEvent::OUTCOMES as $o)
                            <option value="{{ $o }}" @selected(($filters['outcome'] ?? '') === $o)>{{ ucfirst($o) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label" for="linked">Linked to a payment</label>
                    <select id="linked" name="linked" class="form-select">
                        <option value="">All</option>
                        <option value="yes" @selected(($filters['linked'] ?? '') === 'yes')>Yes</option>
                        <option value="no" @selected(($filters['linked'] ?? '') === 'no')>No (unmatched)</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="from">From</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="to">To</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Filter</button>
                    <a href="{{ route('admin.payments.events') }}" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">{{ number_format($events->total()) }} {{ \Illuminate\Support\Str::plural('event', $events->total()) }}</h5>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Received</th><th>Event</th><th>Outcome</th><th>Gateway reference</th><th>Payment</th><th>Payload</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $e)
                            <tr>
                                <td class="text-nowrap">{{ $e->created_at?->format('d M Y, H:i') }}</td>
                                <td><code>{{ $e->event }}</code>@if ($e->gateway)<div class="small text-secondary">{{ $e->gateway }}</div>@endif</td>
                                <td>
                                    @include('admin.payments.events._badge', ['value' => $e->outcome])
                                    @if ($e->note)<div class="small text-secondary">{{ $e->note }}</div>@endif
                                </td>
                                <td class="small text-secondary text-break">{{ $e->gateway_reference ?: '-' }}</td>
                                <td>
                                    @if ($e->payment)
                                        @can('admin.payments.view')<a href="{{ route('admin.payments.show', $e->payment) }}">#{{ $e->payment->id }}</a>@else #{{ $e->payment->id }} @endcan
                                        @if ($e->payment->booking)<div class="small text-secondary">{{ $e->payment->booking->reference }}</div>@endif
                                    @else
                                        <span class="text-secondary">Not matched</span>
                                    @endif
                                </td>
                                <td style="min-width: 160px">
                                    @if ($e->payload)
                                        <details>
                                            <summary class="small">View</summary>
                                            <pre class="small mb-0 mt-2 p-2 bg-light border rounded" style="max-height: 260px; overflow: auto; white-space: pre-wrap">{{ $e->prettyPayload() }}</pre>
                                        </details>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-5">
                                    No payment activity match these filters.
                                    @if (request()->query())
                                        <a href="{{ route('admin.payments.events') }}">Clear the filters</a>
                                    @else
                                        <div class="small mt-1">Activity appears here once an online payment gateway is connected and starts sending updates.</div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="mt-4">{{ $events->links('pagination::bootstrap-5') }}</div>
            @endif
            <div class="form-text mt-3">Secrets, signatures and card numbers are removed before an event is saved. This log is read only.</div>
        </div>
    </div>
@endsection
