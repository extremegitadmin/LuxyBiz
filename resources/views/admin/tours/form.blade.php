@extends('admin.layouts.master')

@php
    $isEdit = $tour->exists;
    $days = old('days', $tour->days->map(fn ($d) => $d->only(['title', 'description', 'activities', 'meals', 'stay']))->all());
    $field = fn (string $name) => old($name, $tour->{$name});
@endphp

@section('title', $isEdit ? 'Edit tour' : 'Add tour')

@section('content')
<div class="d-flex align-items-center mb-3 gap-2">
    <h1 class="page-title h3 mb-0">{{ $isEdit ? $tour->title : 'Add tour' }}</h1>
    @if ($isEdit)@include('admin.partials.status', ['value' => $tour->status])@endif
    <div class="ms-auto d-flex gap-2">
        @if ($isEdit)<a href="{{ route('admin.tours.preview', $tour) }}" class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i>Preview</a>@endif
        <a href="{{ route('admin.tours.index') }}" class="btn btn-outline-secondary">Back to tours</a>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" novalidate
      action="{{ $isEdit ? route('admin.tours.update', $tour) : route('admin.tours.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="panel">
        <ul class="nav nav-tabs px-3 pt-2" role="tablist">
            @foreach (['basics' => 'Basics', 'itinerary' => 'Itinerary', 'details' => 'Details', 'pricing' => 'Pricing and media', 'seo' => 'SEO'] as $id => $label)
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $id }}" role="tab">{{ $label }}</button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content panel-body">
            {{-- Basics --}}
            <div class="tab-pane fade show active" id="tab-basics" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="title">Title</label>
                        <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ $field('title') }}" maxlength="160">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                            @foreach (\App\Models\Tour::STATUSES as $s)<option value="{{ $s }}" @selected($field('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                        </select>
                        <div class="form-text">Only published tours appear on the website.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="objective_id">Objective</label>
                        <select id="objective_id" name="objective_id" class="form-select @error('objective_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach ($objectives as $o)<option value="{{ $o->id }}" @selected($field('objective_id') == $o->id)>{{ $o->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="destination">Destination</label>
                        <input id="destination" name="destination" class="form-control" value="{{ $field('destination') }}" placeholder="Kerala, India">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="duration_days">Duration (days)</label>
                        <input id="duration_days" type="number" min="1" max="60" name="duration_days" class="form-control @error('duration_days') is-invalid @enderror" value="{{ $field('duration_days') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="short_description">Short description</label>
                        <textarea id="short_description" name="short_description" rows="2" maxlength="400" class="form-control">{{ $field('short_description') }}</textarea>
                        <div class="form-text">Shown on the tour card in the catalogue. Up to 400 characters.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Full description</label>
                        <textarea id="description" name="description" rows="8" class="form-control">{{ $field('description') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="highlights">Highlights</label>
                        <textarea id="highlights" name="highlights" rows="4" class="form-control">{{ $field('highlights') }}</textarea>
                        <div class="form-text">One highlight per line.</div>
                    </div>
                </div>
            </div>

            {{-- Itinerary builder --}}
            <div class="tab-pane fade" id="tab-itinerary" role="tabpanel">
                <p class="text-secondary">Add one block per day. Use Up and Down to change the order; day numbers update automatically.</p>
                <div id="days">
                    @foreach ($days as $i => $d)
                        @include('admin.tours._day', ['i' => $i, 'd' => $d])
                    @endforeach
                </div>
                <p id="days-empty" class="text-secondary d-none">No days yet. Add the first day of the itinerary.</p>
                <button type="button" class="btn btn-outline-primary" id="add-day">Add day</button>
                <template id="day-template">@include('admin.tours._day', ['i' => '__i__', 'd' => []])</template>
            </div>

            {{-- Details --}}
            <div class="tab-pane fade" id="tab-details" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="inclusions">Inclusions</label>
                        <textarea id="inclusions" name="inclusions" rows="8" class="form-control">{{ $field('inclusions') }}</textarea>
                        <div class="form-text">One item per line.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="exclusions">Exclusions</label>
                        <textarea id="exclusions" name="exclusions" rows="8" class="form-control">{{ $field('exclusions') }}</textarea>
                        <div class="form-text">One item per line.</div>
                    </div>
                    <div class="col-12"><hr class="my-1"><div class="fw-semibold">Expert information</div><div class="form-text">Professional details supplied by the client, shown on the tour page.</div></div>
                    <div class="col-md-6"><label class="form-label" for="expert_name">Name</label>
                        <input id="expert_name" name="expert_name" class="form-control" value="{{ $field('expert_name') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="expert_role">Role</label>
                        <input id="expert_role" name="expert_role" class="form-control" value="{{ $field('expert_role') }}"></div>
                    <div class="col-12"><label class="form-label" for="expert_bio">About</label>
                        <textarea id="expert_bio" name="expert_bio" rows="4" class="form-control">{{ $field('expert_bio') }}</textarea></div>
                </div>
            </div>

            {{-- Pricing and media --}}
            <div class="tab-pane fade" id="tab-pricing" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="base_price">Base price per traveler</label>
                        <input id="base_price" type="number" step="0.01" min="0" name="base_price" class="form-control @error('base_price') is-invalid @enderror" value="{{ $field('base_price') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror">
                            @foreach (\App\Models\Tour::CURRENCIES as $c)<option @selected($field('currency') === $c)>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6 align-self-end form-text">
                        Departure dates, capacity and date-specific prices are managed under
                        @if ($isEdit)<a href="{{ route('admin.departures.index', ['tour_id' => $tour->id]) }}">Departures</a>@else Departures once the tour is saved @endif.
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="hero_image">Hero image</label>
                        <input id="hero_image" type="file" name="hero_image" accept="image/png,image/jpeg,image/webp" class="form-control @error('hero_image') is-invalid @enderror">
                        <div class="form-text">JPG, PNG or WebP, up to 4 MB. Landscape works best.</div>
                    </div>
                    @if ($tour->hero_image)
                        <div class="col-md-4"><div class="small text-secondary mb-1">Current image</div>
                            <img src="{{ asset('storage/' . $tour->hero_image) }}" alt="" class="img-fluid rounded" style="max-height:120px"></div>
                    @endif
                </div>
            </div>

            {{-- SEO --}}
            <div class="tab-pane fade" id="tab-seo" role="tabpanel">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="slug">URL slug</label>
                        <div class="input-group"><span class="input-group-text">/tours/</span>
                            <input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ $field('slug') }}" placeholder="Generated from the title if left blank"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="meta_title">Meta title</label>
                        <input id="meta_title" name="meta_title" maxlength="70" class="form-control" value="{{ $field('meta_title') }}">
                        <div class="form-text">Up to 70 characters.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="meta_description">Meta description</label>
                        <textarea id="meta_description" name="meta_description" rows="3" maxlength="170" class="form-control">{{ $field('meta_description') }}</textarea>
                        <div class="form-text">Up to 170 characters. Shown in search results.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-body border-top d-flex gap-2">
            <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create tour' }}</button>
            <a href="{{ route('admin.tours.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var list = document.getElementById('days');
    var tpl = document.getElementById('day-template');
    var empty = document.getElementById('days-empty');
    var counter = list.querySelectorAll('[data-day]').length + 1000;

    function relabel() {
        list.querySelectorAll('[data-day]').forEach(function (el, i) {
            el.querySelector('.day-label').textContent = 'Day ' + (i + 1);
        });
        empty.classList.toggle('d-none', list.children.length > 0);
    }

    document.getElementById('add-day').addEventListener('click', function () {
        list.insertAdjacentHTML('beforeend', tpl.innerHTML.split('__i__').join(counter++));
        relabel();
        list.lastElementChild.querySelector('input').focus();
    });

    list.addEventListener('click', function (e) {
        var row = e.target.closest('[data-day]');
        if (!row) return;
        if (e.target.closest('[data-remove]')) {
            if (confirm('Remove this day from the itinerary?')) { row.remove(); relabel(); }
            return;
        }
        var mv = e.target.closest('[data-move]');
        if (!mv) return;
        if (mv.dataset.move === 'up' && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (mv.dataset.move === 'down' && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        relabel();
    });

    relabel();

    // After a failed save, open the first tab that holds an error.
    var bad = document.querySelector('.tab-pane .is-invalid');
    if (bad) {
        var pane = bad.closest('.tab-pane');
        var btn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
})();
</script>
@endpush
