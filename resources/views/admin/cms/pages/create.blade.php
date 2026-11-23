
@extends('admin.layouts.master')

@section('title', 'Create CMS Page')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Create New Page</h4>
            <p class="text-muted mb-0">Add a new page to your website.</p>
        </div>

        <a href="{{ route('admin.cms.pages.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Pages
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            Please correct the errors in the form.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cms.pages.store') }}">
        @csrf

        @include('admin.cms.pages._form')
    </form>

</div>
@endsection
