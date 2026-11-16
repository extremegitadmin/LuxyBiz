@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">All bookings</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <a href="{{ route('admin.bookings.export', request()->query()) }}" class="btn btn-outline-primary px-4">Export CSV</a>
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

    {{-- Search and filters --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Reference, customer name or email, traveler name">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="status">Booking status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Booking::STATUSES as $s)
                            <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="payment_status">Payment</label>
                    <select id="payment_status" name="payment_status" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Booking::PAYMENT_STATUSES as $s)
                            <option value="{{ $s }}" @selected(($filters['payment_status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="source">Source</label>
                    <select id="source" name="source" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Booking::SOURCES as $s)
                            <option value="{{ $s }}" @selected(($filters['source'] ?? '') === $s)>{{ $s === 'csr' ? 'Staff (CSR)' : 'Website' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2"></div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="from">Created from</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="to">Created to</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Filter</button>
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Results --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center mb-3">
                <h5 class="mb-0 fw-semibold">{{ number_format($bookings->total()) }} {{ \Illuminate\Support\Str::plural('booking', $bookings->total()) }}</h5>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th class="text-center">Travelers</th>
                            <th class="text-end">Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $b)
                            @php $net = round((float) $b->charged_total - (float) $b->refunded_total, 2); @endphp
                            <tr>
                                <td class="fw-semibold"><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td>
                                <td>
                                    {{ $b->user->name ?? 'Guest' }}
                                    @if ($b->leadTraveler && $b->leadTraveler->full_name !== ($b->user->name ?? null))
                                        <div class="text-secondary small">Lead: {{ $b->leadTraveler->full_name }}</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $b->travelers_count }}</td>
                                <td class="text-end">{{ $b->currency }} {{ number_format($b->total_amount, 2) }}</td>
                                <td>
                                    @include('admin.bookings._badge', ['kind' => 'payment', 'value' => $b->payment_status])
                                    <div class="text-secondary small">{{ number_format($net, 2) }} paid</div>
                                </td>
                                <td>@include('admin.bookings._badge', ['kind' => 'status', 'value' => $b->status])</td>
                                <td><span class="badge bg-light text-dark border">{{ strtoupper($b->source) }}</span></td>
                                <td class="text-nowrap">{{ $b->created_at?->format('d M Y') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    <a href="{{ route('admin.bookings.edit', $b) }}" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-secondary py-5">
                                    No bookings match these filters.
                                    @if (request()->query())
                                        <a href="{{ route('admin.bookings.index') }}">Clear the filters</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($bookings->hasPages())
                <div class="mt-4">{{ $bookings->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
