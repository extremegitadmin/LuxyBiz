
@extends('admin.layouts.master')

@section('content')

@php
    $isEdit = $package->exists;

    $days = old(
        'days',
        $isEdit ? $package->days->toArray() : []
    );
@endphp

<div class="page-wrapper">
    <div class="page-content">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4>{{ $isEdit ? 'Edit Package' : 'Add Package' }}</h4>
                <p class="text-muted mb-0">
                    Tour: {{ $tour->title }}
                </p>
            </div>

            <a href="{{ route('admin.tours.packages.index', $tour->id) }}"
               class="btn btn-light">
                Back to Packages
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Please correct the following errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $isEdit
            ? route('admin.tours.packages.update', [$tour->id, $package->id])
            : route('admin.tours.packages.store', $tour->id) }}"
              method="POST">

            @csrf

            @if($isEdit)
                @method('PUT')
            @endif

            {{-- Package Information --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Package Information</h5>
                </div>

                <div class="card-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Package Name <span class="text-danger">*</span>
                            </label>

                            <select name="name" class="form-select" required>
                                <option value="">Select Package</option>

                                @foreach(['Silver', 'Gold', 'Platinum'] as $name)
                                    <option value="{{ $name }}"
                                        {{ old('name', $package->name) == $name ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Price Per Person ({{ $tour->currency }})
                                <span class="text-danger">*</span>
                            </label>

                            <input type="number"
                                   name="price"
                                   class="form-control"
                                   step="0.01"
                                   min="0"
                                   value="{{ old('price', $package->price ?? '') }}"
                                   placeholder="Enter package price"
                                   required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Package Description</label>

                            <textarea name="description"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Enter package description">{{ old('description', $package->description) }}</textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Inclusions</label>

                            <textarea name="inclusions"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Enter inclusions, one per line">{{ old('inclusions', $package->inclusions) }}</textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exclusions</label>

                            <textarea name="exclusions"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Enter exclusions, one per line">{{ old('exclusions', $package->exclusions) }}</textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Order</label>

                            <input type="number"
                                   name="sort_order"
                                   class="form-control"
                                   min="0"
                                   value="{{ old('sort_order', $package->sort_order ?? 0) }}">
                        </div>

                        <div class="col-md-6 mb-3 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input type="checkbox"
                                       name="is_active"
                                       value="1"
                                       class="form-check-input"
                                       id="is_active"
                                       {{ old('is_active', $package->exists ? $package->is_active : true) ? 'checked' : '' }}>

                                <label class="form-check-label" for="is_active">
                                    Active Package
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Package Itinerary --}}
            <div class="card mb-4">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Package Itinerary</h5>

                    <button type="button"
                            class="btn btn-sm btn-primary"
                            id="addDay">
                        <i class="material-icons-outlined">add</i>
                        Add Day
                    </button>
                </div>

                <div class="card-body">

                    <p class="text-muted">
                        Add the itinerary specifically for this package.
                    </p>

                    <div id="itineraryContainer">

                        @foreach($days as $index => $day)

                            <div class="border rounded p-3 mb-3 itinerary-day">

                                <div class="d-flex justify-content-between mb-3">
                                    <h6 class="day-heading">
                                        Day {{ $loop->iteration }}
                                    </h6>

                                    <button type="button"
                                            class="btn btn-sm btn-danger removeDay">
                                        Remove
                                    </button>
                                </div>

                                <div class="row">

                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Day Number *</label>
                                        <input type="number"
                                               name="days[{{ $index }}][day_number]"
                                               class="form-control"
                                               min="1"
                                               value="{{ old("days.$index.day_number", $day['day_number'] ?? $loop->iteration) }}"
                                               required>
                                    </div>

                                    <div class="col-md-9 mb-3">
                                        <label class="form-label">Day Title *</label>
                                        <input type="text"
                                               name="days[{{ $index }}][title]"
                                               class="form-control"
                                               value="{{ old("days.$index.title", $day['title'] ?? '') }}"
                                               placeholder="Example: Arrival at Munnar"
                                               required>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="days[{{ $index }}][description]"
                                                  class="form-control"
                                                  rows="3">{{ old("days.$index.description", $day['description'] ?? '') }}</textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Activities</label>
                                        <textarea name="days[{{ $index }}][activities]"
                                                  class="form-control"
                                                  rows="3">{{ old("days.$index.activities", $day['activities'] ?? '') }}</textarea>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Meals</label>
                                        <input type="text"
                                               name="days[{{ $index }}][meals]"
                                               class="form-control"
                                               value="{{ old("days.$index.meals", $day['meals'] ?? '') }}"
                                               placeholder="Breakfast, Lunch">
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Stay</label>
                                        <input type="text"
                                               name="days[{{ $index }}][stay]"
                                               class="form-control"
                                               value="{{ old("days.$index.stay", $day['stay'] ?? '') }}"
                                               placeholder="Hotel name/type">
                                    </div>

                                </div>
                            </div>

                        @endforeach

                    </div>

                    <div id="noDaysMessage"
                         class="text-center text-muted py-4"
                         style="{{ count($days) ? 'display:none;' : '' }}">
                        No itinerary days added yet. Click Add Day to begin.
                    </div>

                </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-end gap-2 mb-4">

                <a href="{{ route('admin.tours.packages.index', $tour->id) }}"
                   class="btn btn-light">
                    Cancel
                </a>

                <button type="submit" class="btn btn-primary">
                    {{ $isEdit ? 'Update Package' : 'Save Package' }}
                </button>

            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const container = document.getElementById('itineraryContainer');
    const addButton = document.getElementById('addDay');
    const noDaysMessage = document.getElementById('noDaysMessage');

    let nextIndex = {{ count($days) }};

    function updateDays() {
        const dayCards = container.querySelectorAll('.itinerary-day');

        dayCards.forEach((card, index) => {
            card.querySelector('.day-heading').textContent = 'Day ' + (index + 1);
        });

        noDaysMessage.style.display = dayCards.length ? 'none' : 'block';
    }

    addButton.addEventListener('click', function () {

        const index = nextIndex++;

        const html = `
            <div class="border rounded p-3 mb-3 itinerary-day">

                <div class="d-flex justify-content-between mb-3">
                    <h6 class="day-heading">New Day</h6>
                    <button type="button" class="btn btn-sm btn-danger removeDay">
                        Remove
                    </button>
                </div>

                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Day Number *</label>
                        <input type="number"
                               name="days[${index}][day_number]"
                               class="form-control"
                               min="1"
                               value="${container.querySelectorAll('.itinerary-day').length + 1}"
                               required>
                    </div>

                    <div class="col-md-9 mb-3">
                        <label class="form-label">Day Title *</label>
                        <input type="text"
                               name="days[${index}][title]"
                               class="form-control"
                               placeholder="Enter day title"
                               required>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="days[${index}][description]"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Activities</label>
                        <textarea name="days[${index}][activities]"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Meals</label>
                        <input type="text"
                               name="days[${index}][meals]"
                               class="form-control"
                               placeholder="Breakfast, Lunch">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Stay</label>
                        <input type="text"
                               name="days[${index}][stay]"
                               class="form-control"
                               placeholder="Hotel name/type">
                    </div>

                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', html);
        updateDays();
    });

    container.addEventListener('click', function (event) {
        if (event.target.closest('.removeDay')) {
            event.target.closest('.itinerary-day').remove();
            updateDays();
        }
    });

    updateDays();
});
</script>

@endsection
