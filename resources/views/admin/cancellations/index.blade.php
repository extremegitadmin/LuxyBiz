@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Cancellations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Cancellation requests</li>
                </ol>
            </nav>
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

    {{-- Status tabs, with counts --}}
    @php
        $tabQuery = fn ($status) => array_filter(['q' => $filters['q'] ?? null, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'status' => $status]);
        $active = $filters['status'] ?? '';
    @endphp
    <ul class="nav nav-pills mb-3 gap-1">
        <li class="nav-item">
            <a class="nav-link {{ $active === '' ? 'active' : '' }}" href="{{ route('admin.cancellations.index', $tabQuery(null)) }}">All <span class="badge bg-secondary ms-1">{{ $counts->sum() }}</span></a>
        </li>
        @foreach (\App\Models\CancellationRequest::STATUSES as $s)
            <li class="nav-item">
                <a class="nav-link {{ $active === $s ? 'active' : '' }}" href="{{ route('admin.cancellations.index', $tabQuery($s)) }}">
                    {{ $s === 'credited' ? 'Credit issued' : ucfirst($s) }} <span class="badge bg-secondary ms-1">{{ $counts[$s] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.cancellations.index') }}" class="row g-3 align-items-end">
                @if ($active !== '')<input type="hidden" name="status" value="{{ $active }}">@endif
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Request or booking reference, customer, traveler">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="from">Requested from</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="to">to</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Filter</button>
                    <a href="{{ route('admin.cancellations.index') }}" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">{{ number_format($requests->total()) }} {{ \Illuminate\Support\Str::plural('request', $requests->total()) }}</h5>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Booking</th>
                            <th>Customer</th>
                            <th>Departure</th>
                            <th>Requested</th>
                            <th class="text-end">Refund</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $r)
                            @php
                                $b = $r->booking;
                                $departs = $b?->departure?->departs_on;
                                $needsReview = (bool) data_get($r->calculation, 'needs_review');
                            @endphp
                            <tr>
                                <td class="fw-semibold text-nowrap">
                                    {{ $r->reference }}
                                    @if ($r->source === 'customer')<div class="small text-secondary fw-normal">by customer</div>@endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($b)
                                        @can('admin.bookings.view')
                                            <a href="{{ route('admin.bookings.show', $b->id) }}">{{ $b->reference }}</a>
                                        @else
                                            {{ $b->reference }}
                                        @endcan
                                        @if ($b->tour?->title)<div class="small text-secondary">{{ \Illuminate\Support\Str::limit($b->tour->title, 32) }}</div>@endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    {{ $b?->user?->name ?? $b?->leadTraveler?->full_name ?? 'Guest' }}
                                </td>
                                <td class="text-nowrap">
                                    @if ($departs)
                                        {{ $departs->format('d M Y') }}
                                    @else
                                        <span class="text-secondary" title="No departure date on this booking">not set</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $r->requested_on?->format('d M Y') }}
                                    @if (($w = $r->waitingDays()) !== null && $w > 0)
                                        <div class="small {{ $w >= 3 ? 'text-danger' : 'text-secondary' }}">waiting {{ $w }} {{ \Illuminate\Support\Str::plural('day', $w) }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ $r->currency }} {{ number_format($r->refundAmount(), 2) }}
                                    @if ($r->isOverridden())<div class="small text-secondary">overridden</div>@endif
                                    @if ($needsReview && $r->status === 'pending')<div class="small text-warning" title="Check the calculation before approving">needs review</div>@endif
                                </td>
                                <td>@include('admin.cancellations._badge', ['value' => $r->status])</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-5">
                                    No cancellation requests{{ request()->query() ? ' match these filters.' : ' yet.' }}
                                    @if (request()->query())
                                        <a href="{{ route('admin.cancellations.index') }}">Clear the filters</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="mt-4">{{ $requests->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
