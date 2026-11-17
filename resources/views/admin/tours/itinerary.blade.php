@extends('admin.layouts.master')

@section('title', 'Itinerary · ' . $tour->title)

@push('css')
<style>
    .itinerary-page .itinerary-card { border:1px solid #e8edf2; border-radius:13px; background:#fff; overflow:hidden; }
    .itinerary-page .itinerary-card + .itinerary-card { margin-top:14px; }
    .itinerary-page .day-number { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#edf3ff; color:#315fc4; font-weight:800; flex:0 0 auto; }
    .itinerary-page .section-title { color:#26364b; font-weight:750; }
    .itinerary-page .form-label { font-size:.83rem; font-weight:650; color:#435269; }
    .itinerary-page .form-control { border-color:#dfe5ec; border-radius:8px; }
    .itinerary-page .form-control:focus { border-color:#88a9ed; box-shadow:0 0 0 .2rem rgba(65,112,210,.12); }
    .itinerary-page .add-day-panel { border:1px dashed #b9c8df; border-radius:13px; background:#f9fbff; }
</style>
@endpush

@section('content')
<div class="itinerary-page">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <div class="small text-secondary mb-1">TOUR MANAGEMENT / ITINERARY</div>
            <h1 class="section-title h3 mb-1">{{ $tour->title }}</h1>
            <div class="small text-secondary">{{ $tour->duration_days }} day tour · {{ $days->count() }} itinerary day(s) added</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a href="{{ route('admin.tours.edit', $tour) }}#tab-basics" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i> Tour details</a>
            <a href="{{ route('admin.tours.preview', $tour) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i> Preview</a>
            <a href="{{ route('admin.tours.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i> All tours</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-4">
        <h2 class="section-title h5 mb-1">Day-by-day itinerary</h2>
        <p class="small text-secondary mb-0">Each day is saved independently. Updating tour details will not remove your itinerary.</p>
    </div>

    @forelse ($days as $day)
        <div class="itinerary-card">
            <div class="p-3 p-lg-4">
                <form method="POST" action="{{ route('admin.tours.itinerary.update', [$tour, $day]) }}">
                    @csrf
                    @method('PUT')
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="day-number">{{ $day->day_number }}</div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark">Day {{ $day->day_number }}</div>
                            <div class="small text-secondary">Edit the schedule and save this day.</div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check2 me-1"></i> Save day</button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label" for="day-number-{{ $day->id }}">Day number</label>
                            <input id="day-number-{{ $day->id }}" type="number" min="1" max="365" name="day_number"
                                   value="{{ old('day_number', $day->day_number) }}" class="form-control" required>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label" for="day-title-{{ $day->id }}">Day title</label>
                            <input id="day-title-{{ $day->id }}" type="text" name="title" maxlength="191"
                                   value="{{ old('title', $day->title) }}" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="day-description-{{ $day->id }}">Description</label>
                            <textarea id="day-description-{{ $day->id }}" name="description" rows="3" class="form-control">{{ old('description', $day->description) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="day-activities-{{ $day->id }}">Activities</label>
                            <textarea id="day-activities-{{ $day->id }}" name="activities" rows="3" class="form-control">{{ old('activities', $day->activities) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="day-meals-{{ $day->id }}">Meals</label>
                            <textarea id="day-meals-{{ $day->id }}" name="meals" rows="3" class="form-control">{{ old('meals', $day->meals) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="day-stay-{{ $day->id }}">Accommodation / stay</label>
                            <textarea id="day-stay-{{ $day->id }}" name="stay" rows="3" class="form-control">{{ old('stay', $day->stay) }}</textarea>
                        </div>
                    </div>
                </form>
                <div class="d-flex justify-content-end mt-2">
                    <form method="POST" action="{{ route('admin.tours.itinerary.destroy', [$tour, $day]) }}" onsubmit="return confirm('Delete Day {{ $day->day_number }} from this itinerary?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> Delete day</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="itinerary-card text-center p-5 mb-4">
            <i class="bi bi-calendar2-week fs-1 text-secondary"></i>
            <h3 class="h6 fw-bold mt-3">No itinerary added yet</h3>
            <p class="small text-secondary mb-0">Start by adding the first day below.</p>
        </div>
    @endforelse

    <div class="add-day-panel p-3 p-lg-4 mt-4">
        <div class="mb-3">
            <h2 class="section-title h5 mb-1"><i class="bi bi-plus-circle me-1"></i> Add a new day</h2>
            <p class="small text-secondary mb-0">Choose a day number that has not already been used.</p>
        </div>
        <form method="POST" action="{{ route('admin.tours.itinerary.store', $tour) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label" for="new-day-number">Day number <span class="text-danger">*</span></label>
                    <input id="new-day-number" type="number" name="day_number" min="1" max="365"
                           value="{{ old('day_number', ($days->max('day_number') ?? 0) + 1) }}" class="form-control" required>
                </div>
                <div class="col-md-10">
                    <label class="form-label" for="new-day-title">Day title <span class="text-danger">*</span></label>
                    <input id="new-day-title" type="text" name="title" maxlength="191"
                           value="{{ old('title') }}" class="form-control" placeholder="Example: Arrival in Kochi" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="new-day-description">Description</label>
                    <textarea id="new-day-description" name="description" rows="3" class="form-control" placeholder="Describe the plan for this day.">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="new-day-activities">Activities</label>
                    <textarea id="new-day-activities" name="activities" rows="3" class="form-control">{{ old('activities') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="new-day-meals">Meals</label>
                    <textarea id="new-day-meals" name="meals" rows="3" class="form-control" placeholder="Breakfast, lunch, dinner">{{ old('meals') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="new-day-stay">Accommodation / stay</label>
                    <textarea id="new-day-stay" name="stay" rows="3" class="form-control">{{ old('stay') }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add itinerary day</button>
            </div>
        </form>
    </div>
</div>
@endsection
