@extends('admin.layouts.master')
@php
    $isEdit = $role->exists;
    $mine = auth()->user()->permissionSlugs();
    $isSuper = auth()->user()->is_super;
@endphp
@section('title', $isEdit ? 'Edit role' : 'Create role')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title">Roles</div>
        <div class="ms-auto">
            <div class="btn-group">
                {{-- button add --}}
            </div>
        </div>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif 
    <!--end breadcrumb-->
    <div class="row">
		<div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-4">{{ $isEdit ? 'Edit role: ' . $role->name : 'Create role' }}</h5>
                    <form class="row g-3" method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
                        @csrf
                        @if ($isEdit) @method('PUT') @endif
                        <div class="col-md-5">
                            <label for="input1" class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" maxlength="60" value="{{ old('name', $role->name) }}" placeholder="For example: Accounts" required>
                        </div>
                        <div class="col-md-7">
                            <label for="input2" class="form-label">Short description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" name="description" maxlength="255" value="{{ old('description', $role->description) }}">
                        </div>
                        <h5>Permissions</h5>
                        <p class="text-secondary small">Tick what people in this role may do. Anything not ticked is hidden from the menu and blocked on the server.</p>
                        <div class="row">
                            @foreach ($groups as $group => $items)
                                <div class="col-lg-6">
                                    <div class="panel h-100">
                                        <div class="panel-head"><span>{{ $group }}</span>
                                            <label class="form-check small fw-normal mb-0">
                                            <input type="checkbox" class="form-check-input js-group"> All</label>
                                        </div>
                                        <div class="panel-body">
                                            @foreach ($items as $slug => $label)
                                                @php $may = $isSuper || in_array($slug, $mine, true); @endphp
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" id="p-{{ $slug }}" name="permissions[]" value="{{ $slug }}"
                                                    @checked(in_array($slug, old('permissions', $held), true)) @disabled(! $may)>
                                                    <label class="form-check-label" for="p-{{ $slug }}">{{ $label }}
                                                    @unless ($may)<span class="d-block small text-secondary">You do not hold this permission, so you cannot grant it.</span>@endunless</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-12">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-primary px-4">{{ $isEdit ? 'Save role' : 'Create role' }}</button>
                                <button type="button" class="btn btn-secondary px-4">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endsection('content')
    <!-- End Section -->
    @push('scripts')
    <script>
    document.querySelectorAll('.js-group').forEach(function (all) {
        var panel = all.closest('.panel');
        var boxes = function () { return panel.querySelectorAll('.panel-body input[type=checkbox]:not(:disabled)'); };
        var sync = function () { var b = boxes(); all.checked = b.length > 0 && Array.prototype.every.call(b, function (x) { return x.checked; }); };
        all.addEventListener('change', function () { boxes().forEach(function (x) { x.checked = all.checked; }); });
        panel.addEventListener('change', function (e) { if (e.target !== all) sync(); });
        sync();
    });
    </script>
    @endpush
