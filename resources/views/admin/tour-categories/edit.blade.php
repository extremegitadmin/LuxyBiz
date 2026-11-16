
@extends('admin.layouts.master')

@section('title', 'Edit Tour Category')

@section('content')

<div class="d-flex align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Edit Tour Category</h1>

    <a href="{{ route('admin.tour-categories.index') }}"
       class="btn btn-outline-secondary ms-auto">
        Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-12 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Category Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.tour-categories.update', $category->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Category Name *</label>
                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   value="{{ old('name', $category->name) }}"
                                   required>
                        </div>

                         <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active"
                                {{ old('status', $category->status) == 'active' ? 'selected' : '' }}>
                                Active
                            </option>

                            <option value="inactive"
                                {{ old('status', $category->status) == 'inactive' ? 'selected' : '' }}>
                                Inactive
                            </option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Sort Order</label>
                            <input type="number"
                                   name="sort_order"
                                   class="form-control"
                                   value="{{ old('sort_order', $category->sort_order) }}"
                                   min="0">
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
