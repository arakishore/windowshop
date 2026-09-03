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
            <label class="form-label" for="{{ $prefix }}product_ids">Products</label>
            <select id="{{ $prefix }}product_ids" name="{{ $prefix }}product_ids[]" class="form-select" multiple size="4">
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected($selectedProductIds->contains((string) $product->id))>{{ $product->product_name }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ $summary($selectedProductIds, fn ($id) => $products->firstWhere('id', (int) $id)?->product_name) }}</div>
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
