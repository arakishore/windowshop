{{-- Purpose: Lists generic marketplace and merchant banners for admin management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Banners"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Banners' => null]"
        :action-url="route('admin.banners.create')"
        action-label="Create Banner"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Banner List</h5>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.banners.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Title or subtitle">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="position">Position</label>
                    <select id="position" name="position" class="form-select">
                        <option value="">All</option>
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['position'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="owner_type">Owner</label>
                    <select id="owner_type" name="owner_type" class="form-select">
                        <option value="">All</option>
                        <option value="marketplace" @selected($filters['owner_type'] === 'marketplace')>Marketplace</option>
                        <option value="merchant" @selected($filters['owner_type'] === 'merchant')>Merchant Store</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                    <a class="btn btn-light" href="{{ route('admin.banners.index') }}">Reset</a>
                </div>
            </form>
        </div>

        @if($banners->isEmpty())
            <x-empty-state icon="ph-images" title="No banners found" message="Create a banner or adjust the current filters." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Source</th>
                            <th>Position</th>
                            <th>Owner</th>
                            <th>Schedule</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banners as $banner)
                            <tr>
                                <td style="width: 96px;">
                                    <img src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}" class="rounded border" style="width: 72px; height: 42px; object-fit: cover;">
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $banner->title }}</div>
                                    @if($banner->subtitle)
                                        <div class="text-muted fs-sm">{{ $banner->subtitle }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($banner->usesTemplate())
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">WindowShop Template</span>
                                        <div class="text-muted fs-sm">{{ $banner->bannerTemplate?->name ?? 'Historical template' }}</div>
                                    @else
                                        <span class="badge bg-light text-body border">Custom Upload</span>
                                    @endif
                                </td>
                                <td>{{ $banner->position?->label() ?? $banner->position }}</td>
                                <td>
                                    @if($banner->merchant_id)
                                        <div>{{ $banner->merchant?->business_name ?? 'Merchant' }}</div>
                                        <div class="text-muted fs-sm">{{ $banner->shop?->name ?? 'Shop' }}</div>
                                    @else
                                        Marketplace
                                    @endif
                                </td>
                                <td class="fs-sm">
                                    {{ $banner->starts_at ? app_datetime($banner->starts_at) : 'Immediate' }}
                                    <br>
                                    {{ $banner->ends_at ? app_datetime($banner->ends_at) : 'No end date' }}
                                </td>
                                <td>{{ $banner->sort_order }}</td>
                                <td>
                                    @if($banner->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $banner->status === 'active' ? 'bg-success' : 'bg-light text-body border' }}">{{ ucfirst($banner->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($banner->trashed())
                                            <form method="POST" action="{{ route('admin.banners.restore', $banner) }}" class="d-inline">
                                                @csrf
                                                <button class="list-icons-item text-success border-0 bg-transparent p-0" type="submit" title="Restore"><i class="ph-arrow-clockwise"></i></button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.banners.show', $banner) }}" class="list-icons-item text-body" title="View"><i class="ph-eye"></i></a>
                                            <a href="{{ route('admin.banners.edit', $banner) }}" class="list-icons-item text-primary" title="Edit"><i class="ph-pencil-simple"></i></a>
                                            <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="list-icons-item text-danger border-0 bg-transparent p-0" type="submit" title="Delete"><i class="ph-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">Showing {{ $banners->firstItem() }} to {{ $banners->lastItem() }} of {{ $banners->total() }} entries</div>
                {{ $banners->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection
