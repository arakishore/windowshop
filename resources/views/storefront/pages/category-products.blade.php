@extends('storefront.layouts.app')

@php
    $marketplaceName = app(\App\Services\System\SystemSettingService::class)->marketplaceName();
    $categoryMetaTitleBase = $category->meta_title ?: $category->name;
    $legacyMarketplaceSuffix = ' | WindowShop';

    if (str_ends_with($categoryMetaTitleBase, $legacyMarketplaceSuffix)) {
        $categoryMetaTitleBase = substr($categoryMetaTitleBase, 0, -strlen($legacyMarketplaceSuffix));
    }

    $categoryMetaTitle = $categoryMetaTitleBase.' | '.$marketplaceName;
    $categoryMetaDescriptionBase = $category->meta_description
        ?: ($category->description ?: 'Browse '.$category->name.' products available from local shops on WindowShop.');

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
                            <a href="{{ $breadcrumbCategory->parent ? route('storefront.category.child.show', [$breadcrumbCategory->parent->slug, $breadcrumbCategory->slug]) : route('storefront.category.show', $breadcrumbCategory->slug) }}">{{ $breadcrumbCategory->name }}</a>
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

            <div class="tf-shop-control sticky-top no-offset sticky-top no-offset">
                <a href="#filterShop" data-bs-toggle="offcanvas" class="tf-btn-filter">
                    <span class="icon icon-filter"></span>
                    <span class="text">Filters</span>
                </a>

                <div class="tf-control-sorting">
                    <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                        <div class="btn-select">
                            <span class="text-sort-value">Sort</span>
                            <span class="icon icon-CaretDown"></span>
                        </div>
                        <div class="dropdown-menu">
                            <div class="select-item active" data-sort-value="popularity">
                                <span class="text-value-item">Popularity</span>
                            </div>
                            <div class="select-item" data-sort-value="new-arrivals">
                                <span class="text-value-item">New Arrivals</span>
                            </div>
                            <div class="select-item" data-sort-value="top-sellers">
                                <span class="text-value-item">Top Sellers</span>
                            </div>
                            <div class="select-item" data-sort-value="price-high-low">
                                <span class="text-value-item">Price High to Low</span>
                            </div>
                            <div class="select-item" data-sort-value="price-low-high">
                                <span class="text-value-item">Price Low to High</span>
                            </div>
                            <div class="select-item" data-sort-value="discount-high-low">
                                <span class="text-value-item">Discount High to Low</span>
                            </div>
                            <div class="select-item" data-sort-value="rating-high-low">
                                <span class="text-value-item">Rating High To Low</span>
                            </div>
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
                                        <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                            <span class="icon icon-heart"></span>
                                            <span class="tooltip">Add to Wishlist</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                            <span class="icon icon-Eye"></span>
                                            <span class="tooltip">Quick view</span>
                                        </a>
                                    </li>
                                </ul>
                                @if ($product['badge'])
                                    <ul class="product-badge_list">
                                        <li class="product-badge_item text-caption-01 {{ $product['badge_class'] }}">
                                            {{ $product['badge'] }}</li>
                                    </ul>
                                @endif
                                <div class="product-action_bot">
                                    <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                        class="tf-btn btn-white small w-100">
                                        Add to cart
                                    </a>
                                </div>
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
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-category" role="button" data-bs-toggle="collapse"
                        aria-expanded="false" aria-controls="filter-category">
                        <h6>Category</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-category" class="collapse storefront-filter-collapse">
                        <ul class="collapse-body filter-group-check group-category">
                            <li class="list-item">
                                <a href="{{ $category->parent ? route('storefront.category.child.show', [$category->parent->slug, $category->slug]) : route('storefront.category.show', $category->slug) }}" class="filter-check">
                                    {{ $category->name }}
                                </a>
                            </li>
                            @foreach ($childCategories as $childCategory)
                                <li class="list-item">
                                    <a href="{{ route('storefront.category.child.show', [$childCategory->parent->slug, $childCategory->slug]) }}" class="filter-check">
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
        });
    </script>
@endpush
