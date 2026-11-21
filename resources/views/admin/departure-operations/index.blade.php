
@extends('admin.layouts.master')

@section('title', 'Departure Operations')

<style>
    .dep-wrap {
        padding: 24px 0;
        color: #243044;
    }
 
    .dep-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; }
    .dep-head h2 { font-size: 25px; font-weight: 750; margin: 0 0 5px; }
    .dep-muted { color: #778397; font-size: 13px; }
    .dep-card { background: #fff; border: 1px solid #e8edf3; border-radius: 14px; box-shadow: 0 5px 18px rgba(25,42,70,.04); }
    .dep-filter { padding: 17px; margin-bottom: 18px; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
    .dep-filter label { display: block; font-size: 12px; font-weight: 650; color: #64748b; margin-bottom: 6px; }
    .dep-filter input, .dep-filter select { width: 100%; height: 40px; border: 1px solid #dce3ec; border-radius: 8px; padding: 0 10px; background: #fff; }
    .dep-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 8px; padding: 9px 13px; font-size: 13px; font-weight: 650; text-decoration: none; cursor: pointer; }
    .dep-btn-primary { background: #173b63; color: #fff; }
    .dep-btn-light { background: #eef3f8; color: #30445c; }
    .dep-btn-danger { background: #fff0ef; color: #b42318; }
    .dep-btn-success { background: #eaf8f0; color: #167344; }
    .dep-table { width: 100%; border-collapse: collapse; }
    .dep-table th { background: #f8fafc; color: #68778c; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 13px 15px; white-space: nowrap; }
    .dep-table td { padding: 14px 15px; border-top: 1px solid #edf1f5; font-size: 13px; vertical-align: middle; }
    .dep-table tr:hover td { background: #fcfdff; }
    .dep-tour { font-weight: 700; color: #26364b; }
    .dep-code { font-size: 11px; color: #8995a5; margin-top: 3px; }
    .dep-pill { display: inline-flex; padding: 5px 9px; border-radius: 30px; font-size: 11px; font-weight: 700; }
    .dep-open { background: #e8f7ee; color: #167344; }
    .dep-closed { background: #fff1e8; color: #a64b12; }
    .dep-full { background: #fff0ef; color: #b42318; }
    .dep-seat { font-weight: 750; }
    .dep-progress { height: 6px; background: #edf1f5; border-radius: 9px; overflow: hidden; margin-top: 7px; min-width: 80px; }
    .dep-progress span { display: block; height: 100%; background: #2e8b63; }
    .dep-empty { text-align: center; padding: 45px 15px; color: #778397; }
    .dep-pagination { padding: 14px; }
    .dep-pagination nav { display: flex; justify-content: flex-end; }
    .dep-pagination svg { width: 18px; }
    .dep-pagination p { font-size: 12px; color: #778397; }

    @media (max-width: 900px) {
        .dep-filter { grid-template-columns: 1fr 1fr; }
        .dep-head { flex-direction: column; }
        .dep-table-wrap { overflow-x: auto; }
        .dep-table { min-width: 850px; }
    }

    @media (max-width: 560px) {
        .dep-filter { grid-template-columns: 1fr; }
    }
</style>


@section('content')
<div class="container-fluid dep-wrap">
    <div class="dep-head">
        <div>
            <h2>Departure Operations</h2>
            <div class="dep-muted">
                Monitor traveler capacity, seat availability and sales for every scheduled tour.
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="GET" class="dep-card dep-filter">
        <div>
            <label>Tour</label>
            <select name="tour_id">
                <option value="">All tours</option>
                @foreach($tours as $tour)
                    <option value="{{ $tour->id }}" @selected(request('tour_id') == $tour->id)>
                        {{ $tour->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label>Sales status</label>
            <select name="sales_status">
                <option value="">All statuses</option>
                <option value="open" @selected(request('sales_status') === 'open')>Open</option>
                <option value="closed" @selected(request('sales_status') === 'closed')>Closed</option>
            </select>
        </div>

        <div>
            <label>Departure from</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
        </div>

        <div>
            <label>Departure to</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}">
        </div>

        <div style="display:flex;gap:7px">
            <button class="dep-btn dep-btn-primary" type="submit">Filter</button>
            <a class="dep-btn dep-btn-light" href="{{ route('admin.departure-operations.index') }}">Reset</a>
        </div>
    </form>

    <div class="dep-card dep-table-wrap">
        <table class="dep-table">
            <thead>
                <tr>
                    <th>Tour / Departure</th>
                    <th>Departure date</th>
                    <th>Capacity</th>
                    <th>Travelers booked</th>
                    <th>Seats left</th>
                    <th>Sales</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($departures as $departure)
                    @php
                        $booked = (int) ($departure->travelers_booked ?? 0);
                        $capacity = (int) $departure->capacity;
                        $left = max(0, $capacity - $booked);
                        $percent = $capacity > 0 ? min(100, round(($booked / $capacity) * 100)) : 0;
                    @endphp

                    <tr>
                        <td>
                            <div class="dep-tour">{{ $departure->tour->title ?? 'Tour unavailable' }}</div>
                            <div class="dep-code">Departure #{{ $departure->id }}</div>
                        </td>

                        <td>{{ $departure->departs_on?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $capacity }}</td>

                        <td>
                            <span class="dep-seat">{{ $booked }}</span>
                            <div class="dep-progress">
                                <span style="width:{{ $percent }}%"></span>
                            </div>
                        </td>

                        <td>
                            <span class="dep-seat">{{ $left }}</span>
                            <span class="dep-muted">seats</span>
                        </td>

                        <td>
                            <span class="dep-pill {{ $left === 0 ? 'dep-full' : ($departure->status === 'open' ? 'dep-open' : 'dep-closed') }}">
                                {{ $left === 0 ? 'Full' : ucfirst($departure->status) }}
                            </span>
                        </td>

                        <td>
                            <a class="dep-btn dep-btn-primary"
                               href="{{ route('admin.departure-operations.show', $departure) }}">
                                Manage departure
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="dep-empty">No departures match these filters.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="dep-pagination">
            {{ $departures->links() }}
        </div>
    </div>
</div>
@endsection
