
@extends('admin.layouts.master')

@section('title', 'Testimonials')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Testimonials</h4>
            <p class="text-muted mb-0">Manage customer reviews and testimonials.</p>
        </div>
        <a href="{{ route('admin.cms.testimonials.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Testimonial
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
                            <th width="60">Order</th>
                            <th>Customer</th>
                            <th>Testimonial</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($testimonials as $testimonial)
                            <tr>
                                <td>{{ $testimonial->sort_order }}</td>
                                <td>
                                    <strong>{{ $testimonial->customer_name }}</strong>
                                    @if($testimonial->designation)
                                        <br><small class="text-muted">{{ $testimonial->designation }}</small>
                                    @endif
                                    @if($testimonial->location)
                                        <br><small class="text-muted">{{ $testimonial->location }}</small>
                                    @endif
                                </td>
                                <td style="min-width:250px">
                                    {{ \Illuminate\Support\Str::limit($testimonial->testimonial, 100) }}
                                </td>
                                <td>
                                    @if($testimonial->rating)
                                        {{ $testimonial->rating }}/5
                                        <span class="text-warning">★</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($testimonial->status === 'published')
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td>{{ $testimonial->published_at?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.cms.testimonials.edit', $testimonial) }}"
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.cms.testimonials.destroy', $testimonial) }}"
                                              onsubmit="return confirm('Delete this testimonial?')">
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
                                    No testimonials added yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $testimonials->links() }}
        </div>
    </div>
</div>
@endsection
