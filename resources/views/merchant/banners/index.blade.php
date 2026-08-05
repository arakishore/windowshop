@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Storefront Banners"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Storefront' => null, 'Banners' => null]"
        :action-url="route('merchant.banners.create')"
        action-label="Create Banner"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Banners for {{ $activeShop->name }}</h5>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('merchant.banners.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Title">
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
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        <option value="trash" @selected($filters['status'] === 'trash')>Trash</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                    <a class="btn btn-light" href="{{ route('merchant.banners.index') }}">Reset</a>
                </div>
            </form>
        </div>

        @if($banners->isEmpty())
            <x-empty-state icon="ph-images" title="No banners found" message="Create a storefront banner or adjust the filters." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Position</th>
                            <th>Schedule</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banners as $banner)
                            <tr>
                                <td style="width: 96px;"><img src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}" class="rounded border" style="width: 72px; height: 42px; object-fit: cover;"></td>
                                <td>{{ $banner->title }}</td>
                                <td>{{ $banner->position?->label() }}</td>
                                <td class="fs-sm">{{ $banner->starts_at ? app_datetime($banner->starts_at) : 'Immediate' }}<br>{{ $banner->ends_at ? app_datetime($banner->ends_at) : 'No end date' }}</td>
                                <td>{{ $banner->sort_order }}</td>
                                <td>{!! $banner->trashed() ? '<span class="badge bg-danger">Trash</span>' : '<span class="badge '.($banner->status === 'active' ? 'bg-success' : 'bg-light text-body border').'">'.ucfirst($banner->status).'</span>' !!}</td>
                                <td class="text-center">
                                    @if($banner->trashed())
                                        <form method="POST" action="{{ route('merchant.banners.restore', $banner) }}" class="d-inline">@csrf<button class="list-icons-item text-success border-0 bg-transparent p-0" type="submit"><i class="ph-arrow-clockwise"></i></button></form>
                                    @else
                                        <a href="{{ route('merchant.banners.edit', $banner) }}" class="list-icons-item text-primary"><i class="ph-pencil-simple"></i></a>
                                        <form method="POST" action="{{ route('merchant.banners.destroy', $banner) }}" class="d-inline">@csrf @method('DELETE')<button class="list-icons-item text-danger border-0 bg-transparent p-0" type="submit"><i class="ph-trash"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $banners->links('pagination::admin-datatable') }}</div>
        @endif
    </div>
@endsection
