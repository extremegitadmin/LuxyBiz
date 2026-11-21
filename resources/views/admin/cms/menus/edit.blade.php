
@extends('admin.layouts.master')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Edit Menu: {{ $menu->name }}</h4>

        <a href="{{ route('admin.cms.menus.index') }}" class="btn btn-secondary">
            Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
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

    <form action="{{ route('admin.cms.menus.update', $menu->id) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Menu Information --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Menu Information</strong>
            </div>

            <div class="card-body">
                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Menu Name</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               value="{{ old('name', $menu->name) }}"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Location</label>
                        <input type="text"
                               class="form-control"
                               value="{{ $menu->location }}"
                               disabled>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Menu Status</label>
                        <select name="is_active" class="form-select" required>
                            <option value="1"
                                @selected(old('is_active', $menu->is_active) == 1)>
                                Active
                            </option>

                            <option value="0"
                                @selected(old('is_active', $menu->is_active) == 0)>
                                Inactive
                            </option>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        {{-- Menu Items --}}
        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Menu Items</strong>

                <button type="button"
                        class="btn btn-sm btn-success"
                        id="addMenuItem">
                    <i class="fas fa-plus"></i> Add Menu Item
                </button>
            </div>

            <div class="card-body">

                <div id="menuItems">

                    @foreach($menu->allItems as $index => $item)

                        <div class="menu-item border rounded p-3 mb-3">

                            <input type="hidden"
                                   name="items[{{ $index }}][id]"
                                   value="{{ $item->id }}">

                            <div class="row">

                                {{-- Label --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Label</label>

                                    <input type="text"
                                           name="items[{{ $index }}][label]"
                                           class="form-control"
                                           value="{{ old("items.$index.label", $item->label) }}"
                                           required>
                                </div>

                                {{-- URL --}}
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">URL</label>

                                    <input type="text"
                                           name="items[{{ $index }}][url]"
                                           class="form-control"
                                           value="{{ old("items.$index.url", $item->url) }}"
                                           placeholder="/about">
                                </div>

                                {{-- Parent Item --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Parent Item</label>

                                    <select name="items[{{ $index }}][parent_id]"
                                            class="form-select">

                                        <option value="">None</option>

                                        @foreach($menu->allItems as $parent)
                                            @if($parent->id != $item->id)

                                                <option value="{{ $parent->id }}"
                                                    @selected(
                                                        old("items.$index.parent_id", $item->parent_id) == $parent->id
                                                    )>
                                                    {{ $parent->label }}
                                                </option>

                                            @endif
                                        @endforeach

                                    </select>
                                </div>

                                {{-- Sort Order --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Order</label>

                                    <input type="number"
                                           name="items[{{ $index }}][sort_order]"
                                           class="form-control"
                                           value="{{ old("items.$index.sort_order", $item->sort_order) }}"
                                           min="0">
                                </div>

                                {{-- Target --}}
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Target</label>

                                    <select name="items[{{ $index }}][target]"
                                            class="form-select">

                                        <option value="_self"
                                            @selected($item->target == '_self')>
                                            Same Window
                                        </option>

                                        <option value="_blank"
                                            @selected($item->target == '_blank')>
                                            New Window
                                        </option>

                                    </select>
                                </div>

                                {{-- Active --}}
                                <div class="col-md-6 mb-2">

                                    <input type="hidden"
                                           name="items[{{ $index }}][is_active]"
                                           value="0">

                                    <label class="form-check-label">
                                        <input type="checkbox"
                                               name="items[{{ $index }}][is_active]"
                                               value="1"
                                               class="form-check-input"
                                               @checked($item->is_active)>
                                        Active
                                    </label>

                                </div>

                                {{-- Remove --}}
                                <div class="col-md-6 text-end">
                                    <button type="button"
                                            class="btn btn-sm btn-danger remove-item">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>

                            </div>
                        </div>

                    @endforeach

                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Menu
                </button>

            </div>
        </div>

    </form>
</div>

{{-- New Menu Item Template --}}
<template id="menuItemTemplate">

    <div class="menu-item border rounded p-3 mb-3">

        <div class="row">

            <div class="col-md-3 mb-3">
                <label class="form-label">Label</label>

                <input type="text"
                       name="items[__INDEX__][label]"
                       class="form-control"
                       required>
            </div>

            <div class="col-md-3 mb-3">
                <label class="form-label">URL</label>

                <input type="text"
                       name="items[__INDEX__][url]"
                       class="form-control"
                       placeholder="/about">
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Parent Item</label>

                <select name="items[__INDEX__][parent_id]"
                        class="form-select">

                    <option value="">None</option>

                    @foreach($menu->allItems as $parent)
                        <option value="{{ $parent->id }}">
                            {{ $parent->label }}
                        </option>
                    @endforeach

                </select>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Order</label>

                <input type="number"
                       name="items[__INDEX__][sort_order]"
                       class="form-control"
                       value="0"
                       min="0">
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Target</label>

                <select name="items[__INDEX__][target]"
                        class="form-select">

                    <option value="_self">Same Window</option>
                    <option value="_blank">New Window</option>

                </select>
            </div>

            <div class="col-md-6 mb-2">

                <input type="hidden"
                       name="items[__INDEX__][is_active]"
                       value="0">

                <label class="form-check-label">
                    <input type="checkbox"
                           name="items[__INDEX__][is_active]"
                           value="1"
                           class="form-check-input"
                           checked>
                    Active
                </label>

            </div>

            <div class="col-md-6 text-end">
                <button type="button"
                        class="btn btn-sm btn-danger remove-item">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>

        </div>
    </div>

</template>

<script>
document.addEventListener('DOMContentLoaded', function () {

    let itemIndex = {{ $menu->allItems->count() }};

    const container = document.getElementById('menuItems');
    const template = document.getElementById('menuItemTemplate');
    const addButton = document.getElementById('addMenuItem');

    // Add new menu item
    addButton.addEventListener('click', function () {

        let html = template.innerHTML.replaceAll('__INDEX__', itemIndex++);

        container.insertAdjacentHTML('beforeend', html);

    });

    // Remove menu item
    container.addEventListener('click', function (event) {

        const removeButton = event.target.closest('.remove-item');

        if (removeButton) {
            removeButton.closest('.menu-item').remove();
        }

    });

});
</script>

@endsection
