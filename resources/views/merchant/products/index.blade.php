{{-- Purpose: Lists products for the merchant active shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Products"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Products' => null]"
        :action-url="route('merchant.products.create')"
        action-label="Add Product"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $hasFilters = $filters['search'] !== '' || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0">Product List</h5>
                <div class="text-muted small">{{ $activeShop->name }}</div>
            </div>
            <a href="#product-filter-collapse" class="text-body collapsed product-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="product-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="product-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('merchant.products.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Product name, slug, or brand">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $value => $status)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('merchant.products.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($products->isEmpty())
            <x-empty-state icon="ph-package" title="No products found" message="Create a product or adjust the current filters." />
        @else
            <form method="POST" action="{{ route('merchant.products.bulk-action') }}" class="js-bulk-product-form">
                @csrf
                <div class="card-body border-bottom d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group" style="max-width: 280px;">
                        <label class="input-group-text" for="bulk_action">Bulk Actions</label>
                        <select id="bulk_action" name="action" class="form-select" required>
                            <option value="">Choose</option>
                            @if($filters['status'] === 'archived')
                                <option value="restore_archive">Restore from Archive</option>
                            @else
                                <option value="mark_active">Mark Active</option>
                                <option value="mark_inactive">Mark Inactive</option>
                                <option value="archive">Archive</option>
                            @endif
                            <option value="delete">Delete to Trash</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-light js-bulk-product-submit">
                        <i class="ph-check-square-offset me-2"></i>
                        Apply
                    </button>
                    <span class="text-muted small js-selected-count">0 selected</span>
                </div>

            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 44px;">
                                <input type="checkbox" class="form-check-input js-select-all-products" aria-label="Select all products">
                            </th>
                            <th>Product</th>
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
                                <td class="text-center">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="form-check-input js-product-checkbox" aria-label="Select {{ $product->product_name }}">
                                </td>
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
                                        <a href="{{ route('merchant.products.edit', $product) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="{{ $product->status === 'archived' ? 'View' : 'Edit' }}">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        @if($product->status === 'archived')
                                            <form method="POST" action="{{ route('merchant.products.restore-archive', $product) }}" class="d-inline js-confirm-action-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore from Archive" data-title="Restore Product" data-message="Restore this archived product?<br><br>The product will be restored as Draft and must be reviewed before activation." data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                    <i class="ph-arrow-clockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('merchant.products.duplicate', $product) }}" class="d-inline js-confirm-action-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-info border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Duplicate" data-title="Duplicate Product" data-message="Duplicate this product?<br><br>The product information, attributes, variants, descriptions and images will be copied.<br><br>Stock will be reset to 0, SKUs/barcodes will not be copied, and the new product will be saved as Draft." data-confirm-label="Yes, Duplicate" data-confirm-class="btn-primary">
                                                    <i class="ph-copy"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('merchant.products.archive', $product) }}" class="d-inline js-confirm-action-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-warning border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Archive" data-title="Archive Product" data-message="Archive Product?<br><br>This product will be hidden from customers but will remain available in your Archived products.<br><br>You can restore it later." data-confirm-label="Yes, Archive" data-confirm-class="btn-warning">
                                                    <i class="ph-archive"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('merchant.products.destroy', $product) }}" class="d-inline js-confirm-action-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Product" data-message="Delete Product?<br><br>This product will be moved to Trash.<br><br>An administrator can restore it within 45 days. After 45 days, it may be permanently deleted automatically." data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
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
            </form>

            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} entries
                </div>
                {{ $products->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-confirm-action');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-confirm-action-form');

                bootbox.confirm({
                    title: button.dataset.title,
                    message: button.dataset.message,
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: button.dataset.confirmLabel,
                            className: button.dataset.confirmClass,
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });

            const bulkForm = document.querySelector('.js-bulk-product-form');
            const selectAll = document.querySelector('.js-select-all-products');
            const selectedCount = document.querySelector('.js-selected-count');
            const bulkMessages = {
                mark_active: {
                    title: 'Mark Products Active',
                    message: 'Mark selected products as Active?<br><br>Each product must have publish-ready active variants.',
                    label: 'Yes, Mark Active',
                    className: 'btn-success',
                },
                mark_inactive: {
                    title: 'Mark Products Inactive',
                    message: 'Mark selected products as Inactive?<br><br>They will not be shown to customers.',
                    label: 'Yes, Mark Inactive',
                    className: 'btn-warning',
                },
                archive: {
                    title: 'Archive Products',
                    message: 'Archive selected products?<br><br>These products will be hidden from customers but will remain available in your Archived products.',
                    label: 'Yes, Archive',
                    className: 'btn-warning',
                },
                restore_archive: {
                    title: 'Restore Products',
                    message: 'Restore selected archived products?<br><br>Products will be restored as Draft and must be reviewed before activation.',
                    label: 'Yes, Restore',
                    className: 'btn-success',
                },
                delete: {
                    title: 'Delete Products',
                    message: 'Delete selected products?<br><br>These products will be moved to Trash. An administrator can restore them within 45 days.',
                    label: 'Yes, Delete',
                    className: 'btn-danger',
                },
            };

            function productCheckboxes() {
                return Array.from(document.querySelectorAll('.js-product-checkbox'));
            }

            function updateSelectedCount() {
                const selected = productCheckboxes().filter((checkbox) => checkbox.checked).length;

                if (selectedCount) {
                    selectedCount.textContent = selected + ' selected';
                }

                if (selectAll) {
                    selectAll.checked = selected > 0 && selected === productCheckboxes().length;
                    selectAll.indeterminate = selected > 0 && selected < productCheckboxes().length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    productCheckboxes().forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                    updateSelectedCount();
                });
            }

            document.addEventListener('change', function (event) {
                if (event.target.closest('.js-product-checkbox')) {
                    updateSelectedCount();
                }
            });

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-bulk-product-submit');

                if (!button || !bulkForm) {
                    return;
                }

                const action = bulkForm.querySelector('[name="action"]').value;
                const selected = productCheckboxes().filter((checkbox) => checkbox.checked).length;

                if (!action || selected === 0) {
                    bootbox.alert('Please choose a bulk actions and select at least one product.');
                    return;
                }

                const config = bulkMessages[action];

                bootbox.confirm({
                    title: config.title,
                    message: config.message,
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: config.label,
                            className: config.className,
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            bulkForm.submit();
                        }
                    },
                });
            });

            updateSelectedCount();
        });
    </script>
@endpush
