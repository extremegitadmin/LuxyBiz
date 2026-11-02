<div class="border rounded bg-white p-3 mb-3" data-day>
    <div class="d-flex align-items-center gap-2 mb-2">
        <strong class="day-label">Day</strong>
        <div class="ms-auto btn-group btn-group-sm" role="group" aria-label="Day controls">
            <button type="button" class="btn btn-outline-secondary" data-move="up" aria-label="Move day up">Up</button>
            <button type="button" class="btn btn-outline-secondary" data-move="down" aria-label="Move day down">Down</button>
            <button type="button" class="btn btn-outline-danger" data-remove>Remove</button>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label small">Day title</label>
            <input name="days[{{ $i }}][title]" value="{{ $d['title'] ?? '' }}" class="form-control @error('days.' . $i . '.title') is-invalid @enderror" maxlength="160">
        </div>
        <div class="col-12">
            <label class="form-label small">Description</label>
            <textarea name="days[{{ $i }}][description]" rows="3" class="form-control">{{ $d['description'] ?? '' }}</textarea>
        </div>
        <div class="col-12">
            <label class="form-label small">Activities</label>
            <textarea name="days[{{ $i }}][activities]" rows="2" class="form-control">{{ $d['activities'] ?? '' }}</textarea>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Meals</label>
            <input name="days[{{ $i }}][meals]" value="{{ $d['meals'] ?? '' }}" class="form-control" placeholder="Breakfast, dinner" maxlength="120">
        </div>
        <div class="col-md-7">
            <label class="form-label small">Stay</label>
            <input name="days[{{ $i }}][stay]" value="{{ $d['stay'] ?? '' }}" class="form-control" placeholder="Hotel or resort name" maxlength="160">
        </div>
    </div>
</div>
