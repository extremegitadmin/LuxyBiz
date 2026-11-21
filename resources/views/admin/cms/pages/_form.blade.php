
@php
    $editing = isset($page);
@endphp

<div class="row g-4">

    {{-- Basic page information --}}
    <div class="col-12">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Page Information</h6>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Page Title <span class="text-danger">*</span></label>
                        <input type="text"
                               name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $page->title ?? '') }}"
                               required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Slug</label>
                        <input type="text"
                               name="slug"
                               class="form-control @error('slug') is-invalid @enderror"
                               value="{{ old('slug', $page->slug ?? '') }}"
                               placeholder="Leave blank to generate from title">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Example: about-us</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Page Type <span class="text-danger">*</span></label>
                        <select name="page_type" class="form-select" required>
                            <option value="standard" @selected(old('page_type', $page->page_type ?? 'standard') === 'standard')>Standard Page</option>
                            <option value="homepage" @selected(old('page_type', $page->page_type ?? '') === 'homepage')>Homepage</option>
                            <option value="legal" @selected(old('page_type', $page->page_type ?? '') === 'legal')>Legal Page</option>
                        </select>
                        @error('page_type')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Template</label>
                        <input type="text"
                               name="template"
                               class="form-control"
                               value="{{ old('template', $page->template ?? 'default') }}"
                               placeholder="default">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Short Description</label>
                        <textarea name="excerpt"
                                  rows="2"
                                  class="form-control"
                                  maxlength="1000">{{ old('excerpt', $page->excerpt ?? '') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Page Content</label>
                        <textarea name="body"
                                  rows="12"
                                  class="form-control @error('body') is-invalid @enderror"
                                  placeholder="Enter page content...">{{ old('body', $page->body ?? '') }}</textarea>
                        @error('body')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            This field currently stores plain text or HTML content. We'll connect a rich text editor later if needed.
                        </small>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Publishing --}}
    <div class="col-12">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Publishing Settings</h6>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="draft" @selected(old('status', $page->status ?? 'draft') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status', $page->status ?? '') === 'published')>Published</option>
                        </select>
                        @error('status')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local"
                               name="published_at"
                               class="form-control"
                               value="{{ old('published_at', isset($page) && $page->published_at ? $page->published_at->format('Y-m-d\TH:i') : '') }}">
                        @error('published_at')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- SEO --}}
    <div class="col-12">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-google me-1"></i> SEO Settings
                </h6>

                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label">Meta Title</label>
                        <input type="text"
                               name="meta_title"
                               class="form-control"
                               maxlength="255"
                               value="{{ old('meta_title', $page->meta_title ?? '') }}"
                               placeholder="SEO title">
                        @error('meta_title')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description"
                                  rows="3"
                                  maxlength="500"
                                  class="form-control"
                                  placeholder="Short description for search engines">{{ old('meta_description', $page->meta_description ?? '') }}</textarea>
                        @error('meta_description')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text"
                               name="meta_keywords"
                               class="form-control"
                               value="{{ old('meta_keywords', $page->meta_keywords ?? '') }}"
                               placeholder="travel, holiday, tours">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Canonical URL</label>
                        <input type="url"
                               name="canonical_url"
                               class="form-control"
                               value="{{ old('canonical_url', $page->canonical_url ?? '') }}"
                               placeholder="https://example.com/about">
                        @error('canonical_url')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Open Graph --}}
    <div class="col-12">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Social Sharing / Open Graph</h6>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Open Graph Title</label>
                        <input type="text"
                               name="og_title"
                               class="form-control"
                               value="{{ old('og_title', $page->og_title ?? '') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Open Graph Media ID</label>
                        <input type="number"
                               name="og_media_id"
                               class="form-control"
                               min="1"
                               value="{{ old('og_media_id', $page->og_media_id ?? '') }}"
                               placeholder="Select from Media Library later">
                        @error('og_media_id')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Enter an existing CMS Media ID for now.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Open Graph Description</label>
                        <textarea name="og_description"
                                  rows="2"
                                  class="form-control">{{ old('og_description', $page->og_description ?? '') }}</textarea>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.cms.pages.index') }}" class="btn btn-light border">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ $editing ? 'Update Page' : 'Save Page' }}
        </button>
    </div>

</div>
