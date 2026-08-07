@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Banner Library"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Banner Library' => null]"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Reusable WindowShop Templates</h5>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.banner-library.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Name, code, or title">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="availability">Available For</label>
                    <select id="availability" name="availability" class="form-select">
                        <option value="">Admin or Both</option>
                        @foreach($availabilities as $value => $label)
                            <option value="{{ $value }}" @selected($filters['availability'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="position">Position</label>
                    <select id="position" name="position" class="form-select">
                        <option value="">All</option>
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['position'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                    <a class="btn btn-light" href="{{ route('admin.banner-library.index') }}">Reset</a>
                </div>
            </form>
        </div>

        @if($templates->isEmpty())
            <x-empty-state icon="ph-images-square" title="No usable templates found" message="Adjust the current filters or activate a compatible template." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Template</th>
                            <th>Category</th>
                            <th>Default Position</th>
                            <th>Available For</th>
                            <th>Event</th>
                            <th>Used By</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            <tr>
                                <td style="width: 120px;">
                                    <img src="{{ asset('storage/'.$template->desktop_image_path) }}" alt="{{ $template->name }}" class="rounded border" style="width: 96px; height: 54px; object-fit: cover;">
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $template->name }}</div>
                                    <div class="text-muted fs-sm">{{ $template->default_title }}</div>
                                </td>
                                <td><span class="badge bg-light text-body border">{{ $template->categoryLabel() }}</span></td>
                                <td>{{ $template->positionLabel() }}</td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ $template->availabilityLabel() }}</span></td>
                                <td>{{ $template->event_code ?: 'General' }}</td>
                                <td>{{ $template->banners_count }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.banners.create', ['template' => $template->uuid]) }}" class="btn btn-primary btn-sm">
                                        <i class="ph-lightning me-1"></i>
                                        Use Template
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">Showing {{ $templates->firstItem() }} to {{ $templates->lastItem() }} of {{ $templates->total() }} entries</div>
                {{ $templates->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection
