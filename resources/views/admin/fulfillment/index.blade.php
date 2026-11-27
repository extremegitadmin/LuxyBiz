@extends('admin.layouts.master')
@section('title', 'Insurance Options')

@section('content')
 <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title">Fulfillment Desk</div>
        <div class="ms-auto">
            <div class="btn-group">
                @can('admin.fulfillment.manifest')
                    @if ($selected)
                        <a class="btn btn-outline-primary ms-auto" href="{{ route('admin.fulfillment.manifest', $selected) }}"><i class="bi bi-printer me-1"></i>Daily manifest</a>
                    @endif
                @endcan
            </div>
        </div>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif 
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="row">
		<div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-4">Create Insurance Option</h5>
                    <form class="row g-3" method="POST" action="{{ route('admin.insurance.store') }}">
                        @csrf
                        <div class="col-md-9">
                            <label class="form-label">Label</label>
                            <input name="name" class="form-control" required maxlength="120">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price each</label>
                            <input name="price" type="number" step="0.01" min="0" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" maxlength="1000"></textarea>
                        </div>                    
                        <div class="col-md-12">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-primary px-4">Save</button>
                                <button type="button" class="btn btn-secondary px-4">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
		<div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10">
                            <h5 class="mb-4">Insurance Options List</h5>
                        </div>
                    </div>         
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Traveler</th>
                                    <th>Booking</th>
                                    <th>Insurance</th>
                                    <th>Medical</th>
                                    <th style="min-width:240px">Arrival details</th>
                                    <th style="min-width:220px">Flag for ground team</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($travelers as $t)
                                @php $f = 'tr-' . $t->id; @endphp
                                <tr>
                                    <td class="fw-medium">{{ $t->full_name }}
                                        <div class="small text-secondary">{{ $t->phone }}</div>
                                    </td>
                                    <td>@if ($isAdmin)<a href="{{ route('admin.bookings.show', $t->booking_id) }}">{{ $t->booking->reference }}</a>@else{{ $t->booking->reference }}@endif</td>
                                    <td>@if ($t->booking->insurance_selected)<span class="badge badge-soft-success">Yes</span>@else<span class="badge badge-soft-secondary">No</span>@endif</td>
                                    <td>@if ($t->health_notes)<span class="badge badge-soft-danger"><i class="bi bi-heart-pulse me-1"></i>Alert</span><div class="small mt-1" style="max-width:220px">{{ \Illuminate\Support\Str::limit($t->health_notes, 90) }}</div>@else<span class="text-secondary">None</span>@endif</td>
                                    <td><input form="{{ $f }}" name="arrival_details" value="{{ $t->arrival_details }}" class="form-control form-control-sm" placeholder="Flight, time, airport" aria-label="Arrival details"></td>
                                    <td><input form="{{ $f }}" name="flag_note" value="{{ $t->flag_note }}" class="form-control form-control-sm" placeholder="e.g. needs wheelchair assistance" aria-label="Flag"></td>
                                    <td class="text-end">
                                        <form id="{{ $f }}" method="POST" action="{{ route('admin.fulfillment.traveler', $t) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-primary">Save</button></form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
