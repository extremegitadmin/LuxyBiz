@extends('admin.layouts.app')
@section('title', 'Departures')

@section('content')
<h1 class="page-title h3 mb-3">Departures</h1>

<form method="POST" action="{{ route('admin.departures.store') }}" class="panel panel-body mb-3 row g-2 align-items-end">
    @csrf
    <div class="col-12 fw-semibold">Add a departure</div>
    <div class="col-md-4"><label class="form-label small" for="n-tour">Tour</label>
        <select id="n-tour" name="tour_id" class="form-select">
            <option value="">Choose a tour</option>
            @foreach ($tours as $t)<option value="{{ $t->id }}" @selected(old('tour_id', request('tour_id')) == $t->id)>{{ $t->title }}</option>@endforeach
        </select></div>
    <div class="col-md-2"><label class="form-label small" for="n-date">Departure date</label>
        <input id="n-date" type="date" name="departs_on" class="form-control" value="{{ old('departs_on') }}" min="{{ today()->toDateString() }}"></div>
    <div class="col-md-2"><label class="form-label small" for="n-cap">Capacity</label>
        <input id="n-cap" type="number" min="1" name="capacity" class="form-control" value="{{ old('capacity', 12) }}"></div>
    <div class="col-md-2"><label class="form-label small" for="n-price">Price override</label>
        <input id="n-price" type="number" step="0.01" min="0" name="price_override" class="form-control" value="{{ old('price_override') }}" placeholder="Tour price"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Add departure</button></div>
</form>

<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select name="tour_id" class="form-select w-auto" aria-label="Filter by tour">
        <option value="">All tours</option>
        @foreach ($tours as $t)<option value="{{ $t->id }}" @selected(request('tour_id') == $t->id)>{{ $t->title }}</option>@endforeach
    </select>
    <select name="status" class="form-select w-auto" aria-label="Filter by status">
        <option value="">Any status</option>
        <option value="open" @selected(request('status') === 'open')>Open</option>
        <option value="closed" @selected(request('status') === 'closed')>Closed</option>
    </select>
    <div class="form-check align-self-center ms-1">
        <input class="form-check-input" type="checkbox" name="past" value="1" id="past" @checked(request()->boolean('past'))>
        <label class="form-check-label" for="past">Show past departures</label>
    </div>
    <button class="btn btn-outline-primary">Apply</button>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Tour</th><th>Date</th><th>Capacity</th><th>Price override</th><th>Status</th><th>Booked</th><th class="text-end">Save</th></tr></thead>
            <tbody>
            @forelse ($departures as $d)
                @php $booked = (int) $d->seats_booked; $form = 'dep-' . $d->id; @endphp
                <tr>
                    <td>{{ $d->tour?->title }}
                        <div class="small text-secondary">Tour price {{ $d->tour?->currency }} {{ number_format($d->tour?->base_price ?? 0, 2) }}</div></td>
                    <td><input form="{{ $form }}" type="date" name="departs_on" value="{{ $d->departs_on->toDateString() }}" class="form-control form-control-sm" aria-label="Departure date"></td>
                    <td style="width:100px"><input form="{{ $form }}" type="number" min="{{ max(1, $booked) }}" name="capacity" value="{{ $d->capacity }}" class="form-control form-control-sm" aria-label="Capacity"></td>
                    <td style="width:140px"><input form="{{ $form }}" type="number" step="0.01" min="0" name="price_override" value="{{ $d->price_override }}" class="form-control form-control-sm" aria-label="Price override"></td>
                    <td style="width:120px"><select form="{{ $form }}" name="status" class="form-select form-select-sm" aria-label="Status">
                        <option value="open" @selected($d->status === 'open')>Open</option>
                        <option value="closed" @selected($d->status === 'closed')>Closed</option></select></td>
                    <td>{{ $booked }} of {{ $d->capacity }}</td>
                    <td class="text-end text-nowrap">
                        <form id="{{ $form }}" method="POST" action="{{ route('admin.departures.update', $d) }}" class="d-inline">@csrf @method('PUT')
                            <button class="btn btn-sm btn-primary">Save</button></form>
                        @if ($booked === 0 && ! $d->bookings()->exists())
                            <form method="POST" action="{{ route('admin.departures.destroy', $d) }}" class="d-inline" onsubmit="return confirm('Delete this departure?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-4 text-secondary">No departures found. Add one above to open a tour for booking.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $departures->links() }}</div>
@endsection
