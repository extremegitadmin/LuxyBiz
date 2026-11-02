@extends('admin.layouts.master')
@section('title', 'Preview: ' . $tour->title)

@section('content')
<div class="alert alert-warning d-flex align-items-center gap-3 no-print" role="status">
    <i class="bi bi-eye fs-5"></i>
    <div class="flex-grow-1">Preview of the public tour page. Status: @include('admin.partials.status', ['value' => $tour->status]). Visitors only see it when it is published.</div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.tours.edit', $tour) }}">Back to editing</a>
</div>

<article class="panel overflow-hidden">
    @if ($tour->hero_image)
        <img src="{{ asset('storage/' . $tour->hero_image) }}" alt="" style="width:100%;max-height:340px;object-fit:cover;display:block">
    @endif
    <div class="panel-body p-4">
        <div class="d-flex flex-wrap gap-2 mb-2">
            @if ($tour->objective)<span class="badge badge-soft-primary">{{ $tour->objective->name }}</span>@endif
            <span class="badge badge-soft-secondary">{{ $tour->duration_days }} days</span>
            @if ($tour->destination)<span class="badge badge-soft-secondary">{{ $tour->destination }}</span>@endif
        </div>
        <h1 class="h2 mb-2">{{ $tour->title }}</h1>
        <p class="lead text-secondary">{{ $tour->short_description }}</p>

        <div class="row g-4 mt-1">
            <div class="col-lg-8">
                @if ($tour->description)<div style="white-space:pre-wrap">{{ $tour->description }}</div>@endif

                @if ($tour->lines('highlights'))
                    <h2 class="h5 mt-4">Highlights</h2>
                    <ul>@foreach ($tour->lines('highlights') as $h)<li>{{ $h }}</li>@endforeach</ul>
                @endif

                <h2 class="h5 mt-4">Day by day</h2>
                @forelse ($tour->days as $d)
                    <div class="border rounded p-3 mb-2">
                        <div class="fw-semibold">Day {{ $d->day_number }}: {{ $d->title }}</div>
                        @if ($d->description)<div style="white-space:pre-wrap">{{ $d->description }}</div>@endif
                        @if ($d->activities)<div class="small text-secondary mt-1"><i class="bi bi-activity me-1"></i>{{ $d->activities }}</div>@endif
                        <div class="small text-secondary mt-1">
                            @if ($d->meals)<i class="bi bi-cup-hot me-1"></i>{{ $d->meals }}@endif
                            @if ($d->stay) <span class="ms-3"><i class="bi bi-house me-1"></i>{{ $d->stay }}</span>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-secondary">No itinerary days yet.</p>
                @endforelse

                <div class="row g-3 mt-2">
                    <div class="col-md-6"><h2 class="h6">Included</h2><ul class="small">@foreach ($tour->lines('inclusions') as $x)<li>{{ $x }}</li>@endforeach</ul></div>
                    <div class="col-md-6"><h2 class="h6">Not included</h2><ul class="small">@foreach ($tour->lines('exclusions') as $x)<li>{{ $x }}</li>@endforeach</ul></div>
                </div>

                @if ($tour->expert_name)
                    <div class="panel panel-body mt-3 bg-transparent">
                        <div class="small text-secondary">Your expert</div>
                        <div class="fw-semibold">{{ $tour->expert_name }} <span class="fw-normal text-secondary">{{ $tour->expert_role }}</span></div>
                        <div style="white-space:pre-wrap">{{ $tour->expert_bio }}</div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="panel panel-body">
                    <div class="small text-secondary">From</div>
                    <div class="h3 mb-0">{{ $tour->currency }} {{ number_format($tour->base_price, 2) }}</div>
                    <div class="small text-secondary mb-3">per traveler</div>
                    <div class="fw-semibold mb-2">Upcoming departures</div>
                    @forelse ($tour->departures as $dep)
                        @php $left = $dep->capacity - (int) $dep->seats_booked; @endphp
                        <div class="d-flex justify-content-between small border-top py-2">
                            <span>{{ $dep->departs_on->format('d M Y') }}</span>
                            <span class="{{ $left <= 2 ? 'text-danger' : 'text-secondary' }}">{{ $left > 0 ? $left . ' seats left' : 'Sold out' }}</span>
                        </div>
                    @empty
                        <div class="small text-secondary">No open departures yet.</div>
                    @endforelse
                    <button class="btn btn-primary w-100 mt-3" disabled>Book now</button>
                </div>
            </div>
        </div>
    </div>
</article>
@endsection
