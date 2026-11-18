@extends('admin.layouts.master')
@section('content')
    @php $balance = max(0, round((float) $booking->total_amount - $paid, 2)); @endphp

    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.bookings.index') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $booking->reference }}</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary px-4">Back to list</a>
            @can('admin.bookings.edit')
                <a href="{{ route('admin.bookings.edit', $booking) }}" class="btn btn-primary px-4">Edit booking</a>
            @endcan
        </div>
    </div>
    <!--end breadcrumb-->

    @if (session('success'))
        <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger border-0 alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Summary --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <h4 class="mb-1 fw-semibold">{{ $booking->reference }}</h4>
                    <p class="mb-0 text-secondary">
                        Created {{ $booking->created_at?->format('d M Y, H:i') }}
                        @if ($booking->source === 'csr' && $booking->creator) &middot; by {{ $booking->creator->name }} (staff) @endif
                    </p>
                </div>
                <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                    @include('admin.bookings._badge', ['kind' => 'status', 'value' => $booking->status])
                    @include('admin.bookings._badge', ['kind' => 'payment', 'value' => $booking->payment_status])
                    <span class="badge bg-light text-dark border">{{ strtoupper($booking->source) }}</span>
                </div>
            </div>
            <hr>
            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="text-secondary small">Customer</div>
                    <div class="fw-semibold">{{ $booking->user->name ?? 'Guest' }}</div>
                    @if ($booking->user?->email)<div class="small text-secondary">{{ $booking->user->email }}</div>@endif
                </div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Tour</div><div class="fw-semibold">Tour #{{ $booking->tour_id }}</div>
                    @if ($booking->tour_departure_id)<div class="small text-secondary">Departure #{{ $booking->tour_departure_id }}</div>@endif
                </div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Travelers</div><div class="fw-semibold">{{ $booking->travelers_count }}</div>
                    <div class="small text-secondary">Insurance: {{ $booking->insurance_selected ? 'Yes' : 'No' }}</div>
                </div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Currency</div><div class="fw-semibold">{{ $booking->currency }}</div></div>
            </div>
            <hr>
            <div class="row g-3 text-center text-sm-start">
                <div class="col-6 col-md-3"><div class="text-secondary small">Subtotal</div><div class="fw-semibold">{{ number_format($booking->subtotal, 2) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Insurance</div><div class="fw-semibold">{{ number_format($booking->insurance_amount, 2) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Total</div><div class="fw-semibold">{{ number_format($booking->total_amount, 2) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-secondary small">Paid / balance</div>
                    <div class="fw-semibold">{{ number_format($paid, 2) }} <span class="text-secondary fw-normal">/ {{ number_format($balance, 2) }}</span></div>
                </div>
            </div>
            @if ($booking->notes)
                <hr>
                <div class="text-secondary small mb-1">Internal notes</div>
                <div style="white-space: pre-line">{{ $booking->notes }}</div>
            @endif
        </div>
    </div>

    @can('admin.payments.link.create')
        @include('admin.bookings._request_payment', ['balance' => $balance])
    @endcan

    {{-- Travelers --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">Travelers</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Name</th><th>Contact</th><th>Nationality</th><th>Passport</th><th>Arrival</th><th>Flag</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($booking->travelers as $t)
                            <tr>
                                <td class="fw-semibold">{{ $t->full_name }} @if ($t->is_lead)<span class="badge bg-primary ms-1">Lead</span>@endif</td>
                                <td>
                                    {{ $t->email ?: '-' }}
                                    @if ($t->phone)<div class="small text-secondary">{{ $t->phone }}</div>@endif
                                </td>
                                <td>{{ $t->nationality ?: '-' }}</td>
                                <td>{{ $t->passport_number ? 'On file, ending ' . substr($t->passport_number, -3) : 'Not on file' }}
                                    @if ($t->passport_expiry)<div class="small text-secondary">Expires {{ $t->passport_expiry->format('d M Y') }}</div>@endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $t->arrival_date?->format('d M Y') ?? '-' }}
                                    @if ($t->arrival_flight || $t->arrival_time)
                                        <div class="small text-secondary">{{ $t->arrival_flight }} {{ $t->arrival_time ? substr($t->arrival_time, 0, 5) : '' }}</div>
                                    @endif
                                </td>
                                <td>{{ $t->flag_note ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No travelers on this booking.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-3">Health information is restricted and is not shown on this page.</div>
        </div>
    </div>

    {{-- Payments --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">Payments</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Method</th><th class="text-end">Amount</th><th>Status</th><th>Gateway reference</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($booking->payments as $p)
                            <tr>
                                <td class="text-nowrap">{{ $p->created_at?->format('d M Y, H:i') }}</td>
                                <td>{{ ucfirst($p->type) }}</td>
                                <td>{{ $p->method ? ucfirst(str_replace('_', ' ', $p->method)) : '-' }}</td>
                                <td class="text-end">{{ $p->type === 'refund' ? '-' : '' }}{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ ['succeeded' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'refunded' => 'secondary'][$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span>
                                </td>
                                <td class="small text-secondary">{{ $p->gateway_reference ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-3">Only gateway references are stored. Card numbers are never kept.</div>
        </div>
    </div>

    @include('admin.bookings._history', ['changes' => $changes])
@endsection
