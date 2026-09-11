@php
    $heading = ['eligible' => 'Eligible Purchase', 'buy' => 'Customer Buys', 'get' => 'Customer Gets'][$role] ?? 'Targets';
    $ids = fn (string $type) => $targetIds($role, $type);
    $selectedValues = fn (string $type) => collect(old($prefix.$type.'_ids', $ids($type)))
        ->filter(fn ($id): bool => $id !== null && $id !== '')
        ->map(fn ($id): string => (string) $id)
        ->values();
    $summary = function ($items, $labelResolver): string {
        $labels = collect($items)->map($labelResolver)->filter()->values();

        if ($labels->isEmpty()) {
            return 'No specific targets selected yet.';
        }

        return 'Selected: '.$labels->join(', ');
    };
    $selectedProductIds = $selectedValues('product');
    $selectedCategoryIds = $selectedValues('category');
    $selectedBrandIds = $selectedValues('brand');
    $selectedCollectionIds = $selectedValues('collection');
    $productStatuses = $products
        ->pluck('status')
        ->filter()
        ->unique()
        ->sort()
        ->values();
    $money = fn ($value): string => '₹'.number_format((float) $value, 0);
    $productImage = function ($product): ?string {
        $path = $product->primaryImage?->thumbnail_path ?: $product->primaryImage?->image_path;

        return $path ? asset('storage/'.$path) : null;
    };
    $defaultVariant = fn ($product) => $product->variants->first();
@endphp

<div class="col-12 js-target-row">
    <h6 class="fw-semibold mb-2 text-uppercase">{{ $heading }}</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="{{ $prefix }}target_scope">Source</label>
            <select id="{{ $prefix }}target_scope" name="{{ $prefix }}target_scope" class="form-select js-target-scope">
                <option value="all" @selected($scope === 'all')>All Products</option>
                <option value="products" @selected($scope === 'products')>Selected Products</option>
                <option value="categories" @selected($scope === 'categories')>Category</option>
                <option value="brands" @selected($scope === 'brands')>Brand</option>
                <option value="collections" @selected($scope === 'collections')>Collection</option>
            </select>
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="products">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <label class="form-label mb-0" for="{{ $prefix }}product_search">Products</label>
                <button type="button" class="btn btn-link btn-sm p-0 js-product-select-all">Select all filtered</button>
            </div>
            <div class="promotion-product-selector js-promotion-product-selector">
                <div class="promotion-product-search">
                    <i class="ph-magnifying-glass"></i>
                    <input id="{{ $prefix }}product_search" type="search" class="form-control js-product-search" placeholder="Search products, SKU or variant...">
                </div>
                <div class="promotion-product-filters">
                    <select class="form-select form-select-sm js-product-filter" data-filter="category">
                        <option value="">All Categories</option>
                        @foreach($categories as $categoryId => $categoryLabel)
                            <option value="{{ $categoryId }}">{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm js-product-filter" data-filter="brand">
                        <option value="">All Brands</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm js-product-filter" data-filter="status">
                        <option value="">All Status</option>
                        @foreach($productStatuses as $status)
                            <option value="{{ $status }}">{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-light btn-sm js-product-filter-clear">Clear</button>
                </div>
                <div class="promotion-product-list">
                    @foreach($products as $product)
                        @php
                            $variant = $defaultVariant($product);
                            $image = $productImage($product);
                            $meta = collect([
                                $variant?->name ? 'Variant '.$variant->name : null,
                                $variant?->sku ? 'SKU: '.$variant->sku : null,
                                $product->category?->name,
                                $product->brand?->name,
                            ])->filter()->join(' · ');
                            $searchText = collect([$product->product_name, $variant?->name, $variant?->sku, $product->category?->name, $product->brand?->name, $product->status])
                                ->filter()
                                ->join(' ');
                        @endphp
                        <label class="promotion-product-option js-product-option @if($selectedProductIds->contains((string) $product->id)) is-selected @endif"
                            data-search="{{ Str::lower($searchText) }}"
                            data-category="{{ $product->product_category_id }}"
                            data-brand="{{ $product->brand_id }}"
                            data-status="{{ $product->status }}">
                            <input type="checkbox" name="{{ $prefix }}product_ids[]" value="{{ $product->id }}" class="form-check-input js-product-checkbox" @checked($selectedProductIds->contains((string) $product->id))>
                            <span class="promotion-product-media">
                                @if($image)
                                    <img src="{{ $image }}" alt="{{ $product->product_name }}" class="promotion-product-thumb">
                                    <span class="promotion-product-preview"><img src="{{ $image }}" alt="{{ $product->product_name }}"></span>
                                @else
                                    <span class="promotion-product-placeholder">No Image</span>
                                @endif
                            </span>
                            <span class="promotion-product-copy">
                                <span class="promotion-product-name">{{ $product->product_name }}</span>
                                <span class="promotion-product-meta">{{ $meta ?: 'No variant details' }}</span>
                            </span>
                            <span class="promotion-product-side">
                                @if($product->status)
                                    <span class="badge bg-light text-muted border">{{ Str::headline($product->status) }}</span>
                                @endif
                                @if($variant)
                                    <span class="promotion-product-price">{{ $money($variant->selling_price) }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                    <div class="promotion-product-empty d-none js-product-empty">No matching products.</div>
                </div>
                <div class="promotion-product-footer">
                    <span class="js-product-selected-count">{{ $selectedProductIds->count() }}</span> selected · {{ $products->count() }} total
                    <button type="button" class="btn btn-link btn-sm p-0 js-product-clear-selection">Clear selection</button>
                </div>
            </div>
            <div class="form-text js-product-summary">{{ $summary($selectedProductIds, fn ($id) => $products->firstWhere('id', (int) $id)?->product_name) }}</div>
            @error($prefix.'product_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="categories">
            <label class="form-label" for="{{ $prefix }}category_ids">Category</label>
            <select id="{{ $prefix }}category_ids" name="{{ $prefix }}category_ids[]" class="form-select" multiple size="4">
                @foreach($categories as $categoryId => $categoryLabel)
                    <option value="{{ $categoryId }}" @selected($selectedCategoryIds->contains((string) $categoryId))>{{ $categoryLabel }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ $summary($selectedCategoryIds, fn ($id) => $categories->get((int) $id)) }}</div>
            @error($prefix.'category_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="brands">
            <label class="form-label" for="{{ $prefix }}brand_ids">Brand</label>
            @if($brands->isEmpty())
                <div class="form-control-plaintext text-muted">No brands available for this shop.</div>
            @else
                <select id="{{ $prefix }}brand_ids" name="{{ $prefix }}brand_ids[]" class="form-select" multiple size="4">
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected($selectedBrandIds->contains((string) $brand->id))>{{ $brand->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ $summary($selectedBrandIds, fn ($id) => $brands->firstWhere('id', (int) $id)?->name) }}</div>
            @endif
            @error($prefix.'brand_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="collections">
            <label class="form-label" for="{{ $prefix }}collection_ids">Collection</label>
            <select id="{{ $prefix }}collection_ids" name="{{ $prefix }}collection_ids[]" class="form-select" multiple size="4">
                @foreach($collections as $collection)
                    <option value="{{ $collection->id }}" @selected($selectedCollectionIds->contains((string) $collection->id))>{{ $collection->name }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ $summary($selectedCollectionIds, fn ($id) => $collections->firstWhere('id', (int) $id)?->name) }}</div>
            @error($prefix.'collection_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
