@extends('admin.layouts.app')
@section('title', 'Departure ' . $departure->departs_on->format('d M Y'))

@php $left = $departure->capacity - $booked; $pct = min(100, round($booked / max(1, $departure->capacity) * 100)); @endphp

@section('content')
<div class="page-head">
    <h1 class="page-title h3">{{ $departure->tour?->title }}</h1>
    <span class="badge badge-soft-secondary">{{ $departure->departs_on->format('D, d M Y') }}</span>
    @include('admin.partials.status', ['value' => $departure->status])
    <div class="ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.departures.index', ['tour_id' => $departure->tour_id]) }}">All departures</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.fulfillment.index', ['departure' => $departure->id]) }}"><i class="bi bi-clipboard-check me-1"></i>Fulfillment desk</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.fulfillment.manifest', $departure) }}"><i class="bi bi-printer me-1"></i>Manifest</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="panel panel-body">
            <div class="small text-secondary">Seats</div>
            <div class="h3 mb-1">{{ $booked }} of {{ $departure->capacity }} booked</div>
            <div class="progress mb-2" style="height:8px"><div class="progress-bar {{ $pct >= 90 ? 'bg-danger' : '' }}" style="width:{{ $pct }}%"></div></div>
            <div class="small {{ $left <= 2 ? 'text-danger' : 'text-secondary' }}">{{ $left > 0 ? $left . ' seat(s) left' : 'Sold out' }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-body h-100">
            <div class="small text-secondary">Price per traveler</div>
            <div class="h4 mb-1">{{ $departure->tour?->currency }} {{ number_format($departure->price_override ?? $departure->tour?->base_price ?? 0, 2) }}</div>
            <div class="small text-secondary">{{ $departure->price_override ? 'Departure-specific price' : 'Uses the tour base price' }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <form method="POST" action="{{ route('admin.departures.toggle', $departure) }}" class="panel panel-body h-100"
              data-confirm="{{ $departure->status === 'open' ? 'Close this departure? Customers will not be able to book it.' : 'Reopen this departure for booking?' }}">
            @csrf @method('PATCH')
            <div class="small text-secondary mb-2">Sales</div>
            <button class="btn {{ $departure->status === 'open' ? 'btn-outline-danger' : 'btn-primary' }} w-100">
                {{ $departure->status === 'open' ? 'Close sales' : 'Reopen sales' }}</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-head">Bookings on this departure ({{ $bookings->count() }})</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Reference</th><th>Customer</th><th>Travelers</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($bookings as $b)
                <tr>
                    <td><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td>
                    <td>{{ $b->user?->name ?? 'Guest' }}</td>
                    <td>{{ $b->travelers->pluck('full_name')->implode(', ') }}</td>
                    <td>@include('admin.partials.status', ['value' => $b->payment_status])</td>
                    <td>@include('admin.partials.status', ['value' => $b->status])</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No bookings on this departure yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
