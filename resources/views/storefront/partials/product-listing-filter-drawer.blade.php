@php
    $filterDrawerId = $filterDrawerId ?? 'filterShop';
    $filterTitle = $filterTitle ?? 'Filters';
    $showShopFilter = $showShopFilter ?? true;
    $shopFilterOptions = $shopFilterOptions ?? collect();
    $categoryFilterOptions = $categoryFilterOptions ?? collect();
    $selectedAttributeFilters = $selectedAttributeFilters ?? [];
    $selectedCategoryFilters = collect($selectedCategoryFilters ?? [])->map(fn ($value) => (string) $value);
    $priceOptions = [
        '' => 'Min',
        '100' => '&#8377;100',
        '250' => '&#8377;250',
        '500' => '&#8377;500',
        '1000' => '&#8377;1000',
        '2000' => '&#8377;2000',
        '5000' => '&#8377;5000',
        '10000' => '&#8377;10000+',
    ];
    $maxPriceOptions = [
        '' => 'Max',
        '500' => '&#8377;500',
        '1000' => '&#8377;1000',
        '2000' => '&#8377;2000',
        '5000' => '&#8377;5000',
        '10000' => '&#8377;10000',
    ];
    $selectedSort = (string) ($selectedFilters['sort'] ?? 'popularity');
    $selectedDiscounts = collect($selectedFilters['discount_min'] ?? [])->map(fn ($value) => (string) $value);
    $selectedShopIds = collect($selectedFilters['shops'] ?? [])->map(fn ($value) => (string) $value);
    $productSearch = (string) ($selectedFilters['search'] ?? '');
    $preservedQuery = collect(['promotion'])
        ->mapWithKeys(fn (string $key): array => request()->filled($key) ? [$key => request()->query($key)] : [])
        ->all();
    $resetUrl = url()->current().($preservedQuery !== [] ? '?'.http_build_query($preservedQuery) : '');
@endphp

<div class="offcanvas offcanvas-start canvas-filter" id="{{ $filterDrawerId }}">
    <form class="canvas-wrapper" method="GET" action="{{ url()->current() }}">
        <input type="hidden" name="sort" value="{{ $selectedSort }}">
        @foreach ($preservedQuery as $queryKey => $queryValue)
            <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
        @endforeach
        <div class="canvas-header">
            <div class="h5 title">{{ $filterTitle }}</div>
            <span class="icon-X2 fs-24 link icon-close-popup" data-bs-dismiss="offcanvas"></span>
        </div>
        <div class="canvas-body">
            <div class="d-flex gap-3 mb-16">
                <button type="button" class="link text-caption-01 fw-semibold storefront-filter-expand-all">Expand all</button>
                <button type="button" class="link text-caption-01 fw-semibold storefront-filter-collapse-all">Collapse all</button>
            </div>

            <div class="storefront-filter-field">
                <label for="{{ $filterDrawerId }}-product-search" class="storefront-filter-label">Search Products</label>
                <input
                    id="{{ $filterDrawerId }}-product-search"
                    class="storefront-filter-input"
                    type="search"
                    name="search"
                    value="{{ $productSearch }}"
                    placeholder="Search by product name"
                    autocomplete="off"
                >
            </div>

            @if ($showShopFilter && $shopFilterOptions->isNotEmpty())
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#{{ $filterDrawerId }}-shops" role="button" data-bs-toggle="collapse"
                        aria-expanded="true" aria-controls="{{ $filterDrawerId }}-shops">
                        <h6>Shop</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="{{ $filterDrawerId }}-shops" class="collapse show storefront-filter-collapse">
                        <div class="collapse-body">
                            <input
                                class="storefront-filter-input mb-12"
                                type="search"
                                placeholder="Search shops"
                                autocomplete="off"
                                data-shop-option-search
                            >
                            <ul class="filter-group-check group-category storefront-shop-filter-list">
                                @foreach ($shopFilterOptions as $shopOption)
                                    @php $shopOptionId = (string) $shopOption->getKey(); @endphp
                                    <li class="list-item" data-shop-option data-shop-name="{{ \Illuminate\Support\Str::lower($shopOption->name) }}">
                                        <input
                                            id="{{ $filterDrawerId }}-shop-{{ $shopOption->getKey() }}"
                                            class="tf-check"
                                            type="checkbox"
                                            name="shops[]"
                                            value="{{ $shopOption->getKey() }}"
                                            @checked($selectedShopIds->contains($shopOptionId))
                                        >
                                        <label for="{{ $filterDrawerId }}-shop-{{ $shopOption->getKey() }}" class="label">
                                            {{ $shopOption->name }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="storefront-shop-filter-empty" data-shop-option-empty>No matching shops.</div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($categoryFilterOptions->isNotEmpty())
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#{{ $filterDrawerId }}-categories" role="button" data-bs-toggle="collapse"
                        aria-expanded="false" aria-controls="{{ $filterDrawerId }}-categories">
                        <h6>Category</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="{{ $filterDrawerId }}-categories" class="collapse storefront-filter-collapse">
                        <ul class="collapse-body filter-group-check group-category">
                            @foreach ($categoryFilterOptions as $categoryOption)
                                @php $categoryOptionId = (string) $categoryOption->getKey(); @endphp
                                <li class="list-item">
                                    <input
                                        id="{{ $filterDrawerId }}-category-{{ $categoryOption->getKey() }}"
                                        class="tf-check"
                                        type="checkbox"
                                        name="categories[]"
                                        value="{{ $categoryOption->getKey() }}"
                                        @checked($selectedCategoryFilters->contains($categoryOptionId))
                                    >
                                    <label for="{{ $filterDrawerId }}-category-{{ $categoryOption->getKey() }}" class="label">
                                        {{ $categoryOption->name }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="widget-facet">
                <div class="facet-title" data-bs-target="#{{ $filterDrawerId }}-price" role="button" data-bs-toggle="collapse"
                    aria-expanded="false" aria-controls="{{ $filterDrawerId }}-price">
                    <h6>Price</h6>
                    <span class="icon icon-CaretDown"></span>
                </div>
                <div id="{{ $filterDrawerId }}-price" class="collapse storefront-filter-collapse">
                    <div class="collapse-body">
                        <div class="d-flex align-items-center gap-2">
                            <select name="price_min" class="form-select">
                                @foreach ($priceOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($selectedFilters['price_min'] ?? '') === (string) $value)>{!! $label !!}</option>
                                @endforeach
                            </select>
                            <span class="text-caption-01 cl-text-2">to</span>
                            <select name="price_max" class="form-select">
                                @foreach ($maxPriceOptions as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($selectedFilters['price_max'] ?? '') === (string) $value)>{!! $label !!}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="widget-facet">
                <div class="facet-title" data-bs-target="#{{ $filterDrawerId }}-discount" role="button" data-bs-toggle="collapse"
                    aria-expanded="false" aria-controls="{{ $filterDrawerId }}-discount">
                    <h6>Discount</h6>
                    <span class="icon icon-CaretDown"></span>
                </div>
                <div id="{{ $filterDrawerId }}-discount" class="collapse storefront-filter-collapse">
                    <ul class="collapse-body filter-group-check">
                        @foreach ([30, 40, 50, 60, 70] as $discount)
                            <li class="list-item">
                                <input
                                    id="{{ $filterDrawerId }}-discount-{{ $discount }}"
                                    class="tf-check"
                                    type="checkbox"
                                    name="discount_min[]"
                                    value="{{ $discount }}"
                                    @checked($selectedDiscounts->contains((string) $discount))
                                >
                                <label for="{{ $filterDrawerId }}-discount-{{ $discount }}" class="label">
                                    {{ $discount }}% or more
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @foreach ($attributeFilters as $attributeFilter)
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#{{ $filterDrawerId }}-attribute-{{ $attributeFilter->product_attribute_group_id }}"
                        role="button" data-bs-toggle="collapse" aria-expanded="false"
                        aria-controls="{{ $filterDrawerId }}-attribute-{{ $attributeFilter->product_attribute_group_id }}">
                        <h6>{{ $attributeFilter->group->name }}</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="{{ $filterDrawerId }}-attribute-{{ $attributeFilter->product_attribute_group_id }}" class="collapse storefront-filter-collapse">
                        <ul class="collapse-body filter-group-check group-category">
                            @foreach ($attributeFilter->group->values as $attributeValue)
                                @php
                                    $selectedValues = collect($selectedAttributeFilters[$attributeFilter->product_attribute_group_id] ?? [])
                                        ->map(fn ($valueId) => (string) $valueId);
                                @endphp
                                <li class="list-item">
                                    <input
                                        id="{{ $filterDrawerId }}-attribute-{{ $attributeFilter->product_attribute_group_id }}-{{ $attributeValue->getKey() }}"
                                        class="tf-check"
                                        type="checkbox"
                                        name="attributes[{{ $attributeFilter->product_attribute_group_id }}][]"
                                        value="{{ $attributeValue->getKey() }}"
                                        @checked($selectedValues->contains((string) $attributeValue->getKey()))
                                    >
                                    <label
                                        for="{{ $filterDrawerId }}-attribute-{{ $attributeFilter->product_attribute_group_id }}-{{ $attributeValue->getKey() }}"
                                        class="label">
                                        {{ $attributeValue->name }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="canvas-bottom">
            <div class="d-flex gap-2">
                <a href="{{ $resetUrl }}" class="tf-btn btn-stroke animate-btn w-100">Reset</a>
                <button type="submit" class="tf-btn animate-btn w-100">Apply Filters</button>
            </div>
        </div>
    </form>
</div>
