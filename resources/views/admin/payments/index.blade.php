@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Payments</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">All payments</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <a href="{{ route('admin.payments.events') }}" class="btn btn-outline-primary px-4">Payment activity</a>
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

    {{-- Totals for the rows that match the filters --}}
    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="card rounded-4 mb-0"><div class="card-body">
                <div class="text-secondary small">Collected</div>
                <div class="fs-5 fw-semibold text-success">{{ number_format($totals['collected'], 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card rounded-4 mb-0"><div class="card-body">
                <div class="text-secondary small">Refunded</div>
                <div class="fs-5 fw-semibold text-danger">{{ number_format($totals['refunded'], 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card rounded-4 mb-0"><div class="card-body">
                <div class="text-secondary small">Net</div>
                <div class="fs-5 fw-semibold">{{ number_format($totals['net'], 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card rounded-4 mb-0"><div class="card-body">
                <div class="text-secondary small">Needs attention</div>
                <div class="fs-5 fw-semibold">{{ $totals['failed'] }} failed <span class="text-secondary fw-normal">/</span> {{ $totals['pending'] }} pending</div>
            </div></div>
        </div>
    </div>
    <div class="form-text mt-2 mb-3">Totals count succeeded payments and refunds that match the filters below. Amounts are added as stored, so filter by currency-specific bookings if you sell in more than one.</div>

    {{-- Search and filters --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}"
                        placeholder="Booking reference, gateway reference, customer">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Payment::TYPES as $t)
                            <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ $t === 'refund' ? 'Refund' : 'Payment' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Payment::STATUSES as $s)
                            <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="channel">Channel</label>
                    <select id="channel" name="channel" class="form-select">
                        <option value="">All</option>
                        @foreach (\App\Models\Payment::CHANNELS as $c)
                            <option value="{{ $c }}" @selected(($filters['channel'] ?? '') === $c)>{{ ucfirst(str_replace('_', ' ', $c)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="method">Method</label>
                    <select id="method" name="method" class="form-select">
                        <option value="">All</option>
                        @foreach ($methods as $m)
                            <option value="{{ $m }}" @selected(($filters['method'] ?? '') === $m)>{{ ucfirst(str_replace('_', ' ', $m)) }}</option>
                        @endforeach
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
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Results --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="mb-3 fw-semibold">{{ number_format($payments->total()) }} {{ \Illuminate\Support\Str::plural('payment', $payments->total()) }}</h5>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Booking</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $p)
                            <tr>
                                <td class="text-nowrap">
                                    @if (\Illuminate\Support\Facades\Route::has('admin.payments.show'))
                                        <a href="{{ route('admin.payments.show', $p) }}">{{ $p->created_at?->format('d M Y, H:i') }}</a>
                                    @else
                                        {{ $p->created_at?->format('d M Y, H:i') }}
                                    @endif
                                </td>
                                <td class="fw-semibold">
                                    @if ($p->booking)
                                        <a href="{{ route('admin.bookings.show', $p->booking_id) }}">{{ $p->booking->reference }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $p->booking?->user?->name ?? 'Guest' }}</td>
                                <td>{{ $p->type === 'refund' ? 'Refund' : 'Payment' }}</td>
                                <td class="small text-secondary">{{ $p->gateway_reference ?: '-' }}</td>
                                <td>{{ $p->method ? ucfirst(str_replace('_', ' ', $p->method)) : '-' }}</td>
                                <td class="text-end text-nowrap {{ $p->type === 'refund' ? 'text-danger' : '' }}">{{ $p->type === 'refund' ? '-' : '' }}{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                                <td>@include('admin.payments._badge', ['value' => $p->status])</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">
                                    No payments match these filters.
                                    @if (request()->query())
                                        <a href="{{ route('admin.payments.index') }}">Clear the filters</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="mt-4">{{ $payments->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
