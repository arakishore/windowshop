{{-- Purpose: Lists products for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Products"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Products' => null]"
        :action-url="route('admin.products.create')"
        action-label="Add Product"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $hasFilters = $filters['search'] !== '' || $filters['shop_id'] || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Product List</h5>
            <a href="#product-filter-collapse" class="text-body collapsed product-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="product-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="product-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Product name, slug, or brand">
                    </div>
                    <div class="col-md-4">
                        <label for="shop_id" class="form-label">Shop</label>
                        <select id="shop_id" name="shop_id" class="form-select">
                            <option value="">All</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((string) $filters['shop_id'] === (string) $shop->id)>
                                    {{ $shop->name }}
                                    @if($shop->merchant)
                                        - {{ $shop->merchant->business_name }}
                                    @endif
                                    ({{ ucfirst($shop->status) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $value => $status)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($products->isEmpty())
            <x-empty-state icon="ph-package" title="No products found" message="Create a product or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Shop</th>
                            <th>Product Category</th>
                            <th>Brand</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                            @if($product->primaryImage)
                                                <img src="{{ asset('storage/'.($product->primaryImage->thumbnail_path ?: $product->primaryImage->image_path)) }}" alt="{{ $product->primaryImage->alt_text ?: $product->product_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <i class="ph-image text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $product->product_name }}</div>
                                            <code>{{ $product->slug }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $product->shop?->name ?? '-' }}</div>
                                    @if($product->shop?->merchant)
                                        <div class="fs-sm text-muted">{{ $product->shop->merchant->business_name }}</div>
                                    @endif
                                    @if($product->shop && $product->shop->status !== 'active')
                                        <div class="fs-sm text-muted">({{ ucfirst($product->shop->status) }})</div>
                                    @endif
                                </td>
                                <td>{{ $product->category?->name ?? '-' }}</td>
                                <td>{{ $product->brand?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $statuses[$product->status]['badge_class'] ?? 'bg-secondary' }}">
                                        {{ $statuses[$product->status]['label'] ?? ucfirst($product->status) }}
                                    </span>
                                </td>
                                <td>{{ $product->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline js-delete-product-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-product" data-bs-popup="tooltip" title="Delete">
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
                    Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} entries
                </div>
                {{ $products->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .product-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .product-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-product');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-product-form');

                bootbox.confirm({
                    title: 'Delete Product',
                    message: 'Are you sure you want to delete this product?',
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
