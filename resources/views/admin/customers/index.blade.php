@extends('admin.layouts.master')
@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Customers</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 p-0">
                <li class="breadcrumb-item active" aria-current="page">All customers</li></ol></nav>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0"><ul class="mb-0 ps-3">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
    @endif

    <div class="card rounded-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="q">Search</label>
                    <input id="q" name="q" maxlength="100" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone">
                </div>
                @if ($canDocs)
                    <div class="col-12 col-lg-3">
                        <div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="with_documents" value="1" id="with_documents" @checked(request()->boolean('with_documents'))>
                            <label class="form-check-label" for="with_documents">Only customers with documents</label></div>
                    </div>
                @endif
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button class="btn btn-primary px-4">Search</button>
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary px-4">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-semibold mb-3">{{ number_format($customers->total()) }} {{ \Illuminate\Support\Str::plural('customer', $customers->total()) }}</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th>Customer</th><th>Phone</th><th class="text-center">Bookings</th><th>Last booking</th>
                        @if ($canDocs)<th class="text-center">Documents</th>@endif
                        <th>Joined</th><th class="text-end">Actions</th>
                    </tr></thead>
                    <tbody>
                    @forelse ($customers as $c)
                        @php $b = $bookings[$c->id] ?? null; $docs = (int) ($documents[$c->id] ?? 0); @endphp
                        <tr>
                            <td><a class="fw-semibold" href="{{ route('admin.customers.show', $c) }}">{{ $c->name }}</a><div class="small text-secondary">{{ $c->email }}</div></td>
                            <td class="text-nowrap">{{ $c->phone ?: '-' }}</td>
                            <td class="text-center">{{ (int) ($b->n ?? 0) }}</td>
                            <td class="small text-nowrap">{{ $b?->last_at ? \Illuminate\Support\Carbon::parse($b->last_at)->format('d M Y') : '-' }}</td>
                            @if ($canDocs)
                                <td class="text-center">@if ($docs > 0)<a href="{{ route('admin.customers.show', $c) }}#documents" class="badge bg-primary text-decoration-none">{{ $docs }}</a>@else<span class="text-secondary">0</span>@endif</td>
                            @endif
                            <td class="small text-nowrap">{{ $c->created_at?->format('d M Y') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.customers.show', $c) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                @if ($canDocs)<a href="{{ route('admin.customers.show', $c) }}#documents" class="btn btn-sm btn-outline-secondary">Documents</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canDocs ? 7 : 6 }}" class="text-center text-secondary py-5">No customers found.
                            @if (request()->query())<a href="{{ route('admin.customers.index') }}">Clear the search</a>@endif</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $customers->links() }}</div>
        </div>
    </div>
@endsection
