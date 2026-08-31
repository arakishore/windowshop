@php
    $heading = ['eligible' => 'Eligible Purchase', 'buy' => 'Customer Buys', 'get' => 'Customer Gets'][$role] ?? 'Targets';
    $ids = fn (string $type) => $targetIds($role, $type);
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
                    <option value="{{ $product->id }}" @selected(in_array($product->id, old($prefix.'product_ids', $ids('product')), true))>{{ $product->product_name }}</option>
                @endforeach
            </select>
            @error($prefix.'product_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="categories">
            <label class="form-label" for="{{ $prefix }}category_ids">Category</label>
            <select id="{{ $prefix }}category_ids" name="{{ $prefix }}category_ids[]" class="form-select" multiple size="4">
                @foreach($categories as $categoryId => $categoryLabel)
                    <option value="{{ $categoryId }}" @selected(in_array($categoryId, old($prefix.'category_ids', $ids('category')), true))>{{ $categoryLabel }}</option>
                @endforeach
            </select>
            @error($prefix.'category_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="brands">
            <label class="form-label" for="{{ $prefix }}brand_ids">Brand</label>
            @if($brands->isEmpty())
                <div class="form-control-plaintext text-muted">No brands available for this shop.</div>
            @else
                <select id="{{ $prefix }}brand_ids" name="{{ $prefix }}brand_ids[]" class="form-select" multiple size="4">
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(in_array($brand->id, old($prefix.'brand_ids', $ids('brand')), true))>{{ $brand->name }}</option>
                    @endforeach
                </select>
            @endif
            @error($prefix.'brand_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8 js-target-selector" data-target-selector="collections">
            <label class="form-label" for="{{ $prefix }}collection_ids">Collection</label>
            <select id="{{ $prefix }}collection_ids" name="{{ $prefix }}collection_ids[]" class="form-select" multiple size="4">
                @foreach($collections as $collection)
                    <option value="{{ $collection->id }}" @selected(in_array($collection->id, old($prefix.'collection_ids', $ids('collection')), true))>{{ $collection->name }}</option>
                @endforeach
            </select>
            @error($prefix.'collection_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
