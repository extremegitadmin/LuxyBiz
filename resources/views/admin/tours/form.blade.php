@extends('admin.layouts.master')

@php
    $isEdit = $tour->exists;
    $field = fn (string $name, $default = null) => old($name, $tour->{$name} ?? $default);
    $activeTab = request('tab');
@endphp

@section('title', $isEdit ? 'Edit Tour' : 'Create Tour')

@push('css')
<style>
    .tour-editor { --editor-border:#e8edf2; --editor-muted:#778496; }
    .tour-editor .editor-title { color:#243246; font-weight:750; letter-spacing:-.025em; }
    .tour-editor .editor-card { background:#fff; border:1px solid var(--editor-border); border-radius:14px; overflow:hidden; }
    .tour-editor .editor-card-body { padding:clamp(16px,2.5vw,28px); }
    .tour-editor .nav-tabs { border-bottom:1px solid var(--editor-border); gap:5px; padding:12px 16px 0; flex-wrap:nowrap; overflow-x:auto; }
    .tour-editor .nav-tabs .nav-link { border:0; border-radius:9px 9px 0 0; color:#687789; font-size:.88rem; font-weight:650; white-space:nowrap; padding:12px 15px; }
    .tour-editor .nav-tabs .nav-link.active { color:#315fc4; background:#f2f6ff; box-shadow:inset 0 -2px #4778df; }
    .tour-editor .form-label { color:#35445a; font-size:.84rem; font-weight:650; }
    .tour-editor .form-control, .tour-editor .form-select { border-color:#dfe5ec; border-radius:8px; min-height:42px; }
    .tour-editor textarea.form-control { min-height:unset; }
    .tour-editor .form-control:focus, .tour-editor .form-select:focus { border-color:#88a9ed; box-shadow:0 0 0 .2rem rgba(65,112,210,.12); }
    .tour-editor .form-text { color:#8792a1; font-size:.77rem; }
    .tour-editor .section-heading { color:#2b3b50; font-weight:750; }
    .tour-editor .section-note { color:var(--editor-muted); font-size:.86rem; }
    .tour-editor .image-tile { border:1px solid var(--editor-border); border-radius:11px; overflow:hidden; background:#fff; }
    .tour-editor .image-tile img { width:100%; height:145px; object-fit:cover; display:block; }
    .tour-editor .hero-preview { width:100%; max-height:210px; object-fit:cover; border-radius:11px; border:1px solid var(--editor-border); }
    .tour-editor .editor-footer { background:#fbfcfe; border-top:1px solid var(--editor-border); padding:15px 22px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
    .tour-editor .editor-footer-actions { display:flex; gap:8px; }
    .tour-editor .hint-box { border:1px solid #e3ebfa; background:#f6f9ff; border-radius:10px; padding:13px 15px; color:#526681; font-size:.84rem; }
    @media(max-width:575px) { .tour-editor .nav-tabs { margin-right:-16px; margin-left:-16px; } .tour-editor .editor-footer-actions { width:100%; } .tour-editor .editor-footer-actions .btn { flex:1; } }
</style>
@endpush

@section('content')
<div class="tour-editor">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <div class="text-secondary small mb-1">TOUR CATALOGUE / {{ $isEdit ? 'EDIT' : 'NEW' }}</div>
            <h1 class="editor-title h3 mb-1">{{ $isEdit ? $tour->title : 'Create a tour' }}</h1>
            <div class="small text-secondary">{{ $isEdit ? 'Manage the content and presentation of this tour.' : 'Start with the essential information. You can add the itinerary and departures after saving.' }}</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @if ($isEdit)
                <a href="{{ route('admin.tours.itinerary', $tour) }}" class="btn btn-outline-primary"><i class="bi bi-calendar2-week me-1"></i> Itinerary</a>
                <a href="{{ route('admin.tours.preview', $tour) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i> Preview</a>
            @endif
            <a href="{{ route('admin.tours.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i> All tours</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">Please check the highlighted fields.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isEdit)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge rounded-pill {{ $tour->status === 'published' ? 'text-bg-success' : ($tour->status === 'archived' ? 'text-bg-secondary' : 'text-bg-warning') }}">
                {{ ucfirst($tour->status) }}
            </span>
            <span class="small text-secondary">Tour ID: {{ $tour->id }}</span>
            <span class="small text-secondary">·</span>
            <span class="small text-secondary">Last updated {{ $tour->updated_at?->diffForHumans() }}</span>
        </div>
    @endif

    <form method="POST"
          action="{{ $isEdit ? route('admin.tours.update', $tour) : route('admin.tours.store') }}"
          enctype="multipart/form-data"
          id="tour-editor-form">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="editor-card">
            <ul class="nav nav-tabs" id="tourEditorTabs" role="tablist">
                @foreach ([
                    'basics' => ['label' => 'Basic details', 'icon' => 'bi-card-text'],
                    'details' => ['label' => 'Other details', 'icon' => 'bi-info-circle'],
                    'pricing' => ['label' => 'Pricing & cover', 'icon' => 'bi-currency-exchange'],
                    'gallery' => ['label' => 'Gallery', 'icon' => 'bi-images'],
                    'seo' => ['label' => 'SEO', 'icon' => 'bi-search'],
                ] as $tab => $meta)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                                id="tab-button-{{ $tab }}"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-{{ $tab }}"
                                type="button"
                                role="tab"
                                aria-controls="tab-{{ $tab }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="editor-card-body tab-content">
                {{-- BASIC DETAILS --}}
                <div class="tab-pane fade show active" id="tab-basics" role="tabpanel" aria-labelledby="tab-button-basics">
                    <div class="mb-4">
                        <h2 class="section-heading h5 mb-1">Basic tour information</h2>
                        <p class="section-note mb-0">The main information visitors use to understand and find this tour.</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="title">Tour title <span class="text-danger">*</span></label>
                            <input id="title" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ $field('title') }}" maxlength="191" required placeholder="Example: Kerala Backwaters Escape">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach (\App\Models\Tour::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($field('status', 'draft') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Only published tours should be visible on the public website.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tour_category_id">Tour category</label>
                            <select id="tour_category_id" name="tour_category_id" class="form-select @error('tour_category_id') is-invalid @enderror">
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) $field('tour_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('tour_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="objective_id">Objective</label>
                            <select id="objective_id" name="objective_id" class="form-select @error('objective_id') is-invalid @enderror">
                                <option value="">Select objective</option>
                                @foreach ($objectives as $objective)
                                    <option value="{{ $objective->id }}" @selected((string) $field('objective_id') === (string) $objective->id)>{{ $objective->name }}</option>
                                @endforeach
                            </select>
                            @error('objective_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="destination">Destination</label>
                            <input id="destination" name="destination" class="form-control @error('destination') is-invalid @enderror"
                                   value="{{ $field('destination') }}" maxlength="191" placeholder="Example: Kerala, India">
                            @error('destination')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="duration_days">Duration in days <span class="text-danger">*</span></label>
                            <input id="duration_days" type="number" min="1" max="365" name="duration_days"
                                   class="form-control @error('duration_days') is-invalid @enderror"
                                   value="{{ $field('duration_days', 7) }}" required>
                            @error('duration_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="short_description">Short description</label>
                            <textarea id="short_description" name="short_description" rows="3" maxlength="1000"
                                      class="form-control @error('short_description') is-invalid @enderror"
                                      placeholder="A concise introduction shown on tour cards.">{{ $field('short_description') }}</textarea>
                            @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Full description</label>
                            <textarea id="description" name="description" rows="7"
                                      class="form-control @error('description') is-invalid @enderror"
                                      placeholder="Explain the experience, route and what makes this tour special.">{{ $field('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="highlights">Tour highlights</label>
                            <textarea id="highlights" name="highlights" rows="4"
                                      class="form-control @error('highlights') is-invalid @enderror"
                                      placeholder="One highlight per line">{{ $field('highlights') }}</textarea>
                            @error('highlights')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Enter one highlight per line. These are displayed as a list in the preview.</div>
                        </div>
                    </div>
                </div>

                {{-- OTHER DETAILS --}}
                <div class="tab-pane fade" id="tab-details" role="tabpanel" aria-labelledby="tab-button-details">
                    <div class="mb-4">
                        <h2 class="section-heading h5 mb-1">What's included in the experience?</h2>
                        <p class="section-note mb-0">Add clear inclusions, exclusions and the tour expert's profile.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="inclusions">Inclusions</label>
                            <textarea id="inclusions" name="inclusions" rows="7" class="form-control"
                                      placeholder="One included item per line">{{ $field('inclusions') }}</textarea>
                            <div class="form-text">For example: accommodation, breakfast, transfers.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="exclusions">Exclusions</label>
                            <textarea id="exclusions" name="exclusions" rows="7" class="form-control"
                                      placeholder="One excluded item per line">{{ $field('exclusions') }}</textarea>
                            <div class="form-text">For example: flights, personal expenses, travel insurance.</div>
                        </div>
                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-md-6">
                            <label class="form-label" for="expert_name">Expert / guide name</label>
                            <input id="expert_name" name="expert_name" class="form-control" value="{{ $field('expert_name') }}" maxlength="191">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="expert_role">Expert role</label>
                            <input id="expert_role" name="expert_role" class="form-control" value="{{ $field('expert_role') }}" maxlength="191" placeholder="Example: Local travel specialist">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="expert_bio">Expert biography</label>
                            <textarea id="expert_bio" name="expert_bio" rows="4" class="form-control">{{ $field('expert_bio') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- PRICING AND COVER --}}
                <div class="tab-pane fade" id="tab-pricing" role="tabpanel" aria-labelledby="tab-button-pricing">
                    <div class="mb-4">
                        <h2 class="section-heading h5 mb-1">Pricing and cover image</h2>
                        <p class="section-note mb-0">Set the base price. Specific departure prices are managed in Departures.</p>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label" for="base_price">Base price per traveller <span class="text-danger">*</span></label>
                            <input id="base_price" type="number" name="base_price" min="0" step="0.01" required
                                   class="form-control @error('base_price') is-invalid @enderror"
                                   value="{{ $field('base_price', 0) }}">
                            @error('base_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Use 0 only if this is intentionally a free tour.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="currency">Currency <span class="text-danger">*</span></label>
                            <select id="currency" name="currency" class="form-select" required>
                                @foreach (\App\Models\Tour::CURRENCIES as $currency)
                                    <option value="{{ $currency }}" @selected($field('currency', 'USD') === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            @if ($isEdit)
                                <a href="{{ route('admin.departures.index', ['tour_id' => $tour->id]) }}" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-calendar-event me-1"></i> Manage departure dates and prices
                                </a>
                            @else
                                <div class="hint-box w-100">Save this tour first. You can then add departure dates, capacity and departure-specific pricing.</div>
                            @endif
                        </div>

                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-lg-7">
                            <label class="form-label" for="hero_image">Main / hero image</label>
                            <input id="hero_image" type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                   class="form-control @error('hero_image') is-invalid @enderror">
                            @error('hero_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">JPG, PNG or WebP. Maximum 5 MB. A landscape image works best.</div>
                        </div>
                        <div class="col-lg-5">
                            @if ($isEdit && $tour->hero_image)
                                <div class="small text-secondary fw-semibold mb-2">Current cover image</div>
                                <img class="hero-preview" src="{{ asset('storage/' . $tour->hero_image) }}" alt="{{ $tour->title }}">
                            @else
                                <div class="hint-box h-100 d-flex align-items-center">The cover image will appear on the tour listing and preview.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- GALLERY --}}
                <div class="tab-pane fade" id="tab-gallery" role="tabpanel" aria-labelledby="tab-button-gallery">
                    <div class="mb-4">
                        <h2 class="section-heading h5 mb-1">Tour photo gallery</h2>
                        <p class="section-note mb-0">Upload supporting photos that help visitors understand the places and experiences.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="gallery_images">Add gallery images</label>
                            <input id="gallery_images" type="file" name="gallery_images[]" multiple
                                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                   class="form-control @error('gallery_images') is-invalid @enderror">
                            @error('gallery_images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('gallery_images.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            <div class="form-text">Select up to 12 images at once. Each image can be up to 5 MB.</div>
                            <div id="gallery-file-preview" class="row g-3 mt-1"></div>
                        </div>
                        @if ($isEdit)
                            <div class="col-12"><hr class="my-2"></div>
                            <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <h3 class="h6 fw-bold mb-1">Uploaded images</h3>
                                    <div class="small text-secondary">{{ $tour->images->count() }} image(s) in this tour gallery</div>
                                </div>
                            </div>
                            @forelse ($tour->images as $image)
                                <div class="col-6 col-md-4 col-xl-3">
                                    <div class="image-tile h-100">
                                        <img src="{{ asset('storage/' . $image->image_path) }}" alt="{{ $image->alt_text ?: $tour->title }}">
                                        <div class="p-2 d-flex justify-content-between align-items-center gap-2">
                                            <span class="small text-secondary text-truncate">Image {{ $loop->iteration }}</span>
                                            <button type="submit" form="delete-gallery-{{ $image->id }}" class="btn btn-sm btn-outline-danger" title="Delete image" onclick="return confirm('Delete this gallery image?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12"><div class="hint-box">No gallery images have been uploaded yet.</div></div>
                            @endforelse
                        @else
                            <div class="col-12"><div class="hint-box">Save the tour first. You can then return here to manage its uploaded gallery.</div></div>
                        @endif
                    </div>
                </div>

                {{-- SEO --}}
                <div class="tab-pane fade" id="tab-seo" role="tabpanel" aria-labelledby="tab-button-seo">
                    <div class="mb-4">
                        <h2 class="section-heading h5 mb-1">Search engine settings</h2>
                        <p class="section-note mb-0">Control the URL and search-result text for this tour.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="slug">URL slug</label>
                            <div class="input-group">
                                <span class="input-group-text">/tours/</span>
                                <input id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                       value="{{ $field('slug') }}" maxlength="191" placeholder="Generated from title if blank">
                            </div>
                            @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            <div class="form-text">Use lowercase letters, numbers and hyphens. Leave blank to generate automatically.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="meta_title">Meta title</label>
                            <input id="meta_title" name="meta_title" maxlength="191" class="form-control"
                                   value="{{ $field('meta_title') }}" placeholder="Search result title">
                            <div class="form-text"><span id="meta-title-count">{{ mb_strlen((string) $field('meta_title')) }}</span>/191 characters</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="meta_description">Meta description</label>
                            <textarea id="meta_description" name="meta_description" rows="4" maxlength="255" class="form-control"
                                      placeholder="A clear summary of this tour for search engines.">{{ $field('meta_description') }}</textarea>
                            <div class="form-text"><span id="meta-description-count">{{ mb_strlen((string) $field('meta_description')) }}</span>/255 characters</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="editor-footer">
                <div class="small text-secondary">
                    <i class="bi bi-shield-check me-1"></i>
                    {{ $isEdit ? 'Your changes are saved to this tour.' : 'A new tour is created as a draft by default.' }}
                </div>
                <div class="editor-footer-actions">
                    <a href="{{ route('admin.tours.index') }}" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2 me-1"></i>{{ $isEdit ? 'Save tour changes' : 'Create tour' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Gallery deletion forms are outside the main form to avoid nested forms. --}}
    @if ($isEdit)
        @foreach ($tour->images as $image)
            <form id="delete-gallery-{{ $image->id }}" method="POST"
                  action="{{ route('admin.tours.gallery.destroy', [$tour, $image]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    const requestedTab = @json($activeTab);
    const tabId = requestedTab ? '#tab-' + requestedTab : hash;

    if (tabId) {
        const trigger = document.querySelector('[data-bs-target="' + tabId + '"]');
        if (trigger && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }

    const invalidField = document.querySelector('#tour-editor-form .is-invalid');
    if (invalidField) {
        const pane = invalidField.closest('.tab-pane');
        const trigger = pane ? document.querySelector('[data-bs-target="#' + pane.id + '"]') : null;
        if (trigger && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }

    const title = document.getElementById('meta_title');
    const description = document.getElementById('meta_description');
    const titleCount = document.getElementById('meta-title-count');
    const descriptionCount = document.getElementById('meta-description-count');

    if (title && titleCount) title.addEventListener('input', () => titleCount.textContent = title.value.length);
    if (description && descriptionCount) description.addEventListener('input', () => descriptionCount.textContent = description.value.length);

    const galleryInput = document.getElementById('gallery_images');
    const preview = document.getElementById('gallery-file-preview');

    if (galleryInput && preview) {
        galleryInput.addEventListener('change', function () {
            preview.innerHTML = '';
            Array.from(this.files).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                const col = document.createElement('div');
                col.className = 'col-6 col-md-3';
                const image = document.createElement('img');
                image.className = 'w-100 rounded border';
                image.style.height = '115px';
                image.style.objectFit = 'cover';
                image.alt = file.name;
                image.src = URL.createObjectURL(file);
                const caption = document.createElement('div');
                caption.className = 'small text-secondary text-truncate mt-1';
                caption.textContent = file.name;
                col.append(image, caption);
                preview.appendChild(col);
            });
        });
    }
});
</script>
@endpush
