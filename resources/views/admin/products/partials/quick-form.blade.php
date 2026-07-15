@php
    $selectedShopId = old('shop_id', $product?->shop_id);
    $selectedCategoryId = old('product_category_id', $product?->product_category_id);
    $selectedBrandId = old('brand_id', $product?->brand_id);
    $selectedStatus = old('status', $product?->status ?? 'draft');
    $allowCreateStatusSelection = $allowCreateStatusSelection ?? false;
    $statusOptions = $product || $allowCreateStatusSelection ? $statuses : ['draft' => $statuses['draft']];
    $includeShortDescription = $includeShortDescription ?? false;
    $selectedShop = $selectedShopId ? $shops->firstWhere('id', (int) $selectedShopId) : null;
    $productRoutePrefix = $productRoutePrefix ?? 'admin';
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="shop_id" class="form-label">Shop <span class="text-danger">*</span></label>
            @if($product && $selectedShop)
                <input type="hidden" id="shop_id" name="shop_id" value="{{ $selectedShop->getKey() }}" data-root-category-id="{{ $selectedShop->root_product_category_id }}">
                <div class="form-control bg-light">
                    {{ $selectedShop->name }}
                    @if($selectedShop->merchant)
                        - {{ $selectedShop->merchant->business_name }}
                    @endif
                    <span>(<strong>{{ ucfirst($selectedShop->status) }}</strong>)</span>
                </div>
            @else
                <select id="shop_id" name="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required>
                    <option value="">Select Shop</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" data-root-category-id="{{ $shop->root_product_category_id }}" @selected((string) $selectedShopId === (string) $shop->id)>
                            {{ $shop->name }}
                            @if($shop->merchant)
                                - {{ $shop->merchant->business_name }}
                            @endif
                            ({{ ucfirst($shop->status) }})
                        </option>
                    @endforeach
                </select>
            @endif
            @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="product_category_id" class="form-label">Product Category <span class="text-danger">*</span></label>
            <select id="product_category_id" name="product_category_id" class="form-select @error('product_category_id') is-invalid @enderror" required>
                <option value="">Select Product Category</option>
                @foreach($productCategories as $category)
                    <option value="{{ $category->id }}" data-root-category-id="{{ $category->root_category_id }}" data-selectable="{{ $category->is_selectable_leaf ? '1' : '0' }}" @selected((string) $selectedCategoryId === (string) $category->id) @disabled(! $category->is_selectable_leaf)>
                        {{ $category->full_path_label ?? $category->name }}
                    </option>
                @endforeach
            </select>
            @error('product_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="brand_id" class="form-label">Brand</label>
            <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror">
                <option value="">No Brand</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" data-root-category-ids="{{ $brand->rootProductCategories->pluck('id')->implode(',') }}" data-current-selected="{{ (string) $selectedBrandId === (string) $brand->id ? '1' : '0' }}" @selected((string) $selectedBrandId === (string) $brand->id)>
                        {{ $brand->name }}
                    </option>
                @endforeach
            </select>
            @error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
            <input id="product_name" name="product_name" type="text" value="{{ old('product_name', $product?->product_name) }}" class="form-control @error('product_name') is-invalid @enderror" required>
            @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($product)
                <div class="form-text">Slug: {{ $product->slug }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach($statusOptions as $value => $status)
                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $status['label'] }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        @if($includeShortDescription)
            <div class="col-12">
                <label for="short_description" class="form-label">Short Description</label>
                <textarea id="short_description" name="short_description" rows="3" class="form-control @error('short_description') is-invalid @enderror">{{ old('short_description', $product?->short_description) }}</textarea>
                @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const shopSelect = document.getElementById('shop_id');
            const categorySelect = document.getElementById('product_category_id');
            const brandSelect = document.getElementById('brand_id');

            if (!shopSelect || !categorySelect) {
                return;
            }

            const syncCategoryOptions = function () {
                const selectedShop = shopSelect.tagName === 'SELECT' ? shopSelect.options[shopSelect.selectedIndex] : shopSelect;
                const rootCategoryId = selectedShop ? selectedShop.dataset.rootCategoryId : '';
                let selectedCategoryVisible = false;
                let selectedBrandVisible = false;

                Array.from(categorySelect.options).forEach(function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const belongsToShopType = rootCategoryId && option.dataset.rootCategoryId === rootCategoryId;
                    option.hidden = !belongsToShopType;
                    option.disabled = !belongsToShopType || option.dataset.selectable !== '1';

                    if (option.selected && belongsToShopType && option.dataset.selectable === '1') {
                        selectedCategoryVisible = true;
                    }
                });

                if (!selectedCategoryVisible) {
                    categorySelect.value = '';
                }

                if (!brandSelect) {
                    return;
                }

                Array.from(brandSelect.options).forEach(function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const rootIds = (option.dataset.rootCategoryIds || '').split(',').filter(Boolean);
                    const isCurrentSelected = option.dataset.currentSelected === '1';
                    const belongsToShopType = rootCategoryId && rootIds.includes(rootCategoryId);
                    option.hidden = !belongsToShopType && !isCurrentSelected;
                    option.disabled = !belongsToShopType && !isCurrentSelected;

                    if (option.selected && (belongsToShopType || isCurrentSelected)) {
                        selectedBrandVisible = true;
                    }
                });

                if (!selectedBrandVisible) {
                    brandSelect.value = '';
                }
            };

            if (shopSelect.tagName === 'SELECT') {
                shopSelect.addEventListener('change', syncCategoryOptions);
            }

            syncCategoryOptions();
        });
    </script>
@endpush

<div class="card-footer d-flex justify-content-end gap-2">
    <a href="{{ route($productRoutePrefix.'.products.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="ph-floppy-disk me-2"></i>
        {{ $submitLabel }}
    </button>
</div>
