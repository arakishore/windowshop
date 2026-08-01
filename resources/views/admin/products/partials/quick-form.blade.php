@php
    $selectedShopId = old('shop_id', $product?->shop_id ?? ($shops->count() === 1 ? $shops->first()->getKey() : null));
    $selectedCategoryId = old('product_category_id', $product?->product_category_id);
    $selectedBrandId = old('brand_id', $product?->brand_id);
    $selectedStatus = old('status', $product?->status ?? 'draft');
    $allowCreateStatusSelection = $allowCreateStatusSelection ?? false;
    $statusOptions = $product || $allowCreateStatusSelection ? $statuses : ['draft' => $statuses['draft']];
    $includeShortDescription = $includeShortDescription ?? false;
    $selectedShop = $selectedShopId ? $shops->firstWhere('id', (int) $selectedShopId) : null;
    $productRoutePrefix = $productRoutePrefix ?? 'admin';
    $taxClasses = $taxClasses ?? collect();
    $availabilityStatuses = $availabilityStatuses ?? collect();
    $selectedAvailabilityStatusId = old('availability_status_id', $product?->availability_status_id);
    $selectedTaxMode = old('tax_mode', $product?->tax_mode ?? 'inherit');
    $selectedTaxClassId = old('tax_class_id', $product?->tax_class_id);
    $selectedCategory = $selectedCategoryId ? $productCategories->firstWhere('id', (int) $selectedCategoryId) : null;
    $selectedCategoryDefaultTaxName = $selectedCategory?->defaultTaxClass?->taxSummaryLabel() ?? 'No Default';
    $selectedOverrideTaxName = $selectedTaxClassId ? ($taxClasses->firstWhere('id', (int) $selectedTaxClassId)?->taxSummaryLabel() ?? $product?->taxClass?->taxSummaryLabel() ?? 'Selected tax class') : 'Choose Tax Class';
    $effectiveTaxName = match ($selectedTaxMode) {
        'override' => $selectedOverrideTaxName,
        'exempt' => 'No Tax',
        default => 'Will be resolved during checkout',
    };
    $merchantTaxEnabled = (bool) ($merchantTaxEnabled ?? false);
    $featuredFromValue = old('featured_from', $product?->featured_from?->format('Y-m-d\TH:i'));
    $featuredUntilValue = old('featured_until', $product?->featured_until?->format('Y-m-d\TH:i'));
    $selectedIsFeatured = (bool) old('is_featured', $product?->is_featured ?? false);
    $featuredStatus = $product ? $product->featuredStatus() : ($selectedIsFeatured ? 'current' : 'disabled');
    $featuredStatusClasses = [
        'current' => 'bg-success bg-opacity-10 text-success',
        'scheduled' => 'bg-info bg-opacity-10 text-info',
        'expired' => 'bg-warning bg-opacity-10 text-warning',
        'disabled' => 'bg-light text-body border',
    ];
    $featuredStatusLabels = [
        'current' => 'Featured Now',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'disabled' => 'Disabled',
    ];
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
                <input type="hidden" id="shop_id" name="shop_id" value="{{ $selectedShop->getKey() }}" data-root-category-id="{{ $selectedShop->root_product_category_id }}" data-merchant-id="{{ $selectedShop->merchant_id }}">
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
                        <option value="{{ $shop->id }}" data-root-category-id="{{ $shop->root_product_category_id }}" data-merchant-id="{{ $shop->merchant_id }}" @selected((string) $selectedShopId === (string) $shop->id)>
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
                    <option value="{{ $category->id }}" data-root-category-id="{{ $category->root_category_id }}" data-selectable="{{ $category->is_selectable_leaf ? '1' : '0' }}" data-default-tax-class-name="{{ $category->defaultTaxClass?->taxSummaryLabel() ?? 'No Default' }}" @selected((string) $selectedCategoryId === (string) $category->id) @disabled(! $category->is_selectable_leaf)>
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

        <div class="col-12">
            <div class="border-top pt-3 mt-2">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-lg-between gap-2 mb-3">
                    <div>
                        <div class="fw-semibold">Customer Availability</div>
                        <div class="text-muted small">Controls the message shown to customers and whether they may purchase when stock reaches zero.</div>
                    </div>
                    @if($product?->availabilityStatus)
                        <span class="badge {{ $product->availabilityStatus->safeBadgeClass() }}">
                            {{ $product->availabilityStatus->name }}
                        </span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <label for="availability_status_id" class="form-label">Availability Status</label>
                        <select id="availability_status_id" name="availability_status_id" class="form-select @error('availability_status_id') is-invalid @enderror">
                            <option value="">Default: In Stock</option>
                            @foreach($availabilityStatuses as $availabilityStatus)
                                <option
                                    value="{{ $availabilityStatus->id }}"
                                    data-merchant-id="{{ $availabilityStatus->merchant_id }}"
                                    data-current-selected="{{ (string) $selectedAvailabilityStatusId === (string) $availabilityStatus->id ? '1' : '0' }}"
                                    @selected((string) $selectedAvailabilityStatusId === (string) $availabilityStatus->id)
                                >
                                    {{ $availabilityStatus->name }} - {{ $availabilityStatus->purchase_allowed ? 'Purchase allowed' : 'Purchase blocked' }}
                                </option>
                            @endforeach
                        </select>
                        @error('availability_status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="border-top pt-3 mt-2">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-lg-between gap-2 mb-3">
                    <div>
                        <div class="fw-semibold">Featured Product</div>
                        <div class="text-muted small">Featured products can be used later in the website, mobile app, marketplace, or POS quick-pick. Existing product sort order controls their display order.</div>
                    </div>
                    <span class="badge {{ $featuredStatusClasses[$featuredStatus] ?? $featuredStatusClasses['disabled'] }}">
                        {{ $featuredStatusLabels[$featuredStatus] ?? 'Disabled' }}
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input id="sort_order" name="sort_order" type="number" min="0" max="999999" value="{{ old('sort_order', $product?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <label class="form-check mb-2">
                            <input name="is_featured" value="1" type="checkbox" class="form-check-input" @checked($selectedIsFeatured)>
                            <span class="form-check-label">Feature this product</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label for="featured_from" class="form-label">Featured From</label>
                        <input id="featured_from" name="featured_from" type="datetime-local" value="{{ $featuredFromValue }}" class="form-control @error('featured_from') is-invalid @enderror">
                        @error('featured_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="featured_until" class="form-label">Featured Until</label>
                        <input id="featured_until" name="featured_until" type="datetime-local" value="{{ $featuredUntilValue }}" class="form-control @error('featured_until') is-invalid @enderror">
                        @error('featured_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        @if($includeShortDescription)
            <div class="col-12">
                <label for="short_description" class="form-label">Short Description</label>
                <textarea id="short_description" name="short_description" rows="3" class="form-control @error('short_description') is-invalid @enderror">{{ old('short_description', $product?->short_description) }}</textarea>
                @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif

        @if($product && $merchantTaxEnabled)
            <div class="col-12">
                <div class="border-top pt-3 mt-2">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-lg-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">Tax Configuration</div>
                            <div class="text-muted small">Uses the default tax determined from the category or merchant settings.</div>
                        </div>
                        <div class="text-lg-end small">
                            <div class="text-muted">Current Category</div>
                            <div class="fw-semibold js-tax-current-category">{{ $selectedCategory?->name ?? $product->category?->name ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="vstack gap-2">
                                <label class="form-check border rounded p-3 ps-5 mb-0">
                                    <input class="form-check-input js-tax-mode" type="radio" name="tax_mode" value="inherit" @checked($selectedTaxMode === 'inherit')>
                                    <span class="fw-semibold">Use Default Tax</span>
                                    <span class="d-block text-muted small">Uses the category default now and merchant fallback in future tax resolution.</span>
                                </label>

                                <label class="form-check border rounded p-3 ps-5 mb-0">
                                    <input class="form-check-input js-tax-mode" type="radio" name="tax_mode" value="exempt" @checked($selectedTaxMode === 'exempt')>
                                    <span class="fw-semibold">Tax Exempt</span>
                                    <span class="d-block text-muted small">Never charge tax for this product.</span>
                                </label>

                                <label class="form-check border rounded p-3 ps-5 mb-0">
                                    <input class="form-check-input js-tax-mode" type="radio" name="tax_mode" value="override" @checked($selectedTaxMode === 'override')>
                                    <span class="fw-semibold">Override Tax Class</span>
                                    <span class="d-block text-muted small">Always use the selected tax class for this product.</span>
                                </label>
                            </div>
                            @error('tax_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-lg-5">
                            <div class="js-tax-class-wrap">
                                <label for="tax_class_id" class="form-label">Tax Class <span class="text-danger">*</span></label>
                                <select id="tax_class_id" name="tax_class_id" class="form-select @error('tax_class_id') is-invalid @enderror">
                                    <option value="">Select Tax Class</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}" data-tax-summary="{{ $taxClass->taxSummaryLabel() }}" @selected((string) $selectedTaxClassId === (string) $taxClass->id)>{{ $taxClass->displayLabel() }}</option>
                                    @endforeach
                                </select>
                                @error('tax_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="bg-light border rounded p-3 mt-3">
                                <div class="row g-2 small">
                                    <div class="col-6 text-muted">Default Tax</div>
                                    <div class="col-6 text-end fw-semibold js-tax-category-default">{{ $selectedCategoryDefaultTaxName }}</div>
                                    <div class="col-6 text-muted">Effective Tax</div>
                                    <div class="col-6 text-end fw-semibold js-tax-effective">{{ $effectiveTaxName }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
            const availabilitySelect = document.getElementById('availability_status_id');
            const taxClassWrap = document.querySelector('.js-tax-class-wrap');
            const taxClassSelect = document.getElementById('tax_class_id');
            const taxCategoryDefault = document.querySelector('.js-tax-category-default');
            const taxEffective = document.querySelector('.js-tax-effective');
            const taxCurrentCategory = document.querySelector('.js-tax-current-category');

            if (!shopSelect || !categorySelect) {
                return;
            }

            const syncCategoryOptions = function () {
                const selectedShop = shopSelect.tagName === 'SELECT' ? shopSelect.options[shopSelect.selectedIndex] : shopSelect;
                const rootCategoryId = selectedShop ? selectedShop.dataset.rootCategoryId : '';
                let selectedCategoryVisible = false;
                let selectedBrandVisible = false;
                let selectedAvailabilityVisible = false;
                const merchantId = selectedShop ? selectedShop.dataset.merchantId : '';

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

                if (!availabilitySelect) {
                    return;
                }

                Array.from(availabilitySelect.options).forEach(function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const isCurrentSelected = option.dataset.currentSelected === '1';
                    const belongsToMerchant = merchantId && option.dataset.merchantId === merchantId;
                    option.hidden = !belongsToMerchant && !isCurrentSelected;
                    option.disabled = !belongsToMerchant && !isCurrentSelected;

                    if (option.selected && (belongsToMerchant || isCurrentSelected)) {
                        selectedAvailabilityVisible = true;
                    }
                });

                if (!selectedAvailabilityVisible) {
                    availabilitySelect.value = '';
                }
            };

            const syncTaxConfiguration = function () {
                const selectedMode = document.querySelector('.js-tax-mode:checked');

                if (!selectedMode || !taxClassWrap || !taxClassSelect) {
                    return;
                }

                const selectedCategory = categorySelect.options[categorySelect.selectedIndex];
                const categoryDefault = selectedCategory?.dataset.defaultTaxClassName || 'No Default';
                const categoryName = selectedCategory?.value ? selectedCategory.textContent.trim() : '-';
                const selectedTaxClass = taxClassSelect.options[taxClassSelect.selectedIndex];
                const overrideName = selectedTaxClass?.value ? (selectedTaxClass.dataset.taxSummary || selectedTaxClass.textContent.trim()) : 'Choose Tax Class';

                taxClassWrap.hidden = selectedMode.value !== 'override';
                taxClassSelect.required = selectedMode.value === 'override';
                taxClassSelect.disabled = selectedMode.value !== 'override';

                if (taxCategoryDefault) {
                    taxCategoryDefault.textContent = categoryDefault;
                }

                if (taxCurrentCategory) {
                    taxCurrentCategory.textContent = categoryName;
                }

                if (taxEffective) {
                    taxEffective.textContent = selectedMode.value === 'override'
                        ? overrideName
                        : (selectedMode.value === 'exempt' ? 'No Tax' : 'Will be resolved during checkout');
                }
            };

            if (shopSelect.tagName === 'SELECT') {
                shopSelect.addEventListener('change', syncCategoryOptions);
            }

            categorySelect.addEventListener('change', syncTaxConfiguration);
            document.querySelectorAll('.js-tax-mode').forEach(function (radio) {
                radio.addEventListener('change', syncTaxConfiguration);
            });

            if (taxClassSelect) {
                taxClassSelect.addEventListener('change', syncTaxConfiguration);
            }

            syncCategoryOptions();
            syncTaxConfiguration();
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
