{{-- Purpose: Assigns products to a merchant product collection. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Manage Collection Products"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Collections' => route('merchant.collections.index'), $collection->name => null]"
        :action-url="route('merchant.collections.edit', $collection)"
        action-label="Edit Collection"
        action-icon="ph-pencil-simple"
    />
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Selected Products ({{ $selectedProductCount }})</h5>
                </div>

                @if($selectedProductCount > 10 || $filters['selected_search'] !== '')
                    <div class="card-body border-bottom">
                        <form method="GET" action="{{ route('merchant.collections.products', $collection) }}" class="d-flex gap-2">
                            <input type="hidden" name="search" value="{{ $filters['search'] }}">
                            <input type="hidden" name="category_id" value="{{ $filters['category_id'] }}">
                            <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            <input name="selected_search" value="{{ $filters['selected_search'] }}" type="search" class="form-control" placeholder="Search selected products">
                            <button class="btn btn-light" type="submit">
                                <i class="ph-magnifying-glass"></i>
                            </button>
                        </form>
                    </div>
                @endif

                @if($selectedProductCount === 0)
                    <x-empty-state icon="ph-package" title="No products selected" message="Search products and add them to this collection." />
                @elseif($selectedProducts->isEmpty())
                    <x-empty-state icon="ph-magnifying-glass" title="No selected products found" message="Adjust the selected-products search." />
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th class="text-center">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedProducts as $product)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $product->product_name }}</div>
                                            <code>{{ $product->slug }}</code>
                                        </td>
                                        <td>{{ $product->category?->name ?? '-' }}</td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('merchant.collections.products.detach', [$collection, $product]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="list-icons-item text-danger border-0 bg-transparent p-0" data-bs-popup="tooltip" title="Remove">
                                                    <i class="ph-x-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add Products from {{ $activeShop->name }}</h5>
                </div>
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('merchant.collections.products', $collection) }}" class="row g-3 align-items-end">
                        <input type="hidden" name="selected_search" value="{{ $filters['selected_search'] }}">
                        <div class="col-md-5">
                            <label class="form-label" for="search">Search</label>
                            <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Product name, slug, or brand">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="0">All Categories</option>
                                @foreach($categoryOptions as $categoryId => $categoryLabel)
                                    <option value="{{ $categoryId }}" @selected((int) $filters['category_id'] === (int) $categoryId)>{{ $categoryLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="" @selected($filters['status'] === '')>All</option>
                                @foreach($productStatuses as $value => $status)
                                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $status['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-fill" type="submit">
                                <i class="ph-magnifying-glass me-2"></i>
                                Search
                            </button>
                            <a class="btn btn-light" href="{{ route('merchant.collections.products', $collection) }}">Reset</a>
                        </div>
                    </form>
                </div>

                @if($availableProducts->isEmpty())
                    <x-empty-state icon="ph-magnifying-glass" title="No available products found" message="Adjust the search or add more products to your catalog." />
                @else
                    <form method="POST" action="{{ route('merchant.collections.products.attach', $collection) }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 44px;">
                                            <input type="checkbox" class="form-check-input js-select-all-products" aria-label="Select all products">
                                        </th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Brand</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($availableProducts as $product)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="form-check-input js-product-checkbox" aria-label="Select {{ $product->product_name }}">
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $product->product_name }}</div>
                                                <code>{{ $product->slug }}</code>
                                            </td>
                                            <td>{{ $product->category?->name ?? '-' }}</td>
                                            <td>{{ $product->brand?->name ?? '-' }}</td>
                                            <td>{{ ucfirst($product->status) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="text-muted small js-selected-count">0 selected</span>
                            <div class="d-flex align-items-center gap-2">
                                {{ $availableProducts->onEachSide(1)->links('pagination::admin-datatable') }}
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph-plus me-2"></i>
                                    Add Selected
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.querySelector('.js-select-all-products');
            const selectedCount = document.querySelector('.js-selected-count');

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

            selectAll?.addEventListener('change', function () {
                productCheckboxes().forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateSelectedCount();
            });

            document.addEventListener('change', function (event) {
                if (event.target.closest('.js-product-checkbox')) {
                    updateSelectedCount();
                }
            });

            updateSelectedCount();
        });
    </script>
@endpush
