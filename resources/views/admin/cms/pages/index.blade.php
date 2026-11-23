
@extends('admin.layouts.master')

@section('title', 'CMS Pages')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="mb-1">CMS Pages</h4>
            <p class="text-muted mb-0">Manage your website pages and SEO content.</p>
        </div>

        <a href="{{ route('admin.cms.pages.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add New Page
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <form method="GET" action="{{ route('admin.cms.pages.index') }}" class="row g-3 mb-4">

                <div class="col-md-5">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Search page title or slug..."
                           value="{{ request('search') }}">
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="published" @selected(request('status') === 'published')>Published</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="page_type" class="form-select">
                        <option value="">All Page Types</option>
                        <option value="homepage" @selected(request('page_type') === 'homepage')>Homepage</option>
                        <option value="standard" @selected(request('page_type') === 'standard')>Standard</option>
                        <option value="legal" @selected(request('page_type') === 'legal')>Legal</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search"></i> Search
                    </button>

                    <a href="{{ route('admin.cms.pages.index') }}"
                       class="btn btn-outline-secondary"
                       title="Reset filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Page Title</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Published Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($pages as $page)
                            <tr>
                                <td>{{ $pages->firstItem() + $loop->index }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $page->title }}</div>
                                    <small class="text-muted">
                                        {{ $page->template ?? 'default' }}
                                    </small>
                                </td>

                                <td>
                                    <code>/{{ $page->slug }}</code>
                                </td>

                                <td>
                                    <span class="badge bg-info-subtle text-info">
                                        {{ ucfirst($page->page_type) }}
                                    </span>
                                </td>

                                <td>
                                    @if($page->status === 'published')
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>

                                <td>
                                    {{ $page->published_at?->format('d M Y') ?? '—' }}
                                </td>

                                <td>
                                    <div class="d-flex justify-content-end gap-2">

                                        <a href="{{ route('admin.cms.pages.edit', $page) }}"
                                           class="btn btn-sm btn-outline-primary"
                                           title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.cms.pages.publish', $page) }}"
                                              onsubmit="return confirm('Are you sure you want to {{ $page->status === 'published' ? 'unpublish' : 'publish' }} this page?')">
                                            @csrf
                                            @method('PATCH')

                                            <button type="submit"
                                                    class="btn btn-sm {{ $page->status === 'published' ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                    title="{{ $page->status === 'published' ? 'Unpublish' : 'Publish' }}">
                                                <i class="bi {{ $page->status === 'published' ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                            </button>
                                        </form>

                                        @if($page->page_type !== 'homepage')
                                            <form method="POST"
                                                  action="{{ route('admin.cms.pages.destroy', $page) }}"
                                                  onsubmit="return confirm('Delete this page?')">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-file-earmark-text fs-2 d-block mb-2"></i>
                                    No CMS pages found.
                                    <div class="mt-2">
                                        <a href="{{ route('admin.cms.pages.create') }}">Create your first page</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $pages->links() }}
            </div>

        </div>
    </div>
</div>
@endsection
