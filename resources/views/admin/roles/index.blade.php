@extends('admin.layouts.master')
@section('title', 'Roles')
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
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-10">
                            <h5 class="mb-4">Roles List</h5>
                        </div>
                        <div class="col-md-2">
                            @can('roles.create')<a href="{{ route('admin.roles.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Create role</a>@endcan
                        </div>
                    </div>                   
                    <p class="text-secondary">A role is a named set of permissions. Create the role first, then add users under it on the Users page. The Super Admin always has every permission and is not a role.</p>
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Description</th>
                                    <th>Permissions</th>
                                    <th>Users</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roles as $role)
                                <tr>
                                    <td class="fw-medium">{{ $role->name }}</td>
                                    <td class="text-secondary small">{{ $role->description }}</td>
                                    <td>{{ $role->permissions_count }} of {{ $total }}</td>
                                    <td>@can('admin.users.view')<a href="{{ route('admin.users.index', ['role_id' => $role->id]) }}">{{ $role->users_count }}</a>@else{{ $role->users_count }}@endcan</td>
                                    <td class="text-end text-nowrap">
                                        @can('admin.users.create')<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.create', ['role_id' => $role->id]) }}">Add user</a>@endcan
                                        @can('admin.roles.edit')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.roles.edit', $role) }}">Edit</a>@endcan
                                        @can('roles.delete')
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm="Delete the role {{ $role->name }}?">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" @disabled($role->users_count > 0) title="{{ $role->users_count > 0 ? 'Move its users to another role first' : 'Delete role' }}">Delete</button>
                                            </form>
                                        @endcan
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
    @endsection('content')
    <!-- End Section -->

