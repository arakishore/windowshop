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
    <style>
        .shop-profile-page {
            /* background: #f7f8fb; */
        }

        .shop-profile-shell {
            padding: 22px 0 46px;
        }

        .shop-profile-breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 18px;
            color: #607086;
            font-size: 13px;
        }

        .shop-profile-breadcrumbs a {
            color: #345174;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .shop-profile-hero {
            position: relative;
            overflow: hidden;
            min-height: 520px;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            isolation: isolate;
        }

        .shop-profile-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: var(--shop-cover);
            background-size: cover;
            background-position: center;
            transform: scale(1.02);
        }

        .shop-profile-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: rgba(7, 12, 20, .62);
        }

        .shop-profile-hero-inner {
            display: grid;
            grid-template-columns: minmax(0, .92fr) minmax(320px, .78fr);
            gap: 44px;
            align-items: center;
            min-height: 520px;
            padding: 58px 76px;
        }

        .shop-profile-identity {
            max-width: 620px;
        }

        .shop-profile-logo {
            width: 84px;
            height: 84px;
            border-radius: 18px;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            background: #fff;
            color: #6d28d9;
            font-weight: 800;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
        }

        .shop-profile-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .shop-profile-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .shop-profile-title {
            max-width: 760px;
            margin: 18px 0 16px;
            color: #fff;
            font-size: clamp(36px, 5vw, 68px);
            line-height: 1;
            font-weight: 800;
        }

        .shop-profile-description {
            max-width: 680px;
            margin: 0 0 24px;
            color: rgba(255, 255, 255, .88);
            font-size: 17px;
            line-height: 1.65;
        }

        .shop-profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 26px;
        }

        .shop-profile-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 48px;
            padding: 0 22px;
            border-radius: 999px;
            background: #fff;
            color: #111827;
            font-weight: 700;
        }

        .shop-profile-action.secondary {
            border: 1px solid rgba(255, 255, 255, .35);
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .shop-profile-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 18px 26px;
            color: rgba(255, 255, 255, .9);
            font-size: 14px;
        }

        .shop-profile-fact {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .shop-profile-slider {
            position: relative;
            min-width: 0;
        }

        .shop-profile-slide-card {
            display: block;
            position: relative;
            overflow: hidden;
            min-height: 356px;
            border: 1px solid rgba(255, 255, 255, .65);
            border-radius: 18px;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            box-shadow: 0 28px 72px rgba(0, 0, 0, .35);
        }

        .shop-profile-slide-card img {
            width: 100%;
            height: 430px;
            object-fit: cover;
        }

        .shop-profile-slide-caption {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 34px 18px 16px;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0), rgba(0, 0, 0, .62));
            color: #fff;
            font-weight: 700;
        }

        .shop-profile-meta-band {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
            margin-top: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #e5e7eb;
        }

        .shop-profile-meta-item {
            min-height: 92px;
            padding: 18px;
            background: #fff;
        }

        .shop-profile-meta-label {
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .shop-profile-meta-value {
            color: #111827;
            font-weight: 700;
            line-height: 1.35;
        }

        .shop-profile-section {
            margin-top: 38px;
        }

        .shop-profile-section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }

        .shop-profile-section-title {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
            font-weight: 800;
        }

        .shop-profile-product-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 28px 18px;
        }

        .shop-profile-empty {
            padding: 42px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            text-align: center;
            color: #6b7280;
        }

        .shop-profile-pagination {
            margin-top: 28px;
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

        @media (max-width: 1199px) {
            .shop-profile-hero-inner {
                grid-template-columns: 1fr;
                padding: 44px;
            }

            .shop-profile-product-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .shop-profile-shell {
                padding-top: 14px;
            }

            .shop-profile-hero,
            .shop-profile-hero-inner {
                min-height: auto;
            }

            .shop-profile-hero-inner {
                padding: 28px 18px;
                gap: 28px;
            }

            .shop-profile-title {
                font-size: 38px;
            }

            .shop-profile-description {
                font-size: 15px;
            }

            .shop-profile-slide-card {
                min-height: 260px;
            }

            .shop-profile-slide-card img {
                height: 300px;
            }

            .shop-profile-meta-band {
                grid-template-columns: 1fr;
            }

            .shop-profile-section-head {
                align-items: start;
                flex-direction: column;
            }

            .shop-profile-product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 22px 12px;
            }
        }
    </style>
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
