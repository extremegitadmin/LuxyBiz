
@extends('admin.layouts.master')

@section('title', 'Add Testimonial')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Add Testimonial</h4>
            <p class="text-muted mb-0">Add a customer review to your website.</p>
        </div>
        <a href="{{ route('admin.cms.testimonials.index') }}" class="btn btn-outline-secondary">
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

    <form method="POST" action="{{ route('admin.cms.testimonials.store') }}">
        @csrf

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name"
                               class="form-control" required
                               value="{{ old('customer_name') }}">
                        @error('customer_name') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" class="form-control"
                               value="{{ old('designation') }}"
                               placeholder="Example: Traveller">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control"
                               value="{{ old('location') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="">No rating</option>
                            @for($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected(old('rating') == $i)>
                                    {{ $i }} / 5
                                </option>
                            @endfor
                        </select>
                        @error('rating') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Testimonial <span class="text-danger">*</span></label>
                        <textarea name="testimonial" rows="5" class="form-control"
                                  required>{{ old('testimonial') }}</textarea>
                        @error('testimonial') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Media</label>
                        <select name="media_id" class="form-select">
                            <option value="">No media selected</option>
                            @foreach($media as $item)
                                <option value="{{ $item->id }}" @selected(old('media_id') == $item->id)>
                                    {{ $item->original_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('media_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" class="form-control"
                               value="{{ old('source') }}"
                               placeholder="Example: Google, Facebook">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Source URL</label>
                        <input type="url" name="source_url" class="form-control"
                               value="{{ old('source_url') }}"
                               placeholder="https://example.com">
                        @error('source_url') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" min="0"
                               class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status') === 'published')>Published</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="published_at" class="form-control"
                               value="{{ old('published_at') }}">
                        @error('published_at') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                </div>
            </div>
            <div class="card-footer bg-white text-end">
                <a href="{{ route('admin.cms.testimonials.index') }}" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Testimonial
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
