@extends('admin.layouts.master')
@php
    $isEdit = $member->exists;
    $isSelf = $isEdit && $member->id === auth()->id();
    $actorSuper = auth()->user()->is_super;
    $selectedRole = old('role_id', $member->role_id ?? request('role_id'));
    $isSuperChecked = (bool) old('is_super', $member->is_super);
    $preview = $roles->mapWithKeys(fn ($r) => [$r->id => ['name' => $r->name, 'perms' => $r->permissions->pluck('label')->values()->all()]]);
@endphp
@section('title', $isEdit ? 'Edit user' : 'Create user')
@section('content')
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title">Users</div>
        <div class="ms-auto">
            <div class="btn-group">
                {{-- button add --}}
            </div>
        </div>
    </div>
    <!--end breadcrumb-->
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
    <div class="row">
		<div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-4">{{ $isEdit ? 'Edit user: ' . $member->name : 'Create user' }}</h5>
                    <form class="row g-3" method="POST" action="{{ $isEdit ? route('admin.users.update', $member) : route('admin.users.store') }}">
                        @csrf
                        @if ($isEdit) @method('PUT') @endif
                        <div class="col-md-3">
                            <label for="input1" class="form-label">Name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $member->name) }}" required maxlength="80">
                        </div>
                        <div class="col-md-3">
                            <label for="input2" class="form-label">Phone</label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="{{ old('phone', $member->phone) }}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 15)" required>
                        </div>
                        <div class="col-md-3">
                            <label for="input2" class="form-label">Email (used to signin)</label>
                            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $member->email) }}" required maxlength="50">
                        </div>
                        <div class="col-md-3" id="role-box">
                            <label class="form-label" for="role_id">Role</label>
                            <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror" @disabled($isSelf && ! $actorSuper)>
                                <option value="">Choose a role</option>
                                @foreach ($roles as $r)<option value="{{ $r->id }}" @selected((string) $selectedRole === (string) $r->id)>{{ $r->name }}</option>@endforeach
                            </select>
                            @if ($isSelf && ! $actorSuper)<input type="hidden" name="role_id" value="{{ $member->role_id }}"><div class="form-text">You cannot change your own role.</div>@endif
                            @if ($roles->isEmpty())<div class="form-text text-danger">No roles exist yet. Create a role first.</div>@endif
                        </div>

                        <div class="col-md-6 align-self-end">
                            @if ($isEdit)
                                @if ($isSelf)<input type="hidden" name="is_active" value="1">@else<input type="hidden" name="is_active" value="0">@endif
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                    @checked(old('is_active', $member->is_active)) @disabled($isSelf)><label class="form-check-label" for="is_active">Account is active</label></div>
                            @else
                                <input type="hidden" name="is_active" value="0">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked><label class="form-check-label" for="is_active">Account is active</label></div>
                            @endif
                        </div>

                        @if ($actorSuper)
                            <div class="col-12">
                                @if ($isSelf && $member->is_super)<input type="hidden" name="is_super" value="1">@endif
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_super" value="1" id="is_super" @checked($isSuperChecked) @disabled($isSelf && $member->is_super)>
                                    <label class="form-check-label" for="is_super"><strong>Super Admin</strong>: full access to everything, no role needed</label>
                                </div>
                            </div>
                        @endif

                        <div class="col-12">
                            <div class="border rounded p-3 small" id="role-preview" aria-live="polite"></div>
                        </div>

                        <div class="col-12"><hr class="my-1"><div class="fw-semibold">{{ $isEdit ? 'Reset password' : 'Password' }}</div>
                            @if ($isEdit)<div class="form-text">Leave blank to keep the current password.</div>@endif
                        </div>
                        <div class="col-md-6"><label class="form-label" for="password">Password</label>
                            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required(! $isEdit)>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                        </div> 
                        <div class="col-md-12">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-primary px-4">{{ $isEdit ? 'Update user' : 'Create user' }}</button>
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
    (function () {
        var data = @json($preview);
        var select = document.getElementById('role_id');
        var superBox = document.getElementById('is_super');
        var box = document.getElementById('role-preview');
        function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function render() {
            var html;
            if (superBox && superBox.checked) {
                select.disabled = true;
                html = '<span class="badge text-bg-dark">Full access to everything (Super Admin)</span>';
            } else {
                if (superBox && !superBox.disabled) select.disabled = false;
                var r = data[select.value];
                if (!r) { box.innerHTML = '<span class="text-secondary">Pick a role to see what this person will be able to do.</span>'; return; }
                html = '<div class="fw-semibold mb-1">' + esc(r.name) + ' can</div>' +
                    (r.perms.length ? r.perms.map(function (p) { return '<span class="badge text-bg-primary me-1 mb-1">' + esc(p) + '</span>'; }).join('')
                                    : '<span class="text-secondary">Nothing yet. This person could only open the dashboard.</span>');
            }
            box.innerHTML = html;
        }
        select.addEventListener('change', render);
        if (superBox) superBox.addEventListener('change', render);
        render();
    })();
    </script>
    @endpush

