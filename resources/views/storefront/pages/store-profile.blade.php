@extends('storefront.layouts.app')

@php
    $shopName = $shopProfile['name'];
    $heroSlides = collect($heroBanners)->map(function ($banner) {
        return [
            'title' => $banner->title ?: 'Featured at this shop',
            'subtitle' => $banner->subtitle ?: $banner->description,
            'image' => $banner->desktop_image_path ? asset('storage/'.$banner->desktop_image_path) : null,
            'url' => null,
        ];
    })->filter(fn ($slide) => !empty($slide['image']))->values();

    if ($heroSlides->isEmpty()) {
        $heroSlides = collect($products->items())->take(5)->map(fn ($product) => [
            'title' => $product['name'],
            'subtitle' => $product['price'],
            'image' => $product['image'],
            'url' => $product['url'],
        ])->values();
    }

    $coverImage = $shopProfile['cover']
        ? asset($shopProfile['cover'])
        : ($heroSlides->first()['image'] ?? asset('assets/storefront/images/category/cate-1.jpg'));
@endphp

@section('title', $shopName.' | WindowShop')
@section('meta_description', $shopProfile['description'] ?: 'Explore products and shop details from '.$shopName.'.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-profile.css') }}">
@endpush

@section('content')
    <main class="shop-profile-page">
        <div class="container shop-profile-shell">
            <nav class="shop-profile-breadcrumbs" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}">Home</a>
                <span>/</span>
                <a href="{{ route('storefront.stores') }}">Stores</a>
                <span>/</span>
                <span>{{ $shopName }}</span>
            </nav>

            <section class="shop-profile-hero" style="--shop-cover: url('{{ $coverImage }}');">
                <div class="shop-profile-hero-inner">
                    <div class="shop-profile-identity">
                        <div class="shop-profile-logo">
                            @if ($shopProfile['logo'])
                                <img src="{{ asset($shopProfile['logo']) }}" width="84" height="84" alt="{{ $shopName }} logo">
                            @else
                                <span>{{ $shopProfile['initials'] }}</span>
                            @endif
                        </div>

                        

                        <h1 class="shop-profile-title">{{ $shopName }}</h1>

                        @if ($shopProfile['description'])
                            <p class="shop-profile-description">{{ $shopProfile['description'] }}</p>
                        @endif

                        <div class="shop-profile-actions">
                            {{-- <a href="{{ $shopProfile['website_url'] }}" class="shop-profile-action" target="_blank" rel="noopener">
                                Visit Shop Website <i class="icon icon-ArrowUpRight1"></i>
                            </a> --}}
                            {{-- @if ($shopProfile['maps_url'])
                                <a href="{{ $shopProfile['maps_url'] }}" class="shop-profile-action secondary" target="_blank" rel="noopener">
                                    View on Maps <i class="icon icon-MapPin"></i>
                                </a>
                            @endif --}}
                        </div>

                        {{-- <div class="shop-profile-facts">
                            <span class="shop-profile-fact"><i class="icon icon-Tag"></i>{{ $shopProfile['shop_type'] ?: 'General Store' }}</span>
                            @if (!empty($shopProfile['audiences']))
                                <span class="shop-profile-fact"><i class="icon icon-Users"></i>{{ implode(', ', $shopProfile['audiences']) }}</span>
                            @endif
                            <span class="shop-profile-fact"><i class="icon icon-Package"></i>{{ $shopProfile['product_count'] }} products</span>
                            <span class="shop-profile-fact"><i class="icon icon-ShieldCheck"></i>Active Shop</span>
                        </div> --}}
                    </div>

                    <div class="shop-profile-slider">
                        <div dir="ltr" class="swiper tf-swiper" data-preview="1" data-tablet="1" data-mobile="1" data-space="0" data-loop="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}" data-auto="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}" data-delay="4500">
                            <div class="swiper-wrapper">
                                @forelse ($heroSlides as $slide)
                                    <div class="swiper-slide">
                                        @if (!empty($slide['url']))
                                            <a href="{{ $slide['url'] }}" class="shop-profile-slide-card">
                                        @else
                                            <div class="shop-profile-slide-card">
                                        @endif
                                            <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}">
                                            <div class="shop-profile-slide-caption">
                                                {{ $slide['title'] }}
                                                @if (!empty($slide['subtitle']))
                                                    <div class="text-caption-01 fw-normal mt-1">{{ $slide['subtitle'] }}</div>
                                                @endif
                                            </div>
                                        @if (!empty($slide['url']))
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="swiper-slide">
                                        <div class="shop-profile-slide-card">
                                            <img loading="eager" src="{{ $coverImage }}" alt="{{ $shopName }}">
                                            <div class="shop-profile-slide-caption">{{ $shopName }}</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                            @if ($heroSlides->count() > 1)
                                <div class="sw-dot-default tf-sw-pagination"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="shop-profile-meta-band" aria-label="Shop details">
                <div class="shop-profile-meta-item">
                    <div class="shop-profile-meta-label">Shop Type</div>
                    <div class="shop-profile-meta-value">{{ $shopProfile['shop_type'] ?: 'General Store' }}</div>
                </div>
                <div class="shop-profile-meta-item">
                    <div class="shop-profile-meta-label">Audience</div>
                    <div class="shop-profile-meta-value">{{ !empty($shopProfile['audiences']) ? implode(', ', $shopProfile['audiences']) : 'All shoppers' }}</div>
                </div>
                <div class="shop-profile-meta-item">
                    <div class="shop-profile-meta-label">Location</div>
                    <div class="shop-profile-meta-value">
                        @if ($shopProfile['maps_url'] && $shopProfile['address'])
                            <a href="{{ $shopProfile['maps_url'] }}" target="_blank" rel="noopener">{{ $shopProfile['address'] }}</a>
                        @else
                            {{ $shopProfile['address'] ?: 'Address unavailable' }}
                        @endif
                    </div>
                </div>
                <div class="shop-profile-meta-item">
                    <div class="shop-profile-meta-label">Products</div>
                    <div class="shop-profile-meta-value">{{ $shopProfile['product_count'] }} available</div>
                </div>
            </section>

            @if ($middleBanners->isNotEmpty())
                <section class="shop-profile-section">
                    <div class="row g-3">
                        @foreach ($middleBanners as $banner)
                            @if ($banner->desktop_image_path)
                                <div class="col-md-{{ $middleBanners->count() === 1 ? '12' : '6' }}">
                                    <img class="w-100 rounded-2" loading="lazy" src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}">
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="shop-profile-section" id="shop-products">
                @include('storefront.partials.product-listing-controls', [
                    'selectedFilters' => $selectedFilters,
                    'filterDrawerId' => 'filterShop',
                ])

                <div class="shop-profile-section-head">
                    <div>
                        <h2 class="shop-profile-section-title">Products from {{ $shopName }}</h2>
                        <div class="text-caption-01 cl-text-2 mt-1">
                            Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
                        </div>
                    </div>
                </div>

                @if ($products->count() > 0)
                    <div class="shop-profile-product-grid">
                        @foreach ($products as $product)
                            <div class="card-product grid" data-availability="In Stock" data-brand="{{ $product['brand'] ?? '' }}">
                                <div class="card-product_wrapper">
                                    <a href="{{ $product['url'] }}" class="product-img">
                                        <img class="img-product" loading="lazy" width="330" height="440" src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
                                        <img class="img-hover" loading="lazy" width="330" height="440" src="{{ $product['hover_image'] }}" alt="{{ $product['name'] }}">
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
                                            <li class="product-badge_item text-caption-01 {{ $product['badge_class'] }}">{{ $product['badge'] }}</li>
                                        </ul>
                                    @endif
                                    {{-- <div class="product-action_bot">
                                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="tf-btn btn-white small w-100">Add to cart</a>
                                    </div> --}}
                                </div>
                                <div class="card-product_info">
                                    <a href="{{ $product['url'] }}" class="name-product lh-24 fw-medium link-underline-text">{{ $product['name'] }}</a>
                                    <div class="price-wrap">
                                        <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                                        @if ($product['old_price'])
                                            <span class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="shop-profile-pagination">
                        @include('storefront.partials.pagination', ['paginator' => $products])
                    </div>
                @else
                    <div class="shop-profile-empty">
                        No products are currently visible for this shop.
                    </div>
                @endif
            </section>
        </div>
    </main>

    @include('storefront.partials.product-listing-filter-drawer', [
        'selectedFilters' => $selectedFilters,
        'selectedAttributeFilters' => $selectedAttributeFilters,
        'selectedCategoryFilters' => $selectedCategoryFilters,
        'attributeFilters' => $attributeFilters,
        'categoryFilterOptions' => $categoryFilterOptions,
        'showShopFilter' => false,
        'filterDrawerId' => 'filterShop',
        'filterTitle' => 'Filters - '.$shopName,
    ])
@endsection

@push('scripts')
    @include('storefront.partials.product-listing-filter-script')
@endpush
