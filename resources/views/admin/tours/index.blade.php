@extends('admin.layouts.master')
@section('title', 'Tours')
@push('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
@endpush
@section('content')
    <div class="d-flex align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Tours</h1>
        <a href="{{ route('admin.tours.create') }}" class="btn btn-primary ms-auto">Add tour</a>
    </div>

    <div class="row">
        <div class="col-12 col-lg-12">
            <div class="card">
                <div class="card-body">                  
                    <form method="GET" class="panel panel-body mb-3 row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small" for="q">Search</label>
                            <input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Tour title">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="status">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">All</option>
                                @foreach (\App\Models\Tour::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="objective_id">Objective</label>
                            <select id="objective_id" name="objective_id" class="form-select">
                                <option value="">All</option>
                                @foreach ($objectives as $o)<option value="{{ $o->id }}" @selected(request('objective_id') == $o->id)>{{ $o->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" for="duration">Duration</label>
                            <select id="duration" name="duration" class="form-select">
                                <option value="">Any</option>
                                @foreach ($durations as $d)<option value="{{ $d }}" @selected(request('duration') == $d)>{{ $d }} days</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary">Filter</button>
                            <a href="{{ route('admin.tours.index') }}" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>                        
                </div>
            </div>
        </div>      
    </div>

    <div class="row">
        <div class="col-12 col-lg-12">
            <div class="card">
                <div class="card-body">     
                    <table id="example2" class="table align-middle">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Tour</th>
                                <th>Objective</th>
                                <th>Duration</th>
                                <th>From</th>
                                <th>Itinerary</th>
                                <th>Departures</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($tours as $t)
                            <tr>
                                <td>
                                    @if ($t->hero_image)
                                        <img class="thumb"
                                            src="{{ asset('storage/' . $t->hero_image) }}"
                                            alt="">
                                    @else
                                        <div class="thumb"></div>
                                    @endif
                                </td>

                                <td>
                                    <a class="fw-medium"
                                    href="{{ route('admin.tours.edit', $t) }}">
                                        {{ $t->title }}
                                    </a>
                                    <div class="small text-secondary">
                                        {{ $t->destination }} · updated {{ $t->updated_at->diffForHumans() }}
                                    </div>
                                </td>

                                <td>{{ $t->objective?->name ?? '—' }}</td>
                                <td>{{ $t->duration_days }} days</td>
                                <td>{{ $t->currency }} {{ number_format($t->base_price, 2) }}</td>
                                <td>{{ $t->days_count }} days</td>

                                <td>
                                    <a href="{{ route('admin.departures.index', ['tour_id' => $t->id]) }}">
                                        {{ $t->departures_count }}
                                    </a>
                                </td>

                                <td>
                                    @include('admin.partials.status', ['value' => $t->status])
                                </td>

                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('admin.tours.edit', $t) }}">Edit</a>

                                    @if ($t->status !== 'archived')
                                        <form class="d-inline" method="POST"
                                            action="{{ route('admin.tours.publish', $t) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-secondary">
                                                {{ $t->status === 'published' ? 'Unpublish' : 'Publish' }}
                                            </button>
                                        </form>
                                    @endif

                                    <form class="d-inline" method="POST"
                                        action="{{ route('admin.tours.duplicate', $t) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            Duplicate
                                        </button>
                                    </form>

                                    @if ($t->status !== 'archived')
                                        <form class="d-inline" method="POST"
                                            action="{{ route('admin.tours.destroy', $t) }}"
                                            onsubmit="return confirm('Archive this tour?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                Archive
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>    
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')

<!-- DataTables Core -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

<!-- Export Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Export Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function () {

    $('#example2').DataTable({

        pageLength: 10,

        lengthMenu: [10, 25, 50, 100],

        searching: true,

        ordering: true,

        paging: true,

        info: true,

        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rtip',

        buttons: [
            'copy',
            'excel',
            'csv',
            'pdf',
            'print'
        ],

        columnDefs: [
            {
                orderable: false,
                targets: [0, 8]
            }
        ]

    });

});
</script>

@endpush