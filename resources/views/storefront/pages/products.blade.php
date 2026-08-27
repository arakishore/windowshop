@extends('storefront.layouts.app')

@section('title', 'Products | WindowShop')
@section('meta_description', 'Browse products available from local shops on WindowShop.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">

                <h1>Products</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Products</p>
                </div>
            </div>
        </div>
    </section>

    <div class="flat-spacing">
        <div class="container">
            <div class="tf-shop-control sticky-top no-offset sticky-top no-offset">
                <a href="#filterShop" data-bs-toggle="offcanvas" class="tf-btn-filter">
                    <span class="icon icon-filter"></span>
                    <span class="text">Show Filters</span>
                </a>

                <div class="tf-control-sorting">
                    <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                        <div class="btn-select">
                            <span class="text-sort-value">Best Selling</span>
                            <span class="icon icon-CaretDown"></span>
                        </div>
                        <div class="dropdown-menu">
                            <div class="select-item active remove-all-filters" data-sort-value="best-selling">
                                <span class="text-value-item">Best Selling</span>
                            </div>
                            <div class="select-item" data-sort-value="a-z">
                                <span class="text-value-item">Alphabetically, A-Z</span>
                            </div>
                            <div class="select-item" data-sort-value="z-a">
                                <span class="text-value-item">Alphabetically, Z-A</span>
                            </div>
                            <div class="select-item" data-sort-value="price-low-high">
                                <span class="text-value-item">Price, low to high</span>
                            </div>
                            <div class="select-item" data-sort-value="price-high-low">
                                <span class="text-value-item">Price, high to low</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wrapper-control-shop gridLayout-wrapper">
                <div class="meta-filter-shop">
                    <div id="product-count-list" class="count-text text-caption-01"></div>
                    <div id="product-count-grid" class="count-text text-caption-01"></div>
                    <div class="br-line type-vertical"></div>
                    <div id="applied-filters"></div>
                    <button id="remove-all" class="remove-all-filters" style="display: none;">
                        <i class="icon icon-X2"></i>
                        Clear all
                    </button>
                </div>

                <div class="tf-list-layout wrapper-shop" id="listLayout" style="display: none;">
                    @forelse ($products as $product)
                        <div class="card-product product-style_list" data-availability="In Stock"
                            data-brand="{{ $product['brand'] ?? '' }}">
                            <div class="card-product_wrapper">
                                <a href="{{ $product['url'] }}" class="product-img">
                                    <img class="img-product" loading="lazy" width="330" height="440"
                                        src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                                    <img class="img-hover" loading="lazy" width="330" height="440"
                                        src="{{ $product['hover_image'] }}" alt="{{ $product['name'] }}">
                                </a>
                                @if ($product['badge'])
                                    <ul class="product-badge_list">
                                        <li class="product-badge_item text-caption-01 {{ $product['badge_class'] }}">
                                            {{ $product['badge'] }}</li>
                                    </ul>
                                @endif
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
                                <p class="description text-caption-01 mb-10">
                                    {{ $product['description'] }}
                                </p>
                                @if (! empty($product['swatches']))
                                    <ul class="product-color_list">
                                        @foreach ($product['swatches'] as $swatch)
                                            <li class="product-color-item color-swatch hover-tooltip tooltip-bot {{ $loop->first ? 'active' : '' }}">
                                                <span class="tooltip color-filter">{{ $swatch['label'] }}</span>
                                                <span class="swatch-value {{ $swatch['class'] }}"></span>
                                                <img src="{{ $swatch['image'] }}" data-src="{{ $swatch['image'] }}" alt="{{ $swatch['label'] }}">
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <ul class="product-action_list">
                                    <li>
                                        <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                            class="hover-tooltip box-icon">
                                            <span class="icon icon-Handbag"></span>
                                            <span class="tooltip">Add to Cart</span>
                                        </a>
                                    </li>
                                    <li class="wishlist">
                                        @include('storefront.components.wishlist-button', [
                                            'product' => $product,
                                            'wishlistedProductIds' => $wishlistedProductIds ?? [],
                                            'tooltipClass' => '',
                                        ])
                                    </li>
                                    <li>
                                        <a href="#;" class="hover-tooltip box-icon">
                                            <span class="icon icon-Eye"></span>
                                            <span class="tooltip">Quick view</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    @empty
                        <div class="wd-full text-center py-5">
                            <p class="h5 mb-0">No products found.</p>
                        </div>
                    @endforelse
                    @include('storefront.partials.pagination', ['paginator' => $products])
                </div>

                <div class="wrapper-shop tf-grid-layout tf-col-4" id="gridLayout">
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
                                @if (! empty($product['swatches']))
                                    <ul class="product-color_list">
                                        @foreach ($product['swatches'] as $swatch)
                                            <li class="product-color-item color-swatch hover-tooltip tooltip-bot {{ $loop->first ? 'active' : '' }}">
                                                <span class="tooltip color-filter">{{ $swatch['label'] }}</span>
                                                <span class="swatch-value {{ $swatch['class'] }}"></span>
                                                <img src="{{ $swatch['image'] }}" data-src="{{ $swatch['image'] }}" alt="{{ $swatch['label'] }}">
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
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
        <div class="canvas-wrapper">
            <div class="canvas-header">
                <div class="h5 title">Filters</div>
                <span class="icon-X2 fs-24 link icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
            <div class="canvas-body">
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-category" role="button" data-bs-toggle="collapse"
                        aria-expanded="true" aria-controls="filter-category">
                        <h6>Product Categories</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-category" class="collapse show">
                        <ul class="collapse-body filter-group-check group-category">
                            <li class="list-item"><a href="#;" class="filter-check">Tops & Shirts</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Dresses</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Footwear</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Accessories</a></li>
                        </ul>
                    </div>
                </div>
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-availability" role="button"
                        data-bs-toggle="collapse" aria-expanded="true" aria-controls="filter-availability">
                        <h6>Availability</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-availability" class="collapse show">
                        <ul class="collapse-body filter-group-check">
                            <li class="list-item"><a href="#;" class="filter-check">In Stock</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Out of Stock</a></li>
                        </ul>
                    </div>
                </div>
                <div class="widget-facet">
                    <div class="facet-title" data-bs-target="#filter-brand" role="button" data-bs-toggle="collapse"
                        aria-expanded="true" aria-controls="filter-brand">
                        <h6>Brand</h6>
                        <span class="icon icon-CaretDown"></span>
                    </div>
                    <div id="filter-brand" class="collapse show">
                        <ul class="collapse-body filter-group-check">
                            <li class="list-item"><a href="#;" class="filter-check">Louis Vuitton</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Nike</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Adidas</a></li>
                            <li class="list-item"><a href="#;" class="filter-check">Gucci</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="canvas-bottom">
                <button type="button" class="tf-btn animate-btn w-100" data-bs-dismiss="offcanvas">Apply
                    Filters</button>
            </div>
        </div>
    </div>
@endsection
