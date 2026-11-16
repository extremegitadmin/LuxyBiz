@extends('admin.layouts.app')
@section('title', 'Objectives')

@section('content')
<h1 class="page-title h3 mb-1">Objectives</h1>
<p class="text-secondary mb-3">The goals visitors can filter tours by on the website, such as Wellness or Adventure.</p>

<form method="POST" action="{{ route('admin.objectives.store') }}" class="panel panel-body mb-3 d-flex gap-2 align-items-end" style="max-width:520px">
    @csrf
    <div class="flex-grow-1"><label class="form-label small" for="name">New objective</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="80"></div>
    <button class="btn btn-primary">Add</button>
</form>

<div class="panel" style="max-width:720px">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Name</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($objectives as $o)
                <tr>
                    <td><input form="obj-{{ $o->id }}" name="name" value="{{ $o->name }}" class="form-control form-control-sm" aria-label="Objective name"></td>
                    <td>{{ $o->tours_count }}</td>
                    <td class="text-end text-nowrap">
                        <form id="obj-{{ $o->id }}" method="POST" action="{{ route('admin.objectives.update', $o) }}" class="d-inline">@csrf @method('PUT')
                            <button class="btn btn-sm btn-primary">Rename</button></form>
                        <form method="POST" action="{{ route('admin.objectives.destroy', $o) }}" class="d-inline" onsubmit="return confirm('Delete this objective?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" @disabled($o->tours_count > 0) title="{{ $o->tours_count > 0 ? 'In use by tours' : '' }}">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-4 text-secondary">No objectives yet. Add the first one above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
