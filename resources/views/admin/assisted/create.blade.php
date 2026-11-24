@extends('admin.layouts.master')
@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Assisted bookings</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.assisted.index') }}">Assisted bookings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">New booking</li>
                </ol>
            </nav>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0" role="alert">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($tours->isEmpty())
        <div class="alert alert-warning border-0">No published tour has an open departure with seats left, so there is nothing to book yet.</div>
    @else
    <form method="POST" action="{{ route('admin.assisted.store') }}" id="assisted-form" autocomplete="off">
        @csrf
        <input type="hidden" name="customer_mode" id="customer_mode" value="{{ old('customer_mode', 'existing') }}">
        <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">

        {{-- 1. Customer --}}
        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">1. Customer</h5>
                <div class="btn-group mb-3" role="group">
                    <button type="button" class="btn btn-outline-primary" data-mode="existing">Existing customer</button>
                    <button type="button" class="btn btn-outline-primary" data-mode="new">New customer</button>
                </div>

                <div id="mode-existing">
                    <label class="form-label" for="customer_search">Search by name, email or phone (at least {{ $minSearch }} characters)</label>
                    <input type="text" id="customer_search" class="form-control" maxlength="100" placeholder="Start typing">
                    <div id="customer_results" class="list-group mt-2"></div>
                    <div id="customer_chosen" class="alert alert-success border-0 mt-2 d-none mb-0"></div>
                    <div class="form-text">Contact details are partly hidden. The booking email goes to the address on the customer's account.</div>
                </div>

                <div id="mode-new" class="row g-3 d-none">
                    <div class="col-md-4"><label class="form-label" for="customer_name">Name</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" maxlength="140" value="{{ old('customer_name') }}"></div>
                    <div class="col-md-4"><label class="form-label" for="customer_email">Email</label>
                        <input type="email" name="customer_email" id="customer_email" class="form-control" maxlength="190" value="{{ old('customer_email') }}"></div>
                    <div class="col-md-4"><label class="form-label" for="customer_phone">Phone (optional)</label>
                        <input type="text" name="customer_phone" id="customer_phone" class="form-control" maxlength="30" value="{{ old('customer_phone') }}"></div>
                    <div class="col-12 form-text">If this email already belongs to a customer, that customer is used and their details are not changed.</div>
                </div>
            </div>
        </div>

        {{-- 2. Tour --}}
        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">2. Tour and departure</h5>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="tour_id">Tour</label>
                        <select name="tour_id" id="tour_id" class="form-select">
                            <option value="">Choose a tour</option>
                            @foreach ($tours as $t)
                                <option value="{{ $t['id'] }}" @selected((string) old('tour_id') === (string) $t['id'])>{{ $t['title'] }} ({{ $t['currency'] }})</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-6"><label class="form-label" for="tour_departure_id">Departure</label>
                        <select name="tour_departure_id" id="tour_departure_id" class="form-select"></select></div>
                </div>
            </div>
        </div>

        {{-- 3. Travelers --}}
        <div class="card rounded-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold mb-0">3. Travelers</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="add-traveler">Add traveler</button>
                </div>
                <div id="travelers"></div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="insurance_selected" value="1" id="insurance" @checked(old('insurance_selected'))>
                    <label class="form-check-label" for="insurance">Add travel insurance</label>
                </div>
                <div class="mt-3"><label class="form-label" for="notes">Notes (optional)</label>
                    <textarea name="notes" id="notes" rows="2" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                    <div class="form-text">Do not type medical details or card numbers here.</div></div>
            </div>
        </div>

        {{-- Quote --}}
        <div class="card rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-2">Price</h5>
                <div id="quote" class="text-secondary">Choose a tour and a departure to see the price.</div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary px-4" id="submit-btn">Create booking</button>
            <a href="{{ route('admin.assisted.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
    </form>
    @endif
@endsection

@push('scripts')
@if ($tours->isNotEmpty())
<script>
(function () {
    const tours = @json($tours);
    const max = {{ $maxTravelers }};
    const minSearch = {{ $minSearch }};
    const old = {
        departure: @json(old('tour_departure_id')),
        travelers: @json(old('travelers', [['full_name' => '']])),
    };
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // ---- customer mode and search
    function setMode(mode) {
        $('customer_mode').value = mode;
        $('mode-existing').classList.toggle('d-none', mode !== 'existing');
        $('mode-new').classList.toggle('d-none', mode !== 'new');
        document.querySelectorAll('[data-mode]').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
    }
    document.querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
    setMode($('customer_mode').value || 'existing');

    let timer = null;
    $('customer_search').addEventListener('input', function () {
        clearTimeout(timer);
        const term = this.value.trim();
        if (term.length < minSearch) { $('customer_results').innerHTML = ''; return; }
        timer = setTimeout(async () => {
            const r = await fetch(@json(route('admin.assisted.customers')) + '?q=' + encodeURIComponent(term), {headers: {'Accept': 'application/json'}});
            if (!r.ok) { return; }
            const data = await r.json();
            $('customer_results').innerHTML = data.results.length ? data.results.map(c =>
                `<button type="button" class="list-group-item list-group-item-action" data-id="${c.id}" data-label="${esc(c.name)} (${esc(c.email)})">
                    <strong>${esc(c.name)}</strong> <span class="text-secondary">${esc(c.email)} ${c.phone ? ' · ' + esc(c.phone) : ''}</span>
                    <span class="badge bg-light text-dark border ms-2">${c.bookings} booking(s)</span></button>`).join('')
                : '<div class="list-group-item text-secondary">No customer found. Use "New customer".</div>';
        }, 300);
    });
    $('customer_results').addEventListener('click', function (e) {
        const b = e.target.closest('button[data-id]'); if (!b) return;
        $('customer_id').value = b.dataset.id;
        $('customer_chosen').textContent = 'Chosen: ' + b.dataset.label;
        $('customer_chosen').classList.remove('d-none');
        this.innerHTML = ''; $('customer_search').value = '';
    });
    if ($('customer_id').value) { $('customer_chosen').textContent = 'Customer chosen (selection kept).'; $('customer_chosen').classList.remove('d-none'); }

    // ---- tour and departures
    function fillDepartures() {
        const t = tours.find(x => String(x.id) === $('tour_id').value);
        const sel = $('tour_departure_id');
        sel.innerHTML = t ? t.departures.map(d => `<option value="${d.id}">${esc(d.date)}${d.late ? ' (inside cut-off)' : ''} · ${d.seats === null ? 'unlimited seats' : d.seats + ' seat(s) left'} · ${esc(t.currency)} ${Number(d.price).toFixed(2)} each</option>`).join('') : '<option value="">Choose a tour first</option>';
        if (t && old.departure) { sel.value = old.departure; old.departure = null; }
        refreshQuote();
    }

    // ---- travelers
    function addTraveler(v) {
        const box = $('travelers');
        if (box.children.length >= max) return;
        const i = box.children.length;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 traveler-row';
        row.innerHTML = `
            <div class="col-md-3"><input class="form-control" name="travelers[${i}][full_name]" maxlength="140" placeholder="Full name${i === 0 ? ' (lead traveler)' : ''}" value="${esc(v.full_name)}"></div>
            <div class="col-md-2"><input class="form-control" name="travelers[${i}][nationality]" maxlength="60" placeholder="Nationality" value="${esc(v.nationality)}"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="travelers[${i}][date_of_birth]" value="${esc(v.date_of_birth)}" title="Date of birth"></div>
            <div class="col-md-2"><input type="email" class="form-control" name="travelers[${i}][email]" maxlength="190" placeholder="Email" value="${esc(v.email)}"></div>
            <div class="col-md-2"><input class="form-control" name="travelers[${i}][phone]" maxlength="30" placeholder="Phone" value="${esc(v.phone)}"></div>
            <div class="col-md-1">${i === 0 ? '' : '<button type="button" class="btn btn-outline-danger w-100 remove">×</button>'}</div>`;
        box.appendChild(row);
        refreshQuote();
    }
    $('add-traveler').addEventListener('click', () => addTraveler({}));
    $('travelers').addEventListener('click', function (e) {
        if (!e.target.classList.contains('remove')) return;
        e.target.closest('.traveler-row').remove();
        // keep the field names in order
        [...this.children].forEach((row, i) => row.querySelectorAll('input').forEach(inp => { inp.name = inp.name.replace(/travelers\[\d+\]/, `travelers[${i}]`); }));
        refreshQuote();
    });
    Object.values(old.travelers).forEach(v => addTraveler(v || {}));
    if (!$('travelers').children.length) addTraveler({});

    // ---- live quote (the same calculation the server uses when it creates the booking)
    let qTimer = null;
    function refreshQuote() {
        clearTimeout(qTimer);
        qTimer = setTimeout(async () => {
            const tour = $('tour_id').value, dep = $('tour_departure_id').value, n = $('travelers').children.length;
            if (!tour || !dep || !n) { $('quote').textContent = 'Choose a tour and a departure to see the price.'; return; }
            const qs = new URLSearchParams({tour_id: tour, tour_departure_id: dep, travelers: n, insurance: $('insurance').checked ? 1 : 0});
            const r = await fetch(@json(route('admin.assisted.quote')) + '?' + qs, {headers: {'Accept': 'application/json'}});
            if (!r.ok) { $('quote').textContent = 'Could not calculate the price.'; return; }
            const d = await r.json();
            if (!d.ok) { $('quote').innerHTML = '<span class="text-danger">' + esc(d.error) + '</span>'; return; }
            const q = d.quote, f = (x) => q.currency + ' ' + Number(x).toFixed(2);
            $('quote').innerHTML = `${q.travelers} × ${f(q.unit_price)} = ${f(q.subtotal)}` + (q.insurance_amount > 0 ? `<br>Insurance: ${f(q.insurance_amount)}` : '')
                + `<div class="fs-5 fw-semibold text-body mt-1">Total: ${f(q.total)}</div><div class="small">${q.seats_left === null ? 'No seat limit on this departure' : q.seats_left + ' seat(s) left on this departure'}</div>`;
        }, 200);
    }
    $('tour_id').addEventListener('change', fillDepartures);
    $('tour_departure_id').addEventListener('change', refreshQuote);
    $('insurance').addEventListener('change', refreshQuote);
    $('assisted-form').addEventListener('submit', () => { $('submit-btn').disabled = true; });
    fillDepartures();
})();
</script>
@endif
@endpush
