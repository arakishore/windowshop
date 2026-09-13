@extends('storefront.layouts.app')

@php
    $categoryMetaTitleBase = $category->meta_title ?: $category->name;
    $legacyMarketplaceSuffix = ' | WindowShop';

    if (str_ends_with($categoryMetaTitleBase, $legacyMarketplaceSuffix)) {
        $categoryMetaTitleBase = substr($categoryMetaTitleBase, 0, -strlen($legacyMarketplaceSuffix));
    }

    $categoryMetaTitle = $categoryMetaTitleBase.' | '.$marketplaceName;
    $categoryMetaDescriptionBase = $category->meta_description
        ?: ($category->description ?: 'Browse '.$category->name.' products available from local shops on '.$marketplaceName.'.');

    if (str_ends_with($categoryMetaDescriptionBase, ' on WindowShop.')) {
        $categoryMetaDescriptionBase = substr($categoryMetaDescriptionBase, 0, -strlen(' on WindowShop.')).'.';
    }

    $categoryMetaDescription = rtrim($categoryMetaDescriptionBase, '.').' on '.$marketplaceName.'.';
@endphp

@section('title', $categoryMetaTitle)
@section('meta_description', $categoryMetaDescription)

@push('styles')
    <style>
        .category-listing-header {
            padding-top: 18px;
            margin-bottom: 22px;
            border-top: 1px solid var(--line);
        }

        .category-listing-breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 20px;
            color: var(--primary);
            font-size: 15px;
            line-height: 1.4;
        }

        .category-listing-breadcrumbs a {
            color: var(--primary);
        }

        .category-listing-breadcrumbs span {
            color: var(--main);
        }

        .category-listing-title {
            margin-bottom: 8px;
            font-size: 24px;
            line-height: 1.22;
            font-weight: 700;
        }

        .category-listing-count {
            margin-bottom: 8px;
            color: var(--main);
            font-size: 16px;
            line-height: 1.35;
        }

        .category-listing-note {
            margin-bottom: 0;
            color: var(--text-2, #777);
            font-size: 14px;
            line-height: 1.4;
        }

        .storefront-filter-field {
            margin-bottom: 24px;
        }

        .storefront-filter-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--main);
        }

        .storefront-filter-input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            line-height: 1.4;
        }

        .storefront-shop-filter-list {
            max-height: 220px;
            overflow-y: auto;
        }

        .storefront-shop-filter-empty {
            display: none;
            padding: 8px 0;
            color: var(--text-2, #777);
            font-size: 13px;
        }

    </style>
@endpush

@section('content')
    <div class="flat-spacing pt-0">
        <div class="container">
            <div class="category-listing-header">
                <div class="category-listing-breadcrumbs">
                    <a href="{{ route('storefront.home') }}">Home</a>
                    @foreach ($breadcrumbCategories as $breadcrumbCategory)
                        <span>/</span>
                        @if ($loop->last)
                            <span>{{ $breadcrumbCategory->name }}</span>
                        @else
                            <a href="{{ $storefrontUrls->category($breadcrumbCategory) }}">{{ $breadcrumbCategory->name }}</a>
                        @endif
                    @endforeach
                </div>

                <h1 class="category-listing-title">{{ $category->name }}</h1>
                <p class="category-listing-count">
                    Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} out of {{ $products->total() }} products
                </p>
                <p class="category-listing-note">
                    Welcome to {{ $category->name }} - Discover amazing products and deals!
                </p>
            </div>

            @php
                $sortOptions = [
                    'popularity' => 'Popularity',
                    'new-arrivals' => 'New Arrivals',
                    'top-sellers' => 'Top Sellers',
                    'price-high-low' => 'Price High to Low',
                    'price-low-high' => 'Price Low to High',
                    'discount-high-low' => 'Discount High to Low',
                    'rating-high-low' => 'Rating High To Low',
                ];
                $selectedSort = (string) ($selectedFilters['sort'] ?? 'popularity');
                $selectedSort = array_key_exists($selectedSort, $sortOptions) ? $selectedSort : 'popularity';
                $sortBaseQuery = request()->except(['sort', 'page']);
            @endphp
            <div class="tf-shop-control sticky-top no-offset sticky-top no-offset">
                <a href="#filterShop" data-bs-toggle="offcanvas" class="tf-btn-filter">
                    <span class="icon icon-filter"></span>
                    <span class="text">Filters</span>
                </a>

                <div class="tf-control-sorting">
                    <span class="text-caption-01 cl-text-2 me-2">Sort By</span>
                    <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                        <div class="btn-select">
                            <span class="text-sort-value">{{ $sortOptions[$selectedSort] }}</span>
                            <span class="icon icon-CaretDown"></span>
                        </div>
                        <div class="dropdown-menu">
                            @foreach ($sortOptions as $sortValue => $sortLabel)
                                <a
                                    class="select-item {{ $selectedSort === $sortValue ? 'active' : '' }}"
                                    data-sort-value="{{ $sortValue }}"
                                    data-server-sort-link
                                    href="{{ url()->current().'?'.http_build_query(array_merge($sortBaseQuery, ['sort' => $sortValue])) }}"
                                >
                                    <span class="text-value-item">{{ $sortLabel }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="wrapper-control-shop gridLayout-wrapper">
                <div class="wrapper-shop tf-grid-layout tf-col-2 md-col-3 lg-col-4" id="gridLayout">
                    @forelse ($products as $product)
                        <div class="card-product grid" data-availability="In Stock"
                            data-brand="{{ $product['brand'] ?? '' }}">
                            <div class="card-product_wrapper">
                                <a href="{{ $product['url'] }}" class="product-img">
                                    <img class="img-product" loading="lazy" width="330" height="440"
                                        src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                                    <img class="img-hover" loading="lazy" width="330" height="440"
                                        src="{{ $product['hover_image'] }}" alt="{{ $product['name'] }}">
                                </a>
                                <ul class="product-action_list">
                                    <li class="wishlist">
                                        @include('storefront.components.wishlist-button', ['product' => $product, 'wishlistedProductIds' => $wishlistedProductIds ?? []])
                                    </li>
                                    <li>
                                        <a href="{{ $product['url'] }}" class="hover-tooltip tooltip-left box-icon">
                                            <span class="icon icon-Eye"></span>
                                            <span class="tooltip">View</span>
                                        </a>
                                    </li>
                                </ul>
                                @if ($product['badge'])
                                    <ul class="product-badge_list">
                                        <li class="product-badge_item text-caption-01 {{ $product['badge_class'] }}">
                                            {{ $product['badge'] }}</li>
                                    </ul>
                                @endif
                                @include('storefront.components.product-promotion', ['product' => $product])
                                {{-- <div class="product-action_bot">
                                    <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                        class="tf-btn btn-white small w-100">
                                        Add to cart2
                                    </a>
                                </div> --}}
                            </div>
                            <div class="card-product_info">
                                <a href="{{ $product['url'] }}"
                                    class="name-product lh-24 fw-medium link-underline-text">{{ $product['name'] }}</a>
                                @if ($product['show_rating'])
                                    <div class="star-wrap d-flex align-items-center"></div>
                                @endif
                                <div class="price-wrap">
                                    <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                                    @if ($product['old_price'])
                                        <span class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                                    @endif
                                </div>
                                @include('storefront.components.product-promotion-text', ['product' => $product])
                            </div>
                        </div>
                    @empty
                        <div class="wd-full text-center py-5">
                            <p class="h5 mb-0">No products found.</p>
                        </div>
                    @endforelse

                    @include('storefront.partials.pagination', ['paginator' => $products])
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-start canvas-filter" id="filterShop">
        <form class="canvas-wrapper" method="GET" action="{{ url()->current() }}">
            <input type="hidden" name="sort" value="{{ $selectedSort }}">
            @php
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
                $selectedDiscounts = collect($selectedFilters['discount_min'] ?? [])->map(fn ($value) => (string) $value);
                $selectedShopIds = collect($selectedFilters['shops'] ?? [])->map(fn ($value) => (string) $value);
                $productSearch = (string) ($selectedFilters['search'] ?? '');
            @endphp
            <div class="canvas-header">
                <div class="h5 title">Filters - {{ $category->parent?->name ?? $category->name }}</div>
                <span class="icon-X2 fs-24 link icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
            <div class="canvas-body">
                <div class="d-flex gap-3 mb-16">
                    <button type="button" class="link text-caption-01 fw-semibold storefront-filter-expand-all">Expand all</button>
                    <button type="button" class="link text-caption-01 fw-semibold storefront-filter-collapse-all">Collapse all</button>
                </div>
                <div class="storefront-filter-field">
                    <label for="product-filter-search" class="storefront-filter-label">Search Products</label>
                    <input
                        id="product-filter-search"
                        class="storefront-filter-input"
                        type="search"
                        name="search"
                        value="{{ $productSearch }}"
                        placeholder="Search by product name"
                        autocomplete="off"
                    >
                </div>
                @if ($shopFilterOptions->isNotEmpty())
                    <div class="widget-facet">
                        <div class="facet-title" data-bs-target="#filter-shop-options" role="button" data-bs-toggle="collapse"
                            aria-expanded="true" aria-controls="filter-shop-options">
                            <h6>Shop</h6>
                            <span class="icon icon-CaretDown"></span>
                        </div>
                        <div id="filter-shop-options" class="collapse show storefront-filter-collapse">
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
                                        @php
                                            $shopOptionId = (string) $shopOption->getKey();
                                        @endphp
                                        <li class="list-item" data-shop-option data-shop-name="{{ \Illuminate\Support\Str::lower($shopOption->name) }}">
                                            <input
                                                id="shop-filter-{{ $shopOption->getKey() }}"
                                                class="tf-check"
                                                type="checkbox"
                                                name="shops[]"
                                                value="{{ $shopOption->getKey() }}"
                                                @checked($selectedShopIds->contains($shopOptionId))
                                            >
                                            <label for="shop-filter-{{ $shopOption->getKey() }}" class="label">
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
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-category" role="button" data-bs-toggle="collapse"
                        aria-expanded="false" aria-controls="filter-category">
                        <h6>Category</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-category" class="collapse storefront-filter-collapse">
                        <ul class="collapse-body filter-group-check group-category">
                            <li class="list-item">
                                <a href="{{ $storefrontUrls->category($category) }}" class="filter-check">
                                    {{ $category->name }}
                                </a>
                            </li>
                            @foreach ($childCategories as $childCategory)
                                <li class="list-item">
                                    <a href="{{ $storefrontUrls->category($childCategory) }}" class="filter-check">
                                        {{ $childCategory->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-price" role="button" data-bs-toggle="collapse"
                        aria-expanded="false" aria-controls="filter-price">
                        <h6>Price</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-price" class="collapse storefront-filter-collapse">
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
                    <div class="facet-title" data-bs-target="#filter-discount" role="button" data-bs-toggle="collapse"
                        aria-expanded="false" aria-controls="filter-discount">
                        <h6>Discount</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-discount" class="collapse storefront-filter-collapse">
                        <ul class="collapse-body filter-group-check">
                            @foreach ([30, 40, 50, 60, 70] as $discount)
                                <li class="list-item">
                                    <input
                                        id="discount-filter-{{ $discount }}"
                                        class="tf-check"
                                        type="checkbox"
                                        name="discount_min[]"
                                        value="{{ $discount }}"
                                        @checked($selectedDiscounts->contains((string) $discount))
                                    >
                                    <label for="discount-filter-{{ $discount }}" class="label">
                                        {{ $discount }}% or more
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @foreach ($attributeFilters as $attributeFilter)
                    <div class="widget-facet">
                        <div class="facet-title" data-bs-target="#filter-attribute-{{ $attributeFilter->product_attribute_group_id }}"
                            role="button" data-bs-toggle="collapse" aria-expanded="false"
                            aria-controls="filter-attribute-{{ $attributeFilter->product_attribute_group_id }}">
                            <h6>{{ $attributeFilter->group->name }}</h6>
                            <span class="icon icon-CaretDown"></span>
                        </div>
                        <div id="filter-attribute-{{ $attributeFilter->product_attribute_group_id }}" class="collapse storefront-filter-collapse">
                            <ul class="collapse-body filter-group-check group-category">
                                @foreach ($attributeFilter->group->values as $attributeValue)
                                    @php
                                        $selectedValues = collect($selectedAttributeFilters[$attributeFilter->product_attribute_group_id] ?? [])
                                            ->map(fn ($valueId) => (string) $valueId);
                                    @endphp
                                    <li class="list-item">
                                        <input
                                            id="attribute-filter-{{ $attributeFilter->product_attribute_group_id }}-{{ $attributeValue->getKey() }}"
                                            class="tf-check"
                                            type="checkbox"
                                            name="attributes[{{ $attributeFilter->product_attribute_group_id }}][]"
                                            value="{{ $attributeValue->getKey() }}"
                                            @checked($selectedValues->contains((string) $attributeValue->getKey()))
                                        >
                                        <label
                                            for="attribute-filter-{{ $attributeFilter->product_attribute_group_id }}-{{ $attributeValue->getKey() }}"
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
                    <a href="{{ url()->current() }}" class="tf-btn btn-stroke animate-btn w-100">Reset</a>
                    <button type="submit" class="tf-btn animate-btn w-100">Apply Filters</button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-server-sort-link]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.location.assign(link.href);
                }, true);
            });

            const filterDrawer = document.getElementById('filterShop');

            if (!filterDrawer || typeof bootstrap === 'undefined') {
                return;
            }

            filterDrawer.querySelector('.storefront-filter-expand-all')?.addEventListener('click', () => {
                filterDrawer.querySelectorAll('.storefront-filter-collapse').forEach((element) => {
                    bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).show();
                });
            });

            filterDrawer.querySelector('.storefront-filter-collapse-all')?.addEventListener('click', () => {
                filterDrawer.querySelectorAll('.storefront-filter-collapse').forEach((element) => {
                    bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).hide();
                });
            });

            const shopSearch = filterDrawer.querySelector('[data-shop-option-search]');

            shopSearch?.addEventListener('input', () => {
                const term = shopSearch.value.trim().toLowerCase();
                let visibleCount = 0;

                filterDrawer.querySelectorAll('[data-shop-option]').forEach((option) => {
                    const isVisible = (option.dataset.shopName || '').includes(term);

                    option.style.display = isVisible ? '' : 'none';
                    visibleCount += isVisible ? 1 : 0;
                });

                const emptyState = filterDrawer.querySelector('[data-shop-option-empty]');

                if (emptyState) {
                    emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            });
        });
    </script>
@endpush
