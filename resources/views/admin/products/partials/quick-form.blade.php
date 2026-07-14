@php
    $selectedShopId = old('shop_id', $product?->shop_id);
    $selectedCategoryId = old('product_category_id', $product?->product_category_id);
    $selectedBrandId = old('brand_id', $product?->brand_id);
    $selectedStatus = old('status', $product?->status ?? 'draft');
    $statusOptions = $product ? $statuses : ['draft' => $statuses['draft']];
    $includeShortDescription = $includeShortDescription ?? false;
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
            <select id="shop_id" name="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required>
                <option value="">Select Shop</option>
                @foreach($shops as $shop)
                    <option value="{{ $shop->id }}" data-root-category-id="{{ $shop->root_product_category_id }}" @selected((string) $selectedShopId === (string) $shop->id)>
                        {{ $shop->name }} @if($shop->merchant) - {{ $shop->merchant->business_name }} @endif
                    </option>
                @endforeach
            </select>
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
                    <option value="{{ $brand->id }}" @selected((string) $selectedBrandId === (string) $brand->id)>
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

            if (!shopSelect || !categorySelect) {
                return;
            }

            const syncCategoryOptions = function () {
                const selectedShop = shopSelect.options[shopSelect.selectedIndex];
                const rootCategoryId = selectedShop ? selectedShop.dataset.rootCategoryId : '';
                let selectedCategoryVisible = false;

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
            };

            shopSelect.addEventListener('change', syncCategoryOptions);
            syncCategoryOptions();
        });
    </script>
@endpush

<div class="card-footer d-flex justify-content-end gap-2">
    <a href="{{ route('admin.products.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="ph-floppy-disk me-2"></i>
        {{ $submitLabel }}
    </button>
</div>
