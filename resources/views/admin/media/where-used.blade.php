
@extends('admin.layouts.master')

@section('content')

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4>Where Used</h4>
            <p class="text-muted mb-0">
                Check where this media file is being used in your CMS.
            </p>
        </div>

        <a href="{{ route('admin.media.show', $media->id) }}"
           class="btn btn-secondary">
            Back to Media Details
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-3">

            @if($media->file_type === 'image')
                <img
                    src="{{ asset('storage/' . $media->file_path) }}"
                    alt="{{ $media->alt_text ?? $media->name }}"
                    style="width:100px;height:80px;object-fit:cover;"
                    class="rounded"
                >
            @else
                <i class="fas fa-file fa-3x text-secondary"></i>
            @endif

            <div>
                <h5 class="mb-1">{{ $media->name }}</h5>
                <p class="text-muted mb-0">
                    {{ $media->file_type }} |
                    {{ number_format($media->file_size / 1024, 2) }} KB
                </p>
            </div>

        </div>
    </div>

    <div class="card">

        <div class="card-header">
            <h5 class="mb-0">Usage References</h5>
        </div>

        <div class="card-body">

            @if($whereUsed->isEmpty())

                <div class="text-center py-5">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>

                    <h5>No Usage Found</h5>

                    <p class="text-muted mb-0">
                        This media file is not currently linked to any CMS content.
                    </p>
                </div>

            @else

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Content Type</th>
                                <th>Title</th>
                                <th>Reference</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($whereUsed as $index => $usage)

                                <tr>
                                    <td>{{ $index + 1 }}</td>

                                    <td>
                                        <span class="badge bg-info">
                                            {{ $usage['type'] }}
                                        </span>
                                    </td>

                                    <td>{{ $usage['title'] }}</td>

                                    <td>{{ $usage['reference'] }}</td>
                                </tr>

                            @endforeach
                        </tbody>

                    </table>
                </div>

            @endif

        </div>
    </div>

</div>

@endsection
