
@extends('admin.layouts.master')

@section('title', 'Trust Items')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Trust Items</h4>
            <p class="text-muted mb-0">Manage homepage trust highlights and benefits.</p>
        </div>
        <a href="{{ route('admin.cms.trust-items.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Trust Item
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="70">Order</th>
                            <th>Trust Item</th>
                            <th>Description</th>
                            <th>Icon</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trustItems as $item)
                            <tr>
                                <td>{{ $item->sort_order }}</td>
                                <td><strong>{{ $item->title }}</strong></td>
                                <td>{{ \Illuminate\Support\Str::limit($item->description, 90) }}</td>
                                <td>{{ $item->icon ?: '—' }}</td>
                                <td>
                                    @if($item->status === 'published')
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td>{{ $item->published_at?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.cms.trust-items.edit', $item) }}"
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.cms.trust-items.destroy', $item) }}"
                                              onsubmit="return confirm('Delete this trust item?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No trust items added yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $trustItems->links() }}
        </div>
    </div>
</div>
@endsection
