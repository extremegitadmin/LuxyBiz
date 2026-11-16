@extends('admin.layouts.master')
@section('title', 'Tour Categories')
@push('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
@endpush
@section('content')

    <div class="d-flex align-items-center mb-3">
        <h1 class="page-title h3 mb-0">Tour Categories</h1>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Add Category --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add New Category</h5>
                </div>

                <div class="card-body">

                    <form method="POST"
                        action="{{ route('admin.tour-categories.store') }}">

                        @csrf

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">Category Name *</label>
                                <input type="text"
                                    name="name"
                                    class="form-control"
                                    value="{{ old('name') }}"
                                    placeholder="Enter category name"
                                    required>
                            </div>

                            <!-- <div class="col-md-4">
                                <label class="form-label">Slug</label>
                                <input type="text"
                                    name="slug"
                                    class="form-control"
                                    value="{{ old('slug') }}"
                                    placeholder="Leave empty to auto-generate">
                            </div> -->

                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active"
                                        {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                                        Active
                                    </option>
                                    <option value="inactive"
                                        {{ old('status') == 'inactive' ? 'selected' : '' }}>
                                        Inactive
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Sort Order</label>
                                <input type="number"
                                    name="sort_order"
                                    class="form-control"
                                    value="{{ old('sort_order', 0) }}"
                                    min="0">
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    Add Category
                                </button>
                            </div>

                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    {{-- Category List --}}
    <div class="row">
        <div class="col-12">
            <div class="card">

                <div class="card-header">
                    <h5 class="mb-0">Category List</h5>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table align-middle table-hover" id="example2">
                            <thead>
                                <tr>
                                    <th width="70">#</th>
                                    <th>Category Name</th>
                                    <!-- <th>Slug</th>
                                    <th>Description</th> -->
                                    <th>Sort Order</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($categories as $key => $category)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>

                                        <td>
                                            <strong>{{ $category->name }}</strong>
                                        </td>

                                        <td>{{ $category->sort_order }}</td>

                                        <td>
                                            @if($category->status == 'active')
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>

                                        <td class="text-end text-nowrap">

                                            <a href="{{ route('admin.tour-categories.edit', $category->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>

                                            <form method="POST"
                                                action="{{ route('admin.tour-categories.destroy', $category->id) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this category?')">

                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Delete
                                                </button>
                                            </form>

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
@push('scripts')

<!-- DataTables Core -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>

<!-- Export Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Export Buttons -->
<!-- <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script> -->

<script>
$(document).ready(function () {

    if ($.fn.DataTable.isDataTable('#example2')) {
        $('#example2').DataTable().destroy();
    }

    $('#example2').DataTable({

        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],

        searching: true,
        ordering: true,
        paging: true,
        info: true,

        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rtip',

        buttons: [
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
            'print'
        ],

        language: {
            emptyTable: "No categories available",
            zeroRecords: "No matching categories found"
        },

        columnDefs: [
            {
                orderable: false,
                searchable: false,
                targets: [0, 4]
            }
        ]

    });

});
</script>

@endpush