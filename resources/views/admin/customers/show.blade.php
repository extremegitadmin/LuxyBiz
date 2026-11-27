@extends('admin.layouts.master')
@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Customers</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 p-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">All customers</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li></ol></nav>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-secondary small">Customer</div><div class="fs-5 fw-semibold">{{ $customer->name }}</div></div>
                <div class="col-md-4"><div class="text-secondary small">Email</div>{{ $customer->email }}</div>
                <div class="col-md-2"><div class="text-secondary small">Phone</div>{{ $customer->phone ?: '-' }}</div>
                <div class="col-md-2"><div class="text-secondary small">Joined</div>{{ $customer->created_at?->format('d M Y') }}</div>
            </div>
        </div>
    </div>

    {{-- Bookings --}}
    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">Bookings ({{ $bookings->count() }})</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Tour</th><th>Departure</th><th class="text-center">Travelers</th><th class="text-end">Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($bookings as $b)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td>
                            <td>{{ $b->tour->title ?? '-' }}</td>
                            <td class="text-nowrap">{{ $b->departure?->departs_on?->format('d M Y') ?? '-' }}</td>
                            <td class="text-center">{{ $b->travelers_count }}</td>
                            <td class="text-end text-nowrap">{{ $b->currency }} {{ number_format($b->total_amount, 2) }}</td>
                            <td>@include('admin.bookings._badge', ['kind' => 'payment', 'value' => $b->payment_status])</td>
                            <td>@include('admin.bookings._badge', ['kind' => 'status', 'value' => $b->status])</td>
                            <td class="text-end"><a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">This customer has no bookings yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Documents from all of this customer's bookings. Viewing and downloading use the same routes (and the same access log) as the booking page. --}}
    @if ($canDocs)
    <div class="card rounded-4" id="documents">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-1">Documents ({{ $documentTotal }})</h5>
            <div class="text-secondary small mb-3">Private files from all of this customer's bookings. Opening or downloading a file is recorded. To add a file, open the booking.</div>

            @forelse ($bookings as $b)
                @php $rows = $documents[$b->id] ?? collect(); @endphp
                @continue($rows->isEmpty())
                <div class="fw-semibold mt-3 mb-2">
                    <a href="{{ route('admin.bookings.show', $b) }}#documents">{{ $b->reference }}</a>
                    <span class="text-secondary fw-normal">· {{ $b->tour->title ?? '-' }} · {{ $b->departure?->departs_on?->format('d M Y') }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Type</th><th>Traveler</th><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($rows as $d)
                            <tr>
                                <td class="fw-semibold">{{ $d->typeLabel() }}@if ($d->label)<div class="small text-secondary fw-normal">{{ $d->label }}</div>@endif</td>
                                <td>{{ $d->traveler?->full_name ?? 'Whole booking' }}</td>
                                <td class="text-break">{{ $d->original_name }}</td>
                                <td class="text-nowrap">{{ $d->humanSize() }}</td>
                                <td class="small text-nowrap">{{ $d->created_at?->format('d M Y H:i') }}<div class="text-secondary">{{ $d->uploader?->name ?? '-' }}</div></td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.documents.view', $d) }}" target="_blank" rel="noopener noreferrer">View</a>
                                        @can('admin.documents.download')<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.documents.download', $d) }}">Download</a>@endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
            @endforelse

            @if ($documentTotal === 0)
                <div class="text-secondary py-3">No documents have been uploaded for this customer yet.</div>
            @endif
        </div>
    </div>
    @endif

    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary px-4 mb-4">Back to customers</a>
@endsection
