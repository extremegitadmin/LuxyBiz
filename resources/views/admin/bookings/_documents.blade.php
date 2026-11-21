{{-- Customer documents (G3, Day 22). Shown only with admin.documents.view. Day 23 adds View / Download. --}}
@php
    $documents = \App\Models\CustomerDocument::query()
        ->where('booking_id', $booking->id)
        ->with(['traveler:id,full_name', 'uploader:id,name'])
        ->orderByDesc('id')
        ->get();
    $types = \App\Models\CustomerDocument::TYPES;
    $limitKb = \App\Services\Documents\DocumentStorage::MAX_KB;
@endphp
<div class="card rounded-4 mb-4" id="documents">
    <div class="card-body p-4">
        <h5 class="mb-1 fw-semibold">Customer documents</h5>
        <div class="text-secondary small mb-3">Private files (passport, visa, tickets, insurance). Only people with permission can see this list.</div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Type</th><th>Traveler</th><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($documents as $d)
                        <tr>
                            <td class="fw-semibold">{{ $d->typeLabel() }}@if ($d->label)<div class="small text-secondary fw-normal">{{ $d->label }}</div>@endif</td>
                            <td>{{ $d->traveler?->full_name ?? 'Whole booking' }}</td>
                            <td class="text-break">{{ $d->original_name }}</td>
                            <td class="text-nowrap">{{ $d->humanSize() }}</td>
                            <td class="text-nowrap small">{{ $d->created_at?->format('d M Y H:i') }}<div class="text-secondary">{{ $d->uploader?->name ?? '-' }}</div></td>
                            <td class="text-end">
                                @can('admin.documents.delete')
                                    <form method="POST" action="{{ route('admin.documents.destroy', $d) }}" onsubmit="return confirm('Delete this document? The file is removed and cannot be recovered.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">No documents uploaded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('admin.documents.upload')
            <hr>
            <form method="POST" action="{{ route('admin.documents.store', $booking) }}" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="doc_type">Type</label>
                    <select id="doc_type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="">Choose...</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="doc_traveler">Belongs to</label>
                    <select id="doc_traveler" name="traveler_id" class="form-select @error('traveler_id') is-invalid @enderror">
                        <option value="">Whole booking</option>
                        @foreach ($booking->travelers as $t)
                            <option value="{{ $t->id }}" @selected((string) old('traveler_id') === (string) $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                    @error('traveler_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="doc_label">Note <span class="text-secondary small">(optional)</span></label>
                    <input id="doc_label" name="label" maxlength="120" value="{{ old('label') }}" class="form-control @error('label') is-invalid @enderror" placeholder="e.g. Photo page">
                    @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9">
                    <label class="form-label" for="doc_file">File</label>
                    <input id="doc_file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="form-control @error('file') is-invalid @enderror" required>
                    @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">PDF, JPG or PNG, up to {{ $limitKb / 1024 }} MB.</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Upload</button>
                </div>
            </form>
        @endcan
    </div>
</div>
