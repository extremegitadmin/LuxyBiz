@extends('admin.layouts.master')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.bookings.index') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->reference }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Health and intake</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto">
            <a href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-outline-secondary px-4">Back to booking</a>
        </div>
    </div>
    <!--end breadcrumb-->

    @if (session('success'))
        <div class="alert alert-success border-0 alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger border-0" role="alert">
            @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
        </div>
    @endif

    <div class="alert alert-warning border-0 d-flex align-items-start gap-2" role="alert">
        <i class="bx bx-lock-alt fs-4"></i>
        <div>
            <div class="fw-semibold">Restricted information</div>
            This page holds private health details. Your name and the time were recorded when you opened it, and every change is recorded too.
            Do not copy it into emails or chats, and do not print it.
        </div>
    </div>

    <div class="card rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <h4 class="mb-1 fw-semibold">{{ $booking->reference }}</h4>
                    <p class="mb-0 text-secondary">{{ $booking->user->name ?? 'Guest' }} &middot; {{ $booking->travelers->count() }} {{ \Illuminate\Support\Str::plural('traveler', $booking->travelers->count()) }}</p>
                </div>
                <div class="ms-auto text-md-end">
                    <div class="text-secondary small">Travel insurance</div>
                    <div class="fw-semibold">{{ $booking->insurance_selected ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>
    </div>

    @forelse ($booking->travelers as $t)
        <div class="card rounded-4 mb-4" id="traveler-{{ $t->id }}">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h5 class="mb-0 fw-semibold">{{ $t->full_name }}</h5>
                    @if ($t->is_lead)<span class="badge bg-primary">Lead</span>@endif
                    <span class="ms-auto small text-secondary">
                        {{ $t->phone ?: 'No phone' }}@if ($t->email) &middot; {{ $t->email }}@endif
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-secondary small">Traveler flag (shown on the manifest)</div>
                        <div class="fw-semibold">{{ $t->flag_note ?: '-' }}</div>
                        @can('admin.bookings.edit')<div class="form-text">Change it on <a href="{{ route('admin.bookings.edit', $booking) }}">Edit booking</a>.</div>@endcan
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary small">Arrival</div>
                        <div class="fw-semibold">
                            {{ $t->arrival_date?->format('d M Y') ?? 'Not given' }}
                            @if ($t->arrival_time) at {{ substr($t->arrival_time, 0, 5) }}@endif
                            @if ($t->arrival_flight) &middot; {{ $t->arrival_flight }}@endif
                        </div>
                        @if ($t->arrival_details)<div class="small text-secondary">{{ $t->arrival_details }}</div>@endif
                    </div>
                </div>

                <hr>
                @can('admin.health.edit')
                    <form method="POST" action="{{ route('admin.health.update', [$booking, $t]) }}">
                        @csrf @method('PUT')
                        <label class="form-label fw-semibold" for="health_{{ $t->id }}">Health notes</label>
                        <textarea id="health_{{ $t->id }}" name="health_notes" rows="4" maxlength="{{ $maxLength }}" class="form-control"
                            placeholder="Conditions, allergies, medication, mobility. Leave empty if there is nothing to record.">{{ old('health_notes', $t->health_notes) }}</textarea>
                        <div class="d-flex align-items-center mt-2">
                            <div class="form-text">Up to {{ $maxLength }} characters. Stored encrypted.</div>
                            <button class="btn btn-primary btn-sm ms-auto">Save health notes</button>
                        </div>
                    </form>
                @else
                    <div class="text-secondary small mb-1">Health notes</div>
                    @if ($t->health_notes)
                        <div style="white-space: pre-line">{{ $t->health_notes }}</div>
                    @else
                        <div class="text-secondary">Nothing recorded.</div>
                    @endif
                @endcan
            </div>
        </div>
    @empty
        <div class="card rounded-4 mb-4"><div class="card-body p-4 text-center text-secondary">No travelers on this booking.</div></div>
    @endforelse

    @can('admin.health.access_log')
        <div class="card rounded-4 mb-4" id="access-history">
            <div class="card-body p-4">
                <h5 class="mb-3 fw-semibold">Who has looked at this</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>When</th><th>Who</th><th>What</th><th>Traveler</th><th>From</th></tr></thead>
                        <tbody>
                            @php $names = $booking->travelers->pluck('full_name', 'id'); @endphp
                            @forelse ($history as $h)
                                <tr>
                                    <td class="text-nowrap">{{ $h->created_at?->format('d M Y H:i:s') }}</td>
                                    <td>{{ $h->user_name ?? 'Unknown' }}</td>
                                    <td>{{ $h->actionLabel() }}</td>
                                    <td>{{ $h->booking_traveler_id ? ($names[$h->booking_traveler_id] ?? 'Traveler #' . $h->booking_traveler_id) : '-' }}</td>
                                    <td class="small text-secondary">{{ $h->ip }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-secondary py-3">No entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-2">Latest 30 entries. The notes themselves are never written to this list.</div>
            </div>
        </div>
    @endcan
@endsection
