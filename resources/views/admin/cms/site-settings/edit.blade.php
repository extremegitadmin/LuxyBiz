
@extends('admin.layouts.master')

@section('content')
<div class="container-fluid">

    <h4 class="mb-4">Website & Footer Settings</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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

    <form action="{{ route('admin.cms.site-settings.update') }}"
          method="POST">

        @csrf
        @method('PUT')

        {{-- Website Information --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Website Information</strong>
            </div>

            <div class="card-body">
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Website Name</label>

                        <input type="text"
                               name="site_name"
                               class="form-control"
                               value="{{ old('site_name', $settings->site_name) }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tagline</label>

                        <input type="text"
                               name="site_tagline"
                               class="form-control"
                               value="{{ old('site_tagline', $settings->site_tagline) }}">
                    </div>

                </div>
            </div>
        </div>

        {{-- Website Branding --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Website Branding</strong>
            </div>

            <div class="card-body">

                @php
                    $logoFields = [
                        'logo_media_id' => 'Website Logo',
                        'footer_logo_media_id' => 'Footer Logo',
                        'favicon_media_id' => 'Favicon',
                    ];
                @endphp

                <div class="row">

                    @foreach($logoFields as $field => $label)

                        @php
                            $selectedId = old($field, $settings->$field);
                            $selectedMedia = $media->firstWhere('id', $selectedId);
                        @endphp

                        <div class="col-md-4 mb-4">

                            <label class="form-label">
                                {{ $label }}
                            </label>

                            <select name="{{ $field }}"
                                    class="form-select">

                                <option value="">-- No Image Selected --</option>

                                @foreach($media as $item)
                                    <option value="{{ $item->id }}"
                                        @selected($selectedId == $item->id)>
                                        {{ $item->name }} ({{ $item->file_type }})
                                    </option>
                                @endforeach

                            </select>

                            @if($selectedMedia)
                                <div class="mt-3">
                                    <p class="small text-muted mb-2">
                                        Current {{ $label }}
                                    </p>
                                    @if($selectedMedia->file_type === 'image')
                                        <img
                                            src="{{ asset('storage/' . $selectedMedia->file_path) }}"
                                            alt="{{ $selectedMedia->alt_text ?? $label }}"
                                            style="max-width: 180px; max-height: 100px; object-fit: contain;"
                                            class="border rounded p-2">
                                    @else
                                        <span class="text-muted">
                                            Selected file is not an image.
                                        </span>
                                    @endif
                                </div>
                            @endif

                        </div>

                    @endforeach

                </div>

                <div class="alert alert-info mb-0">
                    Upload your logo and favicon first through the Media Library,
                    then select them here.
                    Website Logo is the main logo. Footer Logo is optional.
                    Favicon is the small browser tab icon.
                </div>

            </div>
        </div>

        {{-- Contact Information --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Contact Information</strong>
            </div>

            <div class="card-body">
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Email</label>

                        <input type="email"
                               name="contact_email"
                               class="form-control"
                               value="{{ old('contact_email', $settings->contact_email) }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Phone</label>

                        <input type="text"
                               name="contact_phone"
                               class="form-control"
                               value="{{ old('contact_phone', $settings->contact_phone) }}">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Contact Address</label>

                        <textarea name="contact_address"
                                  class="form-control"
                                  rows="3">{{ old('contact_address', $settings->contact_address) }}</textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- Footer Content --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Footer Content</strong>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label">Footer Description</label>

                    <textarea name="footer_description"
                              class="form-control"
                              rows="4">{{ old('footer_description', $settings->footer_description) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Copyright Text</label>

                    <input type="text"
                           name="copyright_text"
                           class="form-control"
                           value="{{ old('copyright_text', $settings->copyright_text) }}">
                </div>

            </div>
        </div>

        {{-- Social Media Links --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Social Media Links</strong>
            </div>

            <div class="card-body">

                @php
                    $socialLinks = old('social_links', $settings->social_links ?? []);
                @endphp

                <div class="row">

                    @foreach(['facebook', 'instagram', 'youtube', 'linkedin', 'twitter'] as $social)

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                {{ ucfirst($social) }} URL
                            </label>

                            <input type="url"
                                   name="social_links[{{ $social }}]"
                                   class="form-control"
                                   value="{{ $socialLinks[$social] ?? '' }}"
                                   placeholder="https://">

                        </div>

                    @endforeach

                </div>

            </div>
        </div>

        {{-- Additional Footer Settings --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Additional Footer Settings</strong>
            </div>

            <div class="card-body">

                @php
                    $footerSettings = old('footer_settings', $settings->footer_settings ?? []);
                @endphp

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">About Section Heading</label>

                        <input type="text"
                               name="footer_settings[about_title]"
                               class="form-control"
                               value="{{ $footerSettings['about_title'] ?? '' }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quick Links Heading</label>

                        <input type="text"
                               name="footer_settings[quick_links_title]"
                               class="form-control"
                               value="{{ $footerSettings['quick_links_title'] ?? '' }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Section Heading</label>

                        <input type="text"
                               name="footer_settings[contact_title]"
                               class="form-control"
                               value="{{ $footerSettings['contact_title'] ?? '' }}">
                    </div>

                </div>

            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Settings
        </button>

    </form>
</div>
@endsection
