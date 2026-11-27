@extends('admin.layouts.master')

@section('title', 'Tour Management')

@push('css')
<style>
    .tour-page { --tour-ink:#202b36; --tour-muted:#718096; }
    .tour-page .tour-heading { color:var(--tour-ink); font-weight:750; letter-spacing:-.03em; }
    .tour-page .tour-stat { border:1px solid #e9edf2; border-radius:14px; background:#fff; padding:17px 19px; height:100%; }
    .tour-page .tour-stat-label { color:var(--tour-muted); font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.06em; }
    .tour-page .tour-stat-value { color:var(--tour-ink); font-size:1.65rem; line-height:1.2; font-weight:750; margin-top:7px; }
    .tour-page .tour-panel { border:1px solid #e9edf2; border-radius:14px; overflow:visible; background:#fff; }
    .tour-page .tour-panel .card-body { padding:18px; }
    .tour-page .tour-table { margin-bottom:0; }
    .tour-page .tour-table thead th { color:#718096; background:#f8fafc; border-bottom:1px solid #e9edf2; font-size:.72rem; font-weight:700; letter-spacing:.055em; text-transform:uppercase; white-space:nowrap; padding:13px 12px; }
    .tour-page .tour-table tbody td { padding:14px 12px; border-color:#eef1f4; vertical-align:middle; }
    .tour-page .tour-thumb { width:58px; height:48px; border-radius:9px; object-fit:cover; background:#edf1f5; display:block; }
    .tour-page .tour-name { color:#253449; font-weight:700; text-decoration:none; }
    .tour-page .tour-name:hover { color:#3867d6; }
    .tour-page .tour-meta { color:#8994a3; font-size:.78rem; margin-top:3px; }
    .tour-page .tour-chip { display:inline-flex; align-items:center; border-radius:999px; padding:4px 9px; background:#f1f4f8; color:#536174; font-size:.75rem; font-weight:600; }
    .tour-page .dropdown-menu { min-width:205px; border:1px solid #e8edf2; border-radius:11px; box-shadow:0 12px 30px rgba(31,45,61,.12); padding:7px; }
    .tour-page .dropdown-item { border-radius:7px; padding:8px 10px; font-size:.86rem; }
    .tour-page .dropdown-item i { width:19px; color:#7a8796; }
    .tour-page .tour-actions { display:flex; align-items:center; justify-content:flex-end; gap:6px; white-space:nowrap; }
    .tour-page .tour-empty { padding:52px 18px; text-align:center; color:#778395; }
    .tour-page .tour-empty i { font-size:2.2rem; color:#b6c0cc; }
    @media (max-width: 767.98px) {
        .tour-page .tour-heading { font-size:1.45rem; }
        .tour-page .tour-stat { padding:13px; }
        .tour-page .tour-stat-value { font-size:1.35rem; }
    }
</style>
@endpush

@section('content')
<div class="tour-page">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <div class="text-secondary small mb-1">CATALOGUE MANAGEMENT</div>
            <h1 class="tour-heading h3 mb-1">Tours</h1>
            <div class="text-secondary small">Create, organise and publish your travel experiences.</div>
        </div>
        <a href="{{ route('admin.tours.create') }}" class="btn btn-primary ms-auto">
            <i class="bi bi-plus-lg me-1"></i> Create tour
        </a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'All tours', 'value' => $stats['total'], 'icon' => 'bi-globe2'],
            ['label' => 'Published', 'value' => $stats['published'], 'icon' => 'bi-check-circle'],
            ['label' => 'Drafts', 'value' => $stats['draft'], 'icon' => 'bi-pencil-square'],
            ['label' => 'Archived', 'value' => $stats['archived'], 'icon' => 'bi-archive'],
        ] as $stat)
            <div class="col-6 col-xl-3">
                <div class="tour-stat">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="tour-stat-label">{{ $stat['label'] }}</div>
                        <i class="bi {{ $stat['icon'] }} text-secondary"></i>
                    </div>
                    <div class="tour-stat-value">{{ number_format($stat['value']) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="tour-panel mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.tours.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label small fw-semibold" for="q">Search tours</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Search by tour title">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small fw-semibold" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (\App\Models\Tour::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small fw-semibold" for="tour_category_id">Category</label>
                    <select id="tour_category_id" name="tour_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('tour_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small fw-semibold" for="objective_id">Objective</label>
                    <select id="objective_id" name="objective_id" class="form-select">
                        <option value="">All objectives</option>
                        @foreach ($objectives as $objective)
                            <option value="{{ $objective->id }}" @selected((string) request('objective_id') === (string) $objective->id)>{{ $objective->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label small fw-semibold" for="duration">Days</label>
                    <select id="duration" name="duration" class="form-select">
                        <option value="">Any</option>
                        @foreach ($durations as $duration)
                            <option value="{{ $duration }}" @selected((string) request('duration') === (string) $duration)>{{ $duration }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.tours.index') }}" title="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="tour-panel">
        <div class="card-body p-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 px-lg-4 py-3 border-bottom">
                <div>
                    <h2 class="h6 fw-bold mb-1">Tour catalogue</h2>
                    <div class="small text-secondary">
                        Showing {{ $tours->firstItem() ?? 0 }}–{{ $tours->lastItem() ?? 0 }} of {{ $tours->total() }} tours
                    </div>
                </div>
                <span class="tour-chip"><i class="bi bi-funnel me-1"></i> Manage each tour from its action menu</span>
            </div>

            <div class="table-responsive">
                <table class="table tour-table">
                    <thead>
                        <tr>
                            <th class="ps-3">Tour</th>
                            <th>Category / Objective</th>
                            <th>Duration</th>
                            <th>Starting price</th>
                            <th>Content</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($tours as $tour)
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($tour->hero_image)
                                        <img class="tour-thumb" src="{{ asset('storage/' . $tour->hero_image) }}" alt="{{ $tour->title }}">
                                    @else
                                        <div class="tour-thumb d-flex align-items-center justify-content-center"><i class="bi bi-image text-secondary"></i></div>
                                    @endif
                                    <div>
                                        <a class="tour-name" href="{{ route('admin.tours.edit', $tour) }}">{{ $tour->title }}</a>
                                        <div class="tour-meta">{{ $tour->destination ?: 'Destination not set' }} · Updated {{ $tour->updated_at?->diffForHumans() }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold small">{{ $tour->category?->name ?? 'Uncategorised' }}</div>
                                <div class="tour-meta">{{ $tour->objective?->name ?? 'No objective' }}</div>
                            </td>
                            <td><span class="tour-chip">{{ $tour->duration_days }} days</span></td>
                            <td>
                                <div class="fw-bold">{{ $tour->currency }} {{ number_format((float) $tour->base_price, 2) }}</div>
                                <div class="tour-meta">per traveller</div>
                            </td>
                            <td>
                                <div class="small"><i class="bi bi-calendar3 me-1 text-secondary"></i>{{ $tour->days_count }} itinerary days</div>
                                <div class="small text-secondary"><i class="bi bi-images me-1"></i>{{ $tour->images_count }} gallery images · {{ $tour->departures_count }} departures</div>
                            </td>
                            <td>
                                @include('admin.partials.status', ['value' => $tour->status])
                            </td>
                            <td class="text-end pe-3">
                                <div class="tour-actions">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-sliders me-1"></i> Manage
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('admin.tours.edit', $tour) }}#tab-basics">
                                                <i class="bi bi-card-text"></i> Basic details</a>
                                            </li>

                                            <!-- <li><a class="dropdown-item" href="{{ route('admin.tours.itinerary', $tour) }}">
                                                <i class="bi bi-calendar2-week"></i> Itinerary 
                                                <span class="text-secondary ms-1">({{ $tour->days_count }})</span></a>
                                            </li> -->
                                            <li><a class="dropdown-item" href="{{ route('admin.tours.edit', $tour) }}#tab-gallery">
                                                <i class="bi bi-images"></i> Gallery <span class="text-secondary ms-1">({{ $tour->images_count }})</span></a></li>
                                            <!-- <li><a class="dropdown-item" href="{{ route('admin.departures.index', ['tour_id' => $tour->id]) }}">
                                                <i class="bi bi-calendar-event"></i> Departures 
                                                <span class="text-secondary ms-1">({{ $tour->departures_count }})</span></a></li> -->
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="{{ route('admin.tours.edit', $tour) }}#tab-details">
                                                <i class="bi bi-info-circle"></i> Other details</a>
                                            </li>
                                            <li><a class="dropdown-item" href="{{ route('admin.tours.edit', $tour) }}#tab-seo">
                                                <i class="bi bi-search"></i> SEO settings</a>
                                            </li>
                                            <li><a class="dropdown-item" href="{{ route('admin.tours.preview', $tour) }}" target="_blank" 
                                            rel="noopener"><i class="bi bi-eye"></i> Preview tour</a>
                                        </li>
                                        </ul>
                                    </div>

                                    @if ($tour->status !== 'archived')
                                        <form method="POST" action="{{ route('admin.tours.publish', $tour) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="{{ $tour->status === 'published' ? 'Move to draft' : 'Publish tour' }}">
                                                <i class="bi {{ $tour->status === 'published' ? 'bi-eye-slash' : 'bi-send' }}"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <form method="POST" action="{{ route('admin.tours.duplicate', $tour) }}">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit"><i class="bi bi-copy"></i> Duplicate as draft</button>
                                                </form>
                                            </li>
                                            @if ($tour->status !== 'archived')
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <!-- <form method="POST" action="{{ route('admin.tours.destroy', $tour) }}" onsubmit="return confirm('Archive “{{ addslashes($tour->title) }}”? Existing bookings will be preserved.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-archive"></i> Archive tour</button>
                                                    </form> -->


                                                     {{-- NEW: Permanent Delete --}}
                                                    <form action="{{ route('admin.tours.permanent-delete', $tour) }}"
                                                        method="POST"
                                                        style="display:inline-block"
                                                        onsubmit="return confirm('Permanently delete this tour? This cannot be undone.')">
                                                        @csrf
                                                        @method('DELETE')

                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="material-icons"></i>
                                                            Delete Permanently
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="tour-empty">
                                    <i class="bi bi-map d-block mb-3"></i>
                                    <h3 class="h6 fw-bold text-dark">No tours found</h3>
                                    <p class="small mb-3">Create your first tour or adjust the filters.</p>
                                    <a href="{{ route('admin.tours.create') }}" class="btn btn-primary btn-sm"> + Create tour</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tours->hasPages())
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 px-lg-4 py-3 border-top">
                    <div class="small text-secondary">Page {{ $tours->currentPage() }} of {{ $tours->lastPage() }}</div>
                    {{ $tours->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
