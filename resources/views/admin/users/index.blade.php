@extends('admin.layouts.master')
@section('title', 'Users')

@section('content')
 <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title">Users</div>
        <div class="ms-auto">
            <div class="btn-group">
                @can('admin.users.create')<a href="{{ route('admin.users.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-person-plus me-1"></i>Create user</a>@endcan
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
    <form method="GET" class="panel panel-body mb-3 row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label small" for="q">Search</label>
            <input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Name, email or phone">
        </div>
        <div class="col-md-3"><label class="form-label small" for="role_id">Role</label>
            <select id="role_id" name="role_id" class="form-select">
                <option value="">All roles</option>
                @foreach ($roles as $r)
                <option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Deactivated</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Reset</a>
        </div>
    </form>
    <div class="row">
		<div class="col-12 col-xl-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10">
                            <h5 class="mb-4">Users List</h5>
                        </div>
                    </div>         
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $m)
                                @php $locked = $m->is_super && ! auth()->user()->is_super; @endphp
                                <tr>
                                    <td class="fw-medium">{{ $m->name }}@if ($m->id === auth()->id()) <span class="text-secondary fw-normal">(you)</span>@endif</td>
                                    <td>{{ $m->email }}</td>
                                    <td>@if ($m->is_super)<span class="badge text-bg-dark">Super Admin</span>@elseif ($m->role){{ $m->role->name }}@else<span class="text-secondary">No role</span>@endif</td>
                                    <td>@if ($m->is_active)<span class="badge text-bg-success">Active</span>@else<span class="badge text-bg-secondary">Deactivated</span>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if ($locked)
                                            <span class="small text-secondary">Super Admin only</span>
                                        @else
                                            @can('admin.users.edit')
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $m) }}">Edit</a>
                                                @if ($m->id !== auth()->id())
                                                    <form method="POST" action="{{ route('admin.users.toggle', $m) }}" class="d-inline" data-confirm="{{ $m->is_active ? 'Deactivate ' . $m->name . '?' : 'Reactivate ' . $m->name . '?' }}">
                                                        @csrf @method('PATCH')
                                                        <button class="btn btn-sm {{ $m->is_active ? 'btn-outline-warning' : 'btn-outline-secondary' }}">{{ $m->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                                    </form>
                                                @endif
                                            @endcan
                                            @can('admin.users.delete')
                                                @if ($m->id !== auth()->id())
                                                    <form method="POST" action="{{ route('admin.users.destroy', $m) }}" class="d-inline" data-confirm="Delete {{ $m->name }}? This cannot be undone.">
                                                        @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
