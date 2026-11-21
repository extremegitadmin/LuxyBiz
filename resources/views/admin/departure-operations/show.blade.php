
@extends('admin.layouts.master')

@section('title', 'Departure Details')

<style>
    .op-wrap {
        padding: 24px 0;
        color: #243044;
    }

    .op-top {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: center;
        margin-bottom: 20px;
    }

    .op-top h2 {
        font-size: 24px;
        font-weight: 750;
        margin: 0 0 5px;
    }

    .op-muted {
        color: #7b8798;
        font-size: 13px;
    }

    .op-card {
        background: #fff;
        border: 1px solid #e8edf3;
        border-radius: 14px;
        box-shadow: 0 5px 18px rgba(25,42,70,.04);
    }

    .op-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }

    .op-stat {
        padding: 18px;
    }

    .op-stat small {
        display: block;
        color: #7b8798;
        font-size: 12px;
        margin-bottom: 9px;
    }

    .op-stat strong {
        font-size: 27px;
        line-height: 1.1;
    }

    .op-stat .sub {
        font-size: 11px;
        color: #8b96a6;
        margin-top: 6px;
    }

    .op-panel {
        padding: 20px;
        margin-bottom: 18px;
    }

    .op-panel h3 {
        font-size: 16px;
        font-weight: 750;
        margin: 0 0 16px;
    }

    .op-info {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
    }

    .op-info label {
        display: block;
        color: #8591a1;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 5px;
    }

    .op-info strong {
        font-size: 14px;
    }

    /* Action buttons */
    .op-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .op-actions form {
        margin: 0;
        display: flex;
        align-items: center;
    }

    .op-btn {
        min-height: 42px;
        box-sizing: border-box;
        padding: 10px 16px;
        border: 0;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        line-height: 20px;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        white-space: nowrap;
    }

    .op-primary {
        background: #173b63;
        color: white;
    }

    .op-primary:hover {
        background: #102d4d;
        color: white;
    }

    .op-green {
        background: #e8f7ee;
        color: #167344;
    }

    .op-green:hover {
        background: #d5f0df;
        color: #125d37;
    }

    .op-orange {
        background: #fff1e8;
        color: #a64b12;
    }

    .op-orange:hover {
        background: #ffe4d4;
        color: #873b0c;
    }

    .op-table {
        width: 100%;
        border-collapse: collapse;
    }

    .op-table th {
        background: #f8fafc;
        color: #6f7d90;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        padding: 12px;
    }

    .op-table td {
        padding: 13px 12px;
        border-top: 1px solid #edf1f5;
        font-size: 13px;
    }

    .op-name {
        font-weight: 700;
    }

    .op-note {
        max-width: 240px;
        white-space: normal;
        color: #667386;
    }

    .op-tag {
        font-size: 10px;
        border-radius: 20px;
        background: #edf3f8;
        padding: 4px 7px;
        color: #52647a;
    }

    .op-empty {
        text-align: center;
        padding: 28px;
        color: #8792a1;
    }

    @media (max-width: 850px) {
        .op-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .op-info {
            grid-template-columns: repeat(2, 1fr);
        }

        .op-table-wrap {
            overflow: auto;
        }

        .op-table {
            min-width: 700px;
        }
    }

    @media (max-width: 540px) {
        .op-top {
            flex-direction: column;
            align-items: stretch;
        }

        .op-actions {
            justify-content: flex-start;
        }

        .op-grid {
            grid-template-columns: 1fr 1fr;
        }

        .op-info {
            grid-template-columns: 1fr 1fr;
        }

        .op-stat {
            padding: 14px;
        }

        .op-stat strong {
            font-size: 23px;
        }
    }
</style>

@section('content')
<div class="container-fluid op-wrap">

    {{-- Page Header --}}
    <div class="op-top">
        <div>
            <a href="{{ route('admin.departure-operations.index') }}" class="op-muted">
                <i class="bi bi-arrow-left"></i> All departures
            </a>

            <h2 style="margin-top:9px">
                {{ $departure->tour->title ?? 'Tour departure' }}
            </h2>

            <div class="op-muted">
                Departure #{{ $departure->id }} ·
                {{ $departure->departs_on?->format('l, d F Y') ?? 'Date not set' }}
            </div>
        </div>

        <div class="op-actions">

            <a class="op-btn op-primary"
               href="{{ route('admin.departure-operations.manifest', $departure) }}"
               target="_blank">
                <i class="bi bi-printer"></i>
                Print Traveler Manifest
            </a>

            <form method="POST"
                  action="{{ route('admin.departure-operations.toggle-sales', $departure) }}"
                  onsubmit="return confirm('{{ $departure->status === 'open' ? 'Close' : 'Reopen' }} sales for this departure?')">

                @csrf
                @method('PATCH')

                <button class="op-btn {{ $departure->status === 'open' ? 'op-orange' : 'op-green' }}"
                        type="submit">

                    <i class="bi {{ $departure->status === 'open' ? 'bi-lock' : 'bi-unlock' }}"></i>

                    {{ $departure->status === 'open' ? 'Close Sales' : 'Reopen Sales' }}
                </button>
            </form>

        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    {{-- Statistics --}}
    <div class="op-grid">

        <div class="op-card op-stat">
            <small>Maximum capacity</small>
            <strong>{{ $departure->capacity }}</strong>
            <div class="sub">Total traveler places</div>
        </div>

        <div class="op-card op-stat">
            <small>Travelers booked</small>
            <strong>{{ $seatsBooked }}</strong>
            <div class="sub">Confirmed individual travelers</div>
        </div>

        <div class="op-card op-stat">
            <small>Seats remaining</small>
            <strong>{{ $seatsLeft }}</strong>
            <div class="sub">Capacity less confirmed travelers</div>
        </div>

        <div class="op-card op-stat">
            <small>Sales status</small>

            <strong style="font-size:21px;color:{{ $departure->status === 'open' && $seatsLeft > 0 ? '#16804a' : '#b45309' }}">
                {{ $seatsLeft === 0 ? 'Full' : ucfirst($departure->status) }}
            </strong>

            <div class="sub">
                {{ $departure->status === 'open' && $seatsLeft > 0
                    ? 'Bookings can be accepted, subject to checkout capacity validation'
                    : 'New sales are not available' }}
            </div>
        </div>

    </div>

    {{-- Departure Information --}}
    <div class="op-card op-panel">

        <h3>Departure information</h3>

        <div class="op-info">

            <div>
                <label>Tour</label>
                <strong>{{ $departure->tour->title ?? '—' }}</strong>
            </div>

            <div>
                <label>Departure date</label>
                <strong>{{ $departure->departs_on?->format('d M Y') ?? '—' }}</strong>
            </div>

            <div>
                <label>Capacity</label>
                <strong>{{ $departure->capacity }} travelers</strong>
            </div>

            <div>
                <label>Departure price override</label>
                <strong>
                    {{ $departure->price_override !== null
                        ? number_format((float) $departure->price_override, 2) . ' ' . ($departure->tour->currency ?? '')
                        : 'Use tour base price' }}
                </strong>
            </div>

            <div>
                <label>Sales status</label>
                <strong>{{ ucfirst($departure->status) }}</strong>
            </div>

            <div>
                <label>Departure reference</label>
                <strong>#{{ $departure->id }}</strong>
            </div>

        </div>
    </div>

    {{-- Confirmed Travelers --}}
    <div class="op-card op-panel">

        <h3>
            Confirmed travelers
            <span class="op-tag">{{ $travelers->count() }} people</span>
        </h3>

        <div class="op-table-wrap">
            <table class="op-table">

                <thead>
                    <tr>
                        <th>Traveler</th>
                        <th>Booking reference</th>
                        <th>Nationality</th>
                        <th>Payment</th>
                        <th>Health / operational note</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($travelers as $traveler)
                        <tr>

                            <td>
                                <div class="op-name">
                                    {{ $traveler->full_name }}
                                </div>

                                @if($traveler->is_lead)
                                    <span class="op-tag">Lead traveler</span>
                                @endif
                            </td>

                            <td>{{ $traveler->reference }}</td>

                            <td>{{ $traveler->nationality ?: '—' }}</td>

                            <td>
                                {{ ucfirst(str_replace('_', ' ', $traveler->payment_status ?? '—')) }}
                            </td>

                            <td class="op-note">
                                {{ collect([$traveler->health_notes, $traveler->flag_note])->filter()->implode(' · ') ?: '—' }}
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="op-empty">
                                No confirmed travelers are assigned to this departure yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </div>

</div>
@endsection
