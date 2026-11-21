
@extends('admin.layouts.master')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4>Media Details</h4>
            <p class="text-muted mb-0">View and manage uploaded media.</p>
        </div>

        <a href="{{ route('admin.media.index') }}" class="btn btn-secondary">
            Back to Media Library
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">

        <div class="col-md-5">
            <div class="card">
                <div class="card-body text-center">

                    @if($media->file_type === 'image')
                        <img
                            src="{{ asset('storage/' . $media->file_path) }}"
                            alt="{{ $media->alt_text ?? $media->name }}"
                            class="img-fluid rounded mb-3"
                            style="max-height:350px;"
                        >
                    @elseif($media->file_type === 'video')
                        <video controls class="w-100 mb-3">
                            <source src="{{ asset('storage/' . $media->file_path) }}">
                        </video>
                    @else
                        <div class="py-5">
                            <i class="fas fa-file fa-4x text-secondary"></i>
                            <p class="mt-3">{{ $media->name }}</p>
                        </div>
                    @endif

                    <h5>{{ $media->name }}</h5>

                    <p class="text-muted">
                        {{ $media->file_type }}
                    </p>

                </div>
            </div>
        </div>

        <div class="col-md-7">

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Media Information</h5>
                </div>

                <div class="card-body">

                    <p><strong>File Name:</strong> {{ $media->file_name }}</p>

                    <p><strong>File Type:</strong> {{ $media->file_type }}</p>

                    <p><strong>MIME Type:</strong> {{ $media->mime_type }}</p>

                    <p><strong>File Size:</strong> {{ number_format($media->file_size / 1024, 2) }} KB</p>

                    <p><strong>Dimensions:</strong>
                        {{ $media->width ?? '-' }} × {{ $media->height ?? '-' }}
                    </p>

                    <p><strong>Uploaded By:</strong>
                        {{ $media->uploader->name ?? 'Unknown' }}
                    </p>

                    <p><strong>Uploaded At:</strong>
                        {{ $media->created_at?->format('d M Y, h:i A') }}
                    </p>

                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Edit Alt Text and Caption</h5>
                </div>

                <div class="card-body">

                    <form action="{{ route('admin.media.alt-text', $media->id) }}"
                          method="POST">

                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label">Alt Text</label>

                            <input type="text"
                                   name="alt_text"
                                   class="form-control"
                                   value="{{ old('alt_text', $media->alt_text) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Caption</label>

                            <textarea name="caption"
                                      class="form-control"
                                      rows="3">{{ old('caption', $media->caption) }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>

                    </form>

                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Replace Media File</h5>
                </div>

                <div class="card-body">

                    <form action="{{ route('admin.media.replace', $media->id) }}"
                          method="POST"
                          enctype="multipart/form-data">

                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <input type="file"
                                   name="file"
                                   class="form-control"
                                   required>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            Replace File
                        </button>

                    </form>

                </div>
            </div>

            <a href="{{ route('admin.media.where-used', $media->id) }}"
               class="btn btn-info">
                Where Used
            </a>

        </div>

    </div>

</div>

@endsection
