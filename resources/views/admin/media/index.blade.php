
@extends('admin.layouts.master')
@php
    use Illuminate\Support\Facades\Storage;
@endphp
@section('content')
<div class="container-fluid">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Media Library</h4>
            <p class="text-muted mb-0">Manage images, videos and documents.</p>
        </div>
    </div>

    {{-- Alerts --}}
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

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Upload Section --}}
    <div class="card mb-4">
        <div class="card-header">
            <strong><i class="fas fa-cloud-upload-alt me-2"></i>Upload Media</strong>
        </div>

        <div class="card-body">
            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data">

                @csrf

                <div class="d-flex align-items-end gap-3">
                    <div class="flex-grow-1">
                        <label class="form-label">Choose Files</label>
                        <input type="file"
                            name="files[]"
                            id="mediaFiles"
                            class="form-control"
                            multiple
                            accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.mp4,.webm,.pdf,.doc,.docx,.xls,.xlsx"
                            required>

                        <small class="text-muted">
                            Allowed: Images, videos, PDF, Word and Excel files.
                            Maximum size: 20 MB per file.
                        </small>

                        <div id="selectedFiles" class="mt-2 text-muted"></div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Upload Files
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Search and Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admin.media.index') }}" method="GET">
                <div class="row align-items-end">

                    <div class="col-md-6 mb-2">
                        <label class="form-label">Search Media</label>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Search filename or alt text..."
                               value="{{ request('search') }}">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="form-label">Media Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="image" @selected(request('type') === 'image')>Images</option>
                            <option value="video" @selected(request('type') === 'video')>Videos</option>
                            <option value="document" @selected(request('type') === 'document')>Documents</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Search
                        </button>

                        <a href="{{ route('admin.media.index') }}"
                           class="btn btn-secondary">
                            Reset
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- Media Listing --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Uploaded Files</strong>
            <span class="badge bg-primary">
                {{ $media->total() }} Files
            </span>
        </div>

        <div class="card-body">

            <div class="row">

                @forelse($media as $item)

                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">

                        

                            {{-- Preview --}}
                            <div class="bg-light d-flex align-items-center justify-content-center"
                                 style="height:180px; overflow:hidden;">

                                @if(str_starts_with($item->mime_type, 'image/'))
                                    <img src="{{ asset('storage/' . $item->file_path) }}" alt="{{ $item->alt_text ?? $item->name }}" style="width:100px; height:80px; object-fit:cover;">

                                   

                                @elseif(str_starts_with($item->mime_type, 'video/'))
                                    <i class="fas fa-video fa-4x text-secondary"></i>

                                @elseif($item->mime_type === 'application/pdf')
                                    <i class="fas fa-file-pdf fa-4x text-danger"></i>

                                @elseif(str_contains($item->mime_type, 'word'))
                                    <i class="fas fa-file-word fa-4x text-primary"></i>

                                @elseif(str_contains($item->mime_type, 'excel') || str_contains($item->mime_type, 'spreadsheet'))
                                    <i class="fas fa-file-excel fa-4x text-success"></i>

                                @else
                                    <i class="fas fa-file fa-4x text-secondary"></i>
                                @endif

                            </div>

                            {{-- Details --}}
                            <div class="card-body">

                                <h6 class="text-truncate"
                                    title="{{ $item->name }}">
                                    {{ $item->name }}
                                </h6>

                                <small class="text-muted d-block">
                                    {{ strtoupper(pathinfo($item->file_name, PATHINFO_EXTENSION)) }}
                                    |
                                    {{ number_format($item->file_size / 1024, 2) }} KB
                                </small>

                                @if($item->width && $item->height)
                                    <small class="text-muted d-block">
                                        {{ $item->width }} × {{ $item->height }} px
                                    </small>
                                @endif

                                <small class="text-muted d-block mb-2">
                                    {{ $item->created_at->format('d M Y') }}
                                </small>

                                @if($item->alt_text)
                                    <small class="d-block text-muted text-truncate"
                                           title="{{ $item->alt_text }}">
                                        Alt: {{ $item->alt_text }}
                                    </small>
                                @else
                                    <small class="text-warning d-block">
                                        Alt text not added
                                    </small>
                                @endif

                            </div>

                            {{-- Actions --}}
                            <div class="card-footer bg-white">
                                <div class="d-flex gap-2">

                                    <a href="{{ route('admin.media.show', $item->id) }}"
                                       class="btn btn-sm btn-primary flex-fill">
                                        <i class="fas fa-eye"></i> View
                                    </a>

                                    <a href="{{ route('admin.media.where-used', $item->id) }}"
                                       class="btn btn-sm btn-info flex-fill">
                                        <i class="fas fa-link"></i> Used
                                    </a>

                                </div>
                            </div>

                        </div>
                    </div>

                @empty

                    <div class="col-12 text-center py-5">
                        <i class="fas fa-photo-video fa-3x text-muted mb-3"></i>
                        <h5>No Media Found</h5>
                        <p class="text-muted">
                            Upload your first media file to get started.
                        </p>
                    </div>

                @endforelse

            </div>

            {{-- Pagination --}}
            <div class="mt-3">
                {{ $media->links() }}
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('mediaFiles');
    const output = document.getElementById('selectedFiles');

    input.addEventListener('change', function () {
        if (!this.files.length) {
            output.textContent = '';
            return;
        }

        const names = Array.from(this.files).map(file => file.name);

        output.innerHTML = '<strong>Selected files:</strong><br>' +
            names.map(name => {
                const span = document.createElement('span');
                span.textContent = name;
                return span.outerHTML;
            }).join('<br>');
    });
});
</script>
@endsection
