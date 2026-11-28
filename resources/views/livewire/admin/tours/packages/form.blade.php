
<div>
    <div class="page-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4>{{ $package ? 'Edit Package' : 'Add Package' }}</h4>
                    <p class="text-muted mb-0">
                        Tour: {{ $tour->title }}
                    </p>
                </div>

                <a href="{{ route('admin.tours.packages.index', $tour->id) }}"
                   class="btn btn-outline-secondary">
                    Back to Packages
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please correct the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit="save">

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Package Information</h5>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Package Name <span class="text-danger">*</span></label>

                                <select wire:model="name" class="form-select">
                                    <option value="Silver">Silver</option>
                                    <option value="Gold">Gold</option>
                                    <option value="Platinum">Platinum</option>
                                </select>

                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    Price Per Person ({{ $tour->currency ?? 'USD' }})
                                    <span class="text-danger">*</span>
                                </label>

                                <input type="number" step="0.01" min="0"
                                       wire:model="price" class="form-control">

                                @error('price')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Package Description</label>
                                <textarea wire:model="description"
                                          class="form-control" rows="3"></textarea>
                                @error('description')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Inclusions</label>
                                <textarea wire:model="inclusions"
                                          class="form-control" rows="5"
                                          placeholder="Enter one inclusion per line"></textarea>
                                @error('inclusions')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Exclusions</label>
                                <textarea wire:model="exclusions"
                                          class="form-control" rows="5"
                                          placeholder="Enter one exclusion per line"></textarea>
                                @error('exclusions')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" min="0"
                                       wire:model="sort_order" class="form-control">
                                @error('sort_order')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <input type="checkbox" wire:model="is_active"
                                           class="form-check-input" id="is_active">
                                    <label class="form-check-label" for="is_active">
                                        Active Package
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Package Itinerary</h5>

                        <button type="button" wire:click="addDay"
                                class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add Day
                        </button>
                    </div>

                    <div class="card-body">

                        @forelse($days as $index => $day)
                            <div class="border rounded p-3 mb-3"
                                 wire:key="package-day-{{ $index }}">

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Day {{ $index + 1 }}</h6>

                                    <button type="button"
                                            wire:click="removeDay({{ $index }})"
                                            class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Remove Day
                                    </button>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Day Number</label>
                                        <input type="number" min="1"
                                               wire:model="days.{{ $index }}.day_number"
                                               class="form-control">
                                        @error("days.$index.day_number")
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Day Title <span class="text-danger">*</span></label>
                                        <input type="text"
                                               wire:model="days.{{ $index }}.title"
                                               class="form-control">
                                        @error("days.$index.title")
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea wire:model="days.{{ $index }}.description"
                                                  class="form-control" rows="3"></textarea>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Activities</label>
                                        <textarea wire:model="days.{{ $index }}.activities"
                                                  class="form-control" rows="2"></textarea>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meals</label>
                                        <input type="text"
                                               wire:model="days.{{ $index }}.meals"
                                               class="form-control">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Stay</label>
                                        <input type="text"
                                               wire:model="days.{{ $index }}.stay"
                                               class="form-control">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                No itinerary days added yet. Click "Add Day" to begin.
                            </div>
                        @endforelse

                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-success"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove>
                            {{ $package ? 'Update Package' : 'Save Package' }}
                        </span>
                        <span wire:loading>
                            Saving...
                        </span>
                    </button>

                    <a href="{{ route('admin.tours.packages.index', $tour->id) }}"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>
