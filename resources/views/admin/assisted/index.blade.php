@extends('admin.layouts.master')
@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 p-0">
                <li class="breadcrumb-item active" aria-current="page">Assisted bookings</li></ol></nav>
        </div>
        <div class="ms-auto">
            @can('admin.assisted.create')<a href="{{ route('admin.assisted.create') }}" class="btn btn-primary px-4">New assisted booking</a>@endcan
        </div>
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls)
        @if (session($key))<div class="alert alert-{{ $cls }} border-0">{{ session($key) }}</div>@endif
    @endforeach

    {{-- Tiles: counts by payment state and what is still owed (follow the search, staff and dates) --}}
    <div class="row g-3 mb-3">
        @foreach (['unpaid' => 'Unpaid', 'partial' => 'Part paid', 'paid' => 'Paid', 'refunded' => 'Refunded'] as $k => $label)
            <div class="col-6 col-lg-2"><a class="card rounded-4 mb-0 text-decoration-none" href="{{ route('admin.assisted.index', array_merge(request()->except('page'), ['payment_status' => $k])) }}">
                <div class="card-body p-3"><div class="text-secondary small">{{ $label }}</div><div class="fs-4 fw-semibold text-body">{{ (int) ($counts[$k] ?? 0) }}</div></div></a></div>
        @endforeach
        <div class="col-12 col-lg-4"><div class="card rounded-4 mb-0"><div class="card-body p-3">
            <div class="text-secondary small">Still to collect (not cancelled)</div>
            @forelse ($owing as $cur => $amt)<div class="fw-semibold">{{ $cur }} {{ number_format($amt, 2) }}</div>@empty<div class="fw-semibold">Nothing</div>@endforelse
        </div></div></div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.assisted.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-3"><label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, customer, traveler"></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="payment_status">Payment</label>
                    <select id="payment_status" name="payment_status" class="form-select"><option value="">All</option>
                        @foreach (\App\Models\Booking::PAYMENT_STATUSES as $s)<option value="{{ $s }}" @selected(($filters['payment_status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="status">Booking status</label>
                    <select id="status" name="status" class="form-select"><option value="">All</option>
                        @foreach (\App\Models\Booking::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="staff">Created by</label>
                    <select id="staff" name="staff" class="form-select"><option value="">Anyone</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected((string) ($filters['staff'] ?? '') === (string) $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-6 col-lg-3 d-flex align-items-center"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request()->boolean('mine'))><label class="form-check-label" for="mine">Only mine</label></div></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="from">Created from</label><input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="to">Created to</label><input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="col-12 col-lg-4 d-flex gap-2"><button class="btn btn-primary px-4">Filter</button><a href="{{ route('admin.assisted.index') }}" class="btn btn-outline-secondary px-4">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">{{ number_format($bookings->total()) }} {{ \Illuminate\Support\Str::plural('booking', $bookings->total()) }}</h5>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Reference</th><th>Customer</th><th>Tour</th><th class="text-center">Travelers</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Payment</th><th>Status</th><th>Created by</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    @php
                        $net = round((float) $b->charged_total - (float) $b->refunded_total, 2);
                        $bal = max(0, round((float) $b->total_amount - $net, 2));
                        $canPay = in_array($b->status, $payable, true) && $bal > 0;
                    @endphp
                    <tr>
                        <td class="fw-semibold"><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a>
                            @if (($openLinks[$b->id] ?? 0) > 0)<div class="small text-primary">{{ $openLinks[$b->id] }} link(s) out</div>@endif</td>
                        <td>{{ $b->user->name ?? 'Guest' }}</td>
                        <td>{{ $b->tour->title ?? '-' }}<div class="small text-secondary">{{ $b->departure?->departs_on?->format('d M Y') }}</div></td>
                        <td class="text-center">{{ $b->travelers_count }}</td>
                        <td class="text-end text-nowrap">{{ $b->currency }} {{ number_format($b->total_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($net, 2) }}</td>
                        <td class="text-end">{{ number_format($bal, 2) }}</td>
                        <td>@include('admin.bookings._badge', ['kind' => 'payment', 'value' => $b->payment_status])</td>
                        <td>@include('admin.bookings._badge', ['kind' => 'status', 'value' => $b->status])</td>
                        <td class="small">{{ $b->creator->name ?? '-' }}<div class="text-secondary">{{ $b->created_at?->format('d M Y') }}</div></td>
                        <td class="text-end text-nowrap">
                            @if ($canPay)@can('admin.payments.link.create')<a href="{{ route('admin.assisted.payment', $b) }}" class="btn btn-sm btn-primary">Take payment</a>@endcan @endif
                            <a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-secondary py-5">No assisted bookings match.
                        @if (request()->query())<a href="{{ route('admin.assisted.index') }}">Clear the filters</a>@endif</td></tr>
                @endforelse
                </tbody></table></div>
            <div class="mt-3">{{ $bookings->links() }}</div>
        </div>
    </div>
@endsection
