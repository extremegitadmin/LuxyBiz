
@extends('admin.layouts.master')

@section('title', 'Add Trust Item')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Add Trust Item</h4>
            <p class="text-muted mb-0">Add a benefit or trust highlight to your homepage.</p>
        </div>
        <a href="{{ route('admin.cms.trust-items.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            Please correct the errors below.
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cms.trust-items.store') }}">
        @csrf

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required
                               value="{{ old('title') }}">
                        @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Icon</label>
                        <input type="text" name="icon" class="form-control"
                               value="{{ old('icon') }}"
                               placeholder="Example: bi bi-shield-check">
                        <small class="text-muted">Enter a Bootstrap icon class or icon name supported by your website.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control">{{ old('description') }}</textarea>
                        @error('description') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Media</label>
                        <select name="media_id" class="form-select">
                            <option value="">No media selected</option>
                            @foreach($media as $mediaItem)
                                <option value="{{ $mediaItem->id }}"
                                    @selected(old('media_id') == $mediaItem->id)>
                                    {{ $mediaItem->original_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('media_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" min="0"
                               class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Link Text</label>
                        <input type="text" name="link_text" class="form-control"
                               value="{{ old('link_text') }}"
                               placeholder="Example: Learn More">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Link URL</label>
                        <input type="text" name="link_url" class="form-control"
                               value="{{ old('link_url') }}"
                               placeholder="/about-us">
                        @error('link_url') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status') === 'published')>Published</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="published_at" class="form-control"
                               value="{{ old('published_at') }}">
                        @error('published_at') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                </div>
            </div>

            <div class="card-footer bg-white text-end">
                <a href="{{ route('admin.cms.trust-items.index') }}" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Trust Item
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
