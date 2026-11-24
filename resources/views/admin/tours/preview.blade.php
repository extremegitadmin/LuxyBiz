@extends('admin.layouts.master')

@section('title', 'Preview · ' . $tour->title)

@push('css')
<style>
    .tour-preview { color:#26364b; }
    .tour-preview .preview-hero { width:100%; max-height:420px; object-fit:cover; display:block; }
    .tour-preview .preview-shell { border:1px solid #e8edf2; border-radius:15px; overflow:hidden; background:#fff; }
    .tour-preview .preview-content { padding:clamp(18px,3vw,36px); }
    .tour-preview .preview-kicker { color:#4773ca; text-transform:uppercase; letter-spacing:.08em; font-size:.72rem; font-weight:800; }
    .tour-preview .preview-title { font-weight:800; letter-spacing:-.035em; color:#243246; }
    .tour-preview .preview-lead { color:#718096; font-size:1.05rem; }
    .tour-preview .preview-section { border-top:1px solid #edf0f4; padding-top:23px; margin-top:23px; }
    .tour-preview .preview-day { border:1px solid #e9edf2; border-radius:11px; padding:16px; }
    .tour-preview .preview-day + .preview-day { margin-top:12px; }
    .tour-preview .preview-price { background:#f7f9fc; border:1px solid #e8edf2; border-radius:13px; padding:20px; position:sticky; top:20px; }
    .tour-preview .preview-gallery img { width:100%; height:135px; object-fit:cover; border-radius:9px; }
</style>
@endpush

@section('content')
<div class="tour-preview">
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3" role="status">
        <i class="bi bi-eye fs-5"></i>
        <div class="flex-grow-1"><strong>Admin preview.</strong> This is a content preview, not the live public booking page. Current status: {{ ucfirst($tour->status) }}.</div>
        <a href="{{ route('admin.tours.edit', $tour) }}" class="btn btn-sm btn-outline-secondary">Edit tour</a>
        <!-- <a href="{{ route('admin.tours.itinerary', $tour) }}" class="btn btn-sm btn-outline-secondary">Edit itinerary</a> -->
    </div>

    <article class="preview-shell">
        @if ($tour->hero_image)
            <img class="preview-hero" src="{{ asset('storage/' . $tour->hero_image) }}" alt="{{ $tour->title }}">
        @endif

        <div class="preview-content">
            <div class="d-flex flex-wrap gap-2 mb-3">
                @if ($tour->category)<span class="badge text-bg-primary">{{ $tour->category->name }}</span>@endif
                @if ($tour->objective)<span class="badge text-bg-light">{{ $tour->objective->name }}</span>@endif
                <span class="badge text-bg-light">{{ $tour->duration_days }}</span>
                @if ($tour->destination)<span class="badge text-bg-light">{{ $tour->destination }}</span>@endif
            </div>

            <div class="preview-kicker mb-2">Tour experience</div>
            <h1 class="preview-title display-6 mb-2">{{ $tour->title }}</h1>
            @if ($tour->short_description)
                <p class="preview-lead mb-0">{{ $tour->short_description }}</p>
            @endif

            @if ($tour->description)
                <section class="preview-section">
                    <h2 class="h5 fw-bold mb-3">About this tour</h2>
                    <div class="text-secondary" style="white-space:pre-wrap">{{ $tour->description }}</div>
                </section>
            @endif

            @if ($tour->lines('highlights'))
                <section class="preview-section">
                    <h2 class="h5 fw-bold mb-3">Tour highlights</h2>
                    <div class="row g-2">
                        @foreach ($tour->lines('highlights') as $highlight)
                            <div class="col-md-6"><div class="d-flex gap-2"><i class="bi bi-check-circle-fill text-success"></i><span>{{ $highlight }}</span></div></div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- <section class="preview-section">
                <h2 class="h5 fw-bold mb-3">Your itinerary</h2>
                @forelse ($tour->days as $day)
                    <div class="preview-day">
                        <div class="d-flex gap-3">
                            <div class="badge text-bg-primary align-self-start">Day {{ $day->day_number }}</div>
                            <div class="flex-grow-1">
                                <h3 class="h6 fw-bold mb-2">{{ $day->title }}</h3>
                                @if ($day->description)<p class="text-secondary mb-2" style="white-space:pre-wrap">{{ $day->description }}</p>@endif
                                @if ($day->activities)<div class="small mb-1"><strong>Activities:</strong> {{ $day->activities }}</div>@endif
                                @if ($day->meals)<div class="small mb-1"><strong>Meals:</strong> {{ $day->meals }}</div>@endif
                                @if ($day->stay)<div class="small"><strong>Stay:</strong> {{ $day->stay }}</div>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-secondary small">No itinerary days have been added yet.</div>
                @endforelse
            </section> -->

            <section class="preview-section">
                <h2 class="h5 fw-bold mb-3">Available Packages</h2>

                <div class="row g-4">
                    @forelse ($tour->packages->where('is_active', true) as $package)
                        <div class="col-lg-4">
                            <div class="border rounded-3 p-3 h-100">

                                <h3 class="h5 fw-bold mb-2">
                                    {{ $package->name }}
                                </h3>

                                <div class="fs-4 fw-bold text-primary mb-3">
                                    USD {{ number_format((float) $package->price, 2) }}
                                </div>

                                @if ($package->description)
                                    <p class="text-secondary small">
                                        {{ $package->description }}
                                    </p>
                                @endif

                                @if ($package->days->isNotEmpty())
                                    <hr>
                                    <h4 class="h6 fw-bold">Itinerary</h4>

                                    @foreach ($package->days as $day)
                                        <div class="mb-3">
                                            <strong>
                                                Day {{ $day->day_number }}:
                                                {{ $day->title }}
                                            </strong>

                                            @if ($day->description)
                                                <p class="small text-secondary mb-1"
                                                style="white-space:pre-wrap">
                                                    {{ $day->description }}
                                                </p>
                                            @endif

                                            @if ($day->activities)
                                                <div class="small">
                                                    <strong>Activities:</strong>
                                                    {{ $day->activities }}
                                                </div>
                                            @endif

                                            @if ($day->meals)
                                                <div class="small">
                                                    <strong>Meals:</strong>
                                                    {{ $day->meals }}
                                                </div>
                                            @endif

                                            @if ($day->stay)
                                                <div class="small">
                                                    <strong>Stay:</strong>
                                                    {{ $day->stay }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif

                                @if ($package->inclusions)
                                    <hr>
                                    <h4 class="h6 fw-bold">What's Included</h4>
                                    <div class="small text-secondary"
                                        style="white-space:pre-wrap">
                                        {{ $package->inclusions }}
                                    </div>
                                @endif

                                @if ($package->exclusions)
                                    <hr>
                                    <h4 class="h6 fw-bold">What's Not Included</h4>
                                    <div class="small text-secondary"
                                        style="white-space:pre-wrap">
                                        {{ $package->exclusions }}
                                    </div>
                                @endif

                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-secondary">
                                No active packages have been added yet.
                            </p>
                        </div>
                    @endforelse
                </div>
            </section>


            @if ($tour->images->isNotEmpty())
                <section class="preview-section">
                    <h2 class="h5 fw-bold mb-3">Gallery</h2>
                    <div class="row g-3 preview-gallery">
                        @foreach ($tour->images as $image)
                            <div class="col-6 col-md-3"><img src="{{ asset('storage/' . $image->image_path) }}" alt="{{ $image->alt_text ?: $tour->title }}"></div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- @if ($tour->lines('inclusions') || $tour->lines('exclusions'))
                <section class="preview-section">
                    <div class="row g-4">
                        @if ($tour->lines('inclusions'))
                            <div class="col-md-6">
                                <h2 class="h6 fw-bold mb-2">What's included</h2>
                                <ul class="small text-secondary mb-0">@foreach ($tour->lines('inclusions') as $item)<li class="mb-1">{{ $item }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @if ($tour->lines('exclusions'))
                            <div class="col-md-6">
                                <h2 class="h6 fw-bold mb-2">What's not included</h2>
                                <ul class="small text-secondary mb-0">@foreach ($tour->lines('exclusions') as $item)<li class="mb-1">{{ $item }}</li>@endforeach</ul>
                            </div>
                        @endif
                    </div>
                </section>
            @endif -->

            @if ($tour->expert_name)
                <section class="preview-section">
                    <div class="preview-kicker mb-2">Your tour expert</div>
                    <h2 class="h5 fw-bold mb-1">{{ $tour->expert_name }}</h2>
                    @if ($tour->expert_role)<div class="text-secondary small mb-2">{{ $tour->expert_role }}</div>@endif
                    @if ($tour->expert_bio)<p class="text-secondary mb-0" style="white-space:pre-wrap">{{ $tour->expert_bio }}</p>@endif
                </section>
            @endif

            <section class="preview-section">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h2 class="h5 fw-bold mb-3">Upcoming departures</h2>
                        @forelse ($tour->departures as $departure)
                            @php $seatsLeft = max(0, (int) $departure->capacity - (int) ($departure->seats_booked ?? 0)); @endphp
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-3">
                                <span>{{ $departure->departs_on?->format('d M Y') }}</span>
                                <span class="small {{ $seatsLeft === 0 ? 'text-danger' : 'text-secondary' }}">{{ $seatsLeft > 0 ? $seatsLeft . ' seats left' : 'Sold out' }}</span>
                            </div>
                        @empty
                            <p class="small text-secondary mb-0">No upcoming open departures are currently available.</p>
                        @endforelse
                    </div>
                    <!-- <div class="col-lg-5">
                        <div class="preview-price">
                            <div class="small text-secondary">Starting from</div>
                            <div class="display-6 fw-bold my-1">{{ $tour->currency }} {{ number_format((float) $tour->base_price, 2) }}</div>
                            <div class="small text-secondary">per traveller</div>
                            <div class="small text-secondary mt-3">Departure-specific prices may differ.</div>
                            <a href="{{ route('admin.departures.index', ['tour_id' => $tour->id]) }}" class="btn btn-outline-primary w-100 mt-3">Manage departures</a>
                        </div>
                    </div> -->
                </div>
            </section>
        </div>
    </article>
</div>
@endsection
