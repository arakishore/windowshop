{{-- Purpose: Shows product category hierarchy, audit details, and direct child categories. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Product Category Details"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Categories' => route('admin.master.product-categories.index'), $category->name => null]"
        :action-url="route('admin.master.product-categories.edit', $category)"
        action-label="Edit Category"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $path = $categoryPaths[$category->id] ?? $category->full_path;
    @endphp

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Category Information</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <tbody>
                    <tr>
                        <th style="width: 240px;">Name</th>
                        <td>{{ $category->name }}</td>
                    </tr>
                    <tr>
                        <th>Slug</th>
                        <td><code>{{ $category->slug }}</code></td>
                    </tr>
                    <tr>
                        <th>Parent Category</th>
                        <td>{{ $category->parent?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Complete Category Path</th>
                        <td>{{ $path }}</td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td>{{ $category->description ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Sort Order</th>
                        <td>{{ $category->sort_order }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge {{ $statusClasses[$category->status] ?? 'bg-secondary' }}">
                                {{ ucfirst($category->status) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Created By</th>
                        <td>{{ $category->created_by ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Updated By</th>
                        <td>{{ $category->updated_by ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td>{{ $category->created_at?->format('d M Y, h:i A') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Updated At</th>
                        <td>{{ $category->updated_at?->format('d M Y, h:i A') ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Child Categories</h5>
        </div>

        @if($category->children->isEmpty())
            <x-empty-state icon="ph-tree-structure" title="No child categories" message="This category does not have child categories yet." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Assigned Products</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($category->children as $child)
                            <tr>
                                <td>{{ $child->name }}</td>
                                <td><code>{{ $child->slug }}</code></td>
                                <td>{{ $child->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$child->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($child->status) }}
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-body border">{{ $child->products_count }}</span></td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.master.product-categories.show', $child) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                            <i class="ph-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.master.product-categories.edit', $child) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
