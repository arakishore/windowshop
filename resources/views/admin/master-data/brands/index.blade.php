{{-- Purpose: Lists brand master data for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Brands"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Brands' => null]"
        :action-url="route('admin.master.brands.create')"
        action-label="Create Brand"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['name'] !== '' || $selectedRootCategoryId || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Brand List</h5>
            <a href="#brand-filter-collapse" class="text-body collapsed brand-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="brand-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="brand-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.brands.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Name</label>
                        <input id="name" name="name" type="search" value="{{ $filters['name'] }}" class="form-control" placeholder="Brand name">
                    </div>
                    <div class="col-md-3">
                        <label for="root_product_category_id" class="form-label">Applicable Shop Type</label>
                        <select id="root_product_category_id" name="root_product_category_id" class="form-select">
                            <option value="">All</option>
                            @foreach($rootProductCategories as $rootProductCategory)
                                <option value="{{ $rootProductCategory->getKey() }}" @selected($selectedRootCategoryId === $rootProductCategory->getKey())>
                                    {{ $rootProductCategory->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.brands.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($brands->isEmpty())
            <x-empty-state icon="ph-copyright" title="No brands found" message="Create a brand or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Logo</th>
                            <th>Brand Name</th>
                            <th>Slug</th>
                            <th>Applicable Shop Types</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($brands as $brand)
                            <tr>
                                <td style="width: 76px;">
                                    <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        @if($brand->logo_path)
                                            <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            <i class="ph-image text-muted"></i>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $brand->name }}</div>
                                    @if($brand->website_url)
                                        <a href="{{ $brand->website_url }}" target="_blank" rel="noopener" class="fs-sm">{{ $brand->website_url }}</a>
                                    @endif
                                    @if($brand->description)
                                        <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($brand->description, 80) }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $brand->slug }}</code></td>
                                <td>
                                    @forelse($brand->rootProductCategories as $rootProductCategory)
                                        <span class="badge bg-light text-body border me-1 mb-1">
                                            {{ $rootProductCategory->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
                                <td>{{ $brand->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$brand->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($brand->status) }}
                                    </span>
                                </td>
                                <td>{{ $brand->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.master.brands.edit', $brand) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.master.brands.destroy', $brand) }}" class="d-inline js-delete-brand-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-brand" data-bs-popup="tooltip" title="Delete">
                                                <i class="ph-trash"></i>
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
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $brands->firstItem() }} to {{ $brands->lastItem() }} of {{ $brands->total() }} entries
                </div>
                {{ $brands->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .brand-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .brand-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-brand');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-brand-form');

                bootbox.confirm({
                    title: 'Delete Brand',
                    message: 'Are you sure you want to delete this brand?',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });
        });
    </script>
@endpush
