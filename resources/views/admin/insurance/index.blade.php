@extends('admin.layouts.master')
@section('title', 'Insurance Options')

@section('content')
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
        <div class="breadcrumb-title">Insurance Options</div>
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
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @can('admin.insurance.create')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-4">Create Insurance Option</h5>
                    <form class="row g-3" method="POST" action="{{ route('admin.insurance.store') }}">
                        @csrf
                        <div class="col-md-9">
                            <label class="form-label">Label</label>
                            <input name="name" class="form-control" required maxlength="120" value="{{ old('name') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price each</label>
                            <input name="price" type="number" step="0.01" min="0" class="form-control" value="{{ old('price') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" maxlength="1000">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="d-md-flex d-grid align-items-center gap-3">
                                <button type="submit" class="btn btn-primary px-4">Save</button>
                                <button type="reset" class="btn btn-secondary px-4">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endcan

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-4">Insurance Options List</h5>
                    <div class="table-responsive">
                        <table id="example" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Option</th>
                                    <th style="width:130px">Price each</th>
                                    <th style="width:90px">Order</th>
                                    <th style="width:90px">Active</th>
                                    @canany(['admin.insurance.edit', 'admin.insurance.delete'])
                                        <th class="text-end">Actions</th>
                                    @endcanany
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($options as $o)
                                    @php $f = 'ins-' . $o->id; @endphp
                                    <tr>
                                        @can('admin.insurance.edit')
                                            <td>
                                                <input form="{{ $f }}" name="name" value="{{ $o->name }}" class="form-control mb-1" aria-label="Label" required>
                                                <textarea form="{{ $f }}" name="description" rows="2" class="form-control" aria-label="Description">{{ $o->description }}</textarea>
                                            </td>
                                            <td>
                                                <input form="{{ $f }}" name="price" type="number" step="0.01" min="0" value="{{ $o->price }}" class="form-control" aria-label="Price">
                                            </td>
                                            <td>
                                                <input form="{{ $f }}" name="sort_order" type="number" min="0" value="{{ $o->sort_order }}" class="form-control" aria-label="Order">
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input form="{{ $f }}" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($o->is_active) aria-label="Active">
                                                </div>
                                            </td>
                                        @else
                                            <td>
                                                <strong>{{ $o->name }}</strong>
                                                @if ($o->description)
                                                    <div class="text-muted small">{{ $o->description }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $o->price }}</td>
                                            <td>{{ $o->sort_order }}</td>
                                            <td>
                                                <span class="badge {{ $o->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $o->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        @endcan

                                        @canany(['admin.insurance.edit', 'admin.insurance.delete'])
                                            <td class="text-end text-nowrap">
                                                @can('admin.insurance.edit')
                                                    <form id="{{ $f }}" method="POST" action="{{ route('admin.insurance.update', $o) }}" class="d-inline">
                                                        @csrf @method('PUT')
                                                        <button class="btn btn-sm btn-primary">Save</button>
                                                    </form>
                                                @endcan
                                                @can('admin.insurance.delete')
                                                    <form method="POST" action="{{ route('admin.insurance.destroy', $o) }}" class="d-inline" data-confirm="Delete this option?">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        @endcanany
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