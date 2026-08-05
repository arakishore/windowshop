{{-- Purpose: Lists reusable banner templates for admin template-library management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Banner Templates"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Banner Templates' => null]"
        :action-url="route('admin.banner-templates.create')"
        action-label="Create Template"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Banner Template List</h5>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.banner-templates.index') }}" class="row g-3 align-items-end">
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
                        <option value="">All</option>
                        @foreach($availabilities as $value => $label)
                            <option value="{{ $value }}" @selected($filters['availability'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="default_position">Position</label>
                    <select id="default_position" name="default_position" class="form-select">
                        <option value="">All</option>
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['default_position'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-6">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                    <a class="btn btn-light" href="{{ route('admin.banner-templates.index') }}">Reset</a>
                </div>
            </form>
        </div>

        @if($templates->isEmpty())
            <x-empty-state icon="ph-images-square" title="No banner templates found" message="Create a template or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Position</th>
                            <th>Available For</th>
                            <th>Used By</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            <tr>
                                <td style="width: 110px;">
                                    <img src="{{ asset('storage/'.$template->desktop_image_path) }}" alt="{{ $template->name }}" class="rounded border" style="width: 88px; height: 48px; object-fit: cover;">
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $template->name }}</div>
                                    <div class="text-muted fs-sm">{{ $template->code }}</div>
                                    @if($template->event_code)
                                        <span class="badge bg-info-subtle text-info border mt-1">{{ $template->event_code }}</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-light text-body border">{{ $template->categoryLabel() }}</span></td>
                                <td>
                                    <div>{{ $template->positionLabel() }}</div>
                                    <div class="text-muted fs-sm">Sort {{ $template->sort_order }}</div>
                                </td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ $template->availabilityLabel() }}</span></td>
                                <td>{{ $template->banners_count }}</td>
                                <td>
                                    <span class="badge {{ $template->status === 'active' ? 'bg-success' : 'bg-light text-body border' }}">{{ ucfirst($template->status) }}</span>
                                </td>
                                <td class="text-muted fs-sm">{{ app_datetime($template->updated_at) }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.banner-templates.edit', $template) }}" class="list-icons-item text-primary" title="Edit"><i class="ph-pencil-simple"></i></a>
                                        <form method="POST" action="{{ route('admin.banner-templates.toggle-status', $template) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button class="list-icons-item {{ $template->status === 'active' ? 'text-warning' : 'text-success' }} border-0 bg-transparent p-0" type="submit" title="{{ $template->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                                <i class="{{ $template->status === 'active' ? 'ph-pause-circle' : 'ph-play-circle' }}"></i>
                                            </button>
                                        </form>
                                    </div>
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
