
<div>
    <div class="page-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Tour Packages</h4>
                    <p class="text-muted mb-0">
                        Manage packages for {{ $tour->title }}
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('admin.tours.edit', $tour->id) }}"
                       class="btn btn-outline-secondary">
                        Back to Tour
                    </a>

                    <a href="{{ route('admin.tours.packages.create', $tour->id) }}"
                       class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>
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

                    @if($packages->count() > 0)

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Package</th>
                                        <th>Price Per Person</th>
                                        <th>Itinerary Days</th>
                                        <th>Status</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach($packages as $index => $package)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>

                                            <td>
                                                <strong>{{ $package->name }}</strong>
                                            </td>

                                            <td>
                                                {{ $tour->currency ?? 'USD' }}
                                                {{ number_format($package->price, 2) }}
                                            </td>

                                            <td>{{ $package->days_count }}</td>

                                            <td>
                                                @if($package->is_active)
                                                    <span class="badge bg-success">
                                                        Active
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">
                                                        Inactive
                                                    </span>
                                                @endif
                                            </td>

                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('admin.tours.packages.edit', [$tour->id, $package->id]) }}"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>

                                                    <form method="POST"
                                                          action="{{ route('admin.tours.packages.destroy', [$tour->id, $package->id]) }}"
                                                          onsubmit="return confirm('Are you sure you want to delete this package?')">
                                                        @csrf
                                                        @method('DELETE')

                                                        <button type="submit"
                                                                class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @else

                        <div class="text-center py-5">
                            <i class="bi bi-box-seam display-4 text-muted"></i>

                            <h5 class="mt-3">No Packages Added</h5>

                            <p class="text-muted">
                                Add Silver, Gold or Platinum packages for this tour.
                            </p>

                            <a href="{{ route('admin.tours.packages.create', $tour->id) }}"
                               class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>
                                Add First Package
                            </a>
                        </div>

                    @endif

                </div>
            </div>

        </div>
    </div>
</div>
