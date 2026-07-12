{{-- Purpose: Lists shop category master data for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Shop Categories"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Shop Categories' => null]"
        :action-url="route('admin.master.shop-categories.create')"
        action-label="Create Category"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['name'] !== '' || $filters['slug'] !== '' || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Shop Category List</h5>
            <a href="#shop-category-filter-collapse" class="text-body collapsed shop-category-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="shop-category-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="shop-category-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.shop-categories.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="name" class="form-label">Name</label>
                        <input id="name" name="name" type="search" value="{{ $filters['name'] }}" class="form-control" placeholder="Category name">
                    </div>
                    <div class="col-md-4">
                        <label for="slug" class="form-label">Slug</label>
                        <input id="slug" name="slug" type="search" value="{{ $filters['slug'] }}" class="form-control" placeholder="category-slug">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.shop-categories.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($categories->isEmpty())
            <x-empty-state icon="ph-tag" title="No shop categories found" message="Create a category or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Assigned Shops</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td style="width: 76px;">
                                    <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        @if($category->image_path)
                                            <img src="{{ asset('storage/'.$category->image_path) }}" alt="{{ $category->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            <i class="ph-image text-muted"></i>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $category->name }}</div>
                                    @if($category->description)
                                        <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($category->description, 80) }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $category->slug }}</code></td>
                                <td>
                                    <span class="badge bg-light text-body border">{{ $category->shops_count }}</span>
                                </td>
                                <td>{{ $category->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$category->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($category->status) }}
                                    </span>
                                </td>
                                <td>{{ $category->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.master.shop-categories.edit', $category) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.master.shop-categories.destroy', $category) }}" class="d-inline js-delete-shop-category-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-shop-category" data-bs-popup="tooltip" title="Delete">
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
                    Showing {{ $categories->firstItem() }} to {{ $categories->lastItem() }} of {{ $categories->total() }} entries
                </div>
                {{ $categories->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .shop-category-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .shop-category-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-shop-category');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-shop-category-form');

                bootbox.confirm({
                    title: 'Delete Category',
                    message: 'Are you sure you want to delete this category?',
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
