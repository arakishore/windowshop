{{-- Purpose: Lists product category master data for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Product Categories"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Categories' => null]"
        :action-url="route('admin.master.product-categories.create')"
        action-label="Create Category"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['search'] !== '' || $filters['parent_id'] || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Product Category List</h5>
            <a href="#product-category-filter-collapse" class="text-body collapsed product-category-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="product-category-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="product-category-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.product-categories.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Category name, slug, or parent">
                    </div>
                    <div class="col-md-4">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select id="parent_id" name="parent_id" class="form-select">
                            <option value="">All</option>
                            <option value="root" @selected($filters['parent_id'] === 'root')>No Parent / Root Category</option>
                            @foreach($parentCategories as $parentCategory)
                                <option value="{{ $parentCategory->id }}" @selected((string) $filters['parent_id'] === (string) $parentCategory->id)>
                                    {{ $parentCategory->full_path_label ?? $parentCategory->name }}
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
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.product-categories.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($categories->isEmpty())
            <x-empty-state icon="ph-tag" title="No product categories found" message="Create a category or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Parent Category</th>
                            <th>Category Path</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                            @php
                                $path = $categoryPaths[$category->id] ?? $category->name;
                                $depth = substr_count($path, ' > ');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="padding-left: {{ $depth * 20 }}px;">
                                        @if($depth > 0)
                                            <span class="text-muted">{{ str_repeat('-- ', $depth) }}</span>
                                        @endif
                                        {{ $category->name }}
                                    </div>
                                    @if($category->description)
                                        <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($category->description, 80) }}</div>
                                    @endif
                                </td>
                                <td>{{ $category->parent?->name ?? '-' }}</td>
                                <td class="text-muted">{{ $path }}</td>
                                <td>{{ $category->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$category->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($category->status) }}
                                    </span>
                                </td>
                                <td>{{ $category->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.master.product-categories.show', $category) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                            <i class="ph-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.master.product-categories.edit', $category) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        @if($category->parent_id === null)
                                            <a href="{{ route('admin.master.product-categories.attribute-groups.edit', $category) }}" class="list-icons-item product-category-mapping-action" data-bs-popup="tooltip" title="Manage Attribute Mapping">
                                                <i class="ph-sliders-horizontal"></i>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.master.product-categories.destroy', $category) }}" class="d-inline js-delete-product-category-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-product-category" data-bs-popup="tooltip" title="Delete">
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

            <div class="card-body">
                <div class="text-muted">
                    Showing {{ $categories->count() }} entries
                </div>
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .product-category-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .product-category-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }

        .product-category-mapping-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 50%;
            background-color: #eef2ff;
            color: #4f46e5;
        }

        .product-category-mapping-action:hover,
        .product-category-mapping-action:focus {
            background-color: #4f46e5;
            color: #fff;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-product-category');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-product-category-form');

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
