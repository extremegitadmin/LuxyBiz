
@extends('admin.layouts.master')

@section('title', 'Homepage Editor')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Homepage Editor</h4>
            <p class="text-muted mb-0">Manage your website homepage content.</p>
        </div>

        <a href="{{ url('/') }}" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right me-1"></i> View Website
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.cms.homepage.update') }}" method="POST">
        @csrf
        @method('PUT')

        {{-- HERO SECTION --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-image me-2"></i>Hero Section
                </h5>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label">Eyebrow Text</label>
                        <input type="text"
                               name="hero_eyebrow"
                               class="form-control"
                               value="{{ old('hero_eyebrow', $settings->hero_eyebrow) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Hero Title</label>
                        <input type="text"
                               name="hero_title"
                               class="form-control"
                               value="{{ old('hero_title', $settings->hero_title) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Hero Subtitle</label>
                        <textarea name="hero_subtitle"
                                  rows="3"
                                  class="form-control">{{ old('hero_subtitle', $settings->hero_subtitle) }}</textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Hero Media ID</label>
                        <select name="hero_media_id" class="form-select">
                            <option value="">Select hero media</option>

                            @foreach($media as $item)
                                <option value="{{ $item->id }}"
                                    @selected(old('hero_media_id', $settings->hero_media_id) == $item->id)>
                                    {{ $item->original_name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="text-muted">
                            Select an image or video from the CMS Media Library.
                        </small>
                    </div>

                </div>
            </div>
        </div>

        {{-- HERO BUTTONS --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-mouse me-2"></i>Hero Call-to-Action Buttons
                </h5>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Primary Button Text</label>
                        <input type="text"
                               name="hero_cta_text"
                               class="form-control"
                               value="{{ old('hero_cta_text', $settings->hero_cta_text) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Primary Button URL</label>
                        <input type="text"
                               name="hero_cta_url"
                               class="form-control"
                               value="{{ old('hero_cta_url', $settings->hero_cta_url) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Secondary Button Text</label>
                        <input type="text"
                               name="hero_secondary_cta_text"
                               class="form-control"
                               value="{{ old('hero_secondary_cta_text', $settings->hero_secondary_cta_text) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Secondary Button URL</label>
                        <input type="text"
                               name="hero_secondary_cta_url"
                               class="form-control"
                               value="{{ old('hero_secondary_cta_url', $settings->hero_secondary_cta_url) }}">
                    </div>

                </div>
            </div>
        </div>

        {{-- FEATURED TOURS --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-map me-2"></i>Featured Tours
                </h5>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Section Title</label>
                    <input type="text"
                           name="featured_section_title"
                           class="form-control"
                           value="{{ old('featured_section_title', $settings->featured_section_title) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Section Description</label>
                    <textarea name="featured_section_description"
                              rows="3"
                              class="form-control">{{ old('featured_section_description', $settings->featured_section_description) }}</textarea>
                </div>

                <label class="form-label">Select Featured Tours</label>

                @php
                    $selectedTours = old('featured_tour_ids', $settings->featured_tour_ids ?? []);
                    $selectedTours = array_map('strval', $selectedTours);
                @endphp

                <div class="row g-3">
                    @forelse($tours as $tour)
                        <div class="col-md-4 col-lg-3">
                            <label class="border rounded p-3 d-flex align-items-center gap-2 h-100">
                                <input type="checkbox"
                                       name="featured_tour_ids[]"
                                       value="{{ $tour->id }}"
                                       @checked(in_array((string) $tour->id, $selectedTours, true))>

                                <span>{{ $tour->title }}</span>
                            </label>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                No tours are available yet.
                            </div>
                        </div>
                    @endforelse
                </div>

            </div>
        </div>

        {{-- TRUST SECTION --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-shield-check me-2"></i>Trust Section
                </h5>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label">Trust Section Title</label>
                        <input type="text"
                               name="trust_section_title"
                               class="form-control"
                               value="{{ old('trust_section_title', $settings->trust_section_title) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Trust Section Description</label>
                        <textarea name="trust_section_description"
                                  rows="3"
                                  class="form-control">{{ old('trust_section_description', $settings->trust_section_description) }}</textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- BOTTOM CTA --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="bi bi-megaphone me-2"></i>Bottom Call-to-Action Section
                </h5>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label">CTA Title</label>
                        <input type="text"
                               name="cta_title"
                               class="form-control"
                               value="{{ old('cta_title', $settings->cta_title) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">CTA Description</label>
                        <textarea name="cta_description"
                                  rows="3"
                                  class="form-control">{{ old('cta_description', $settings->cta_description) }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Button Text</label>
                        <input type="text"
                               name="cta_button_text"
                               class="form-control"
                               value="{{ old('cta_button_text', $settings->cta_button_text) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Button URL</label>
                        <input type="text"
                               name="cta_button_url"
                               class="form-control"
                               value="{{ old('cta_button_url', $settings->cta_button_url) }}">
                    </div>

                </div>
            </div>
        </div>

        {{-- SAVE --}}
        <div class="d-flex justify-content-end gap-2 mb-4">
            <button type="reset" class="btn btn-light border">
                Reset Changes
            </button>

            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i> Save Homepage
            </button>
        </div>

    </form>

</div>
@endsection
