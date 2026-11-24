
@extends('admin.layouts.master')

@section('content')

<div class="page-wrapper">
    <div class="page-content">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Tour Packages</h4>
                <p class="text-muted mb-0">
                    Manage packages for {{ $tour->title }}
                </p>
            </div>

            <div>
                <a href="{{ route('admin.tours.edit', $tour->id) }}"
                   class="btn btn-light">
                    <i class="material-icons-outlined">arrow_back</i>
                    Back to Tour
                </a>

                <a href="{{ route('admin.tours.packages.create', $tour->id) }}"
                   class="btn btn-primary">
                    <i class="material-icons-outlined">add</i>
                    Add Package
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close"
                        data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-body">

                <h5 class="mb-3">Available Packages</h5>

                @if($packages->count())

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Package</th>
                                    <th>Price Per Person</th>
                                    <th>Itinerary Days</th>
                                    <th>Status</th>
                                    <th width="160">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($packages as $package)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>

                                        <td>
                                            <strong>{{ $package->name }}</strong>
                                            <div class="small text-muted">
                                                {{ Str::limit($package->description, 70) }}
                                            </div>
                                        </td>

                                        <td>
                                            {{ $tour->currency }}
                                            {{ number_format($package->price, 2) }}
                                        </td>

                                        <td>
                                            {{ $package->days_count }} days
                                        </td>

                                        <td>
                                            @if($package->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>

                                        <td>
                                            <a href="{{ route('admin.tours.packages.edit', [$tour->id, $package->id]) }}"
                                               class="btn btn-sm btn-primary">
                                                <i class="material-icons-outlined">edit</i>
                                            </a>

                                            <form action="{{ route('admin.tours.packages.destroy', [$tour->id, $package->id]) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this package?');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="btn btn-sm btn-danger">
                                                    <i class="material-icons-outlined">delete</i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>

                @else

                    <div class="text-center py-5">
                        <i class="material-icons-outlined"
                           style="font-size:55px;color:#aaa;">
                            inventory_2
                        </i>

                        <h5 class="mt-3">No Packages Added</h5>

                        <p class="text-muted">
                            Add Silver, Gold and Platinum packages for this tour.
                        </p>

                        <a href="{{ route('admin.tours.packages.create', $tour->id) }}"
                           class="btn btn-primary">
                            Add First Package
                        </a>
                    </div>

                @endif

            </div>
        </div>

    </div>
</div>

@endsection
