@extends('storefront.layouts.app')

@php
    $shopName = $shopProfile['name'];
    $heroSlides = collect($heroBanners)
        ->map(function ($banner) {
            return [
                'title' => $banner->title ?: 'Featured at this shop',
                'subtitle' => $banner->subtitle ?: $banner->description,
                'image' => $banner->desktop_image_path ? asset('storage/' . $banner->desktop_image_path) : null,
                'url' => null,
            ];
        })
        ->filter(fn($slide) => !empty($slide['image']))
        ->values();

    if ($heroSlides->isEmpty()) {
        $heroSlides = collect($products->items())
            ->take(5)
            ->map(
                fn($product) => [
                    'title' => $product['name'],
                    'subtitle' => $product['price'],
                    'image' => $product['image'],
                    'url' => $product['url'],
                ],
            )
            ->values();
    }

    $coverImage = $shopProfile['cover']
        ? asset($shopProfile['cover'])
        : $heroSlides->first()['image'] ?? asset('assets/storefront/images/category/cate-1.jpg');
@endphp

@section('title', $shopName . ' | ' . $marketplaceName)
@section('meta_description', $shopProfile['description'] ?: 'Explore products and shop details from ' . $shopName . '.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-profile.css') }}">
    <style>
  .ws-meta-band{ background:#fff; border:1px solid #EDEEF2; border-radius:16px; padding:12px; display:flex; align-items:center; justify-content:space-between; gap:16px; box-shadow:0 1px 2px rgba(17,24,39,.04); }
  .ws-meta-actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .ws-meta-btn{ display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 18px; border-radius:999px; font-size:13.5px; font-weight:600; text-decoration:none; border:1px solid transparent; transition:all .15s; white-space:nowrap; line-height:1; }
  .ws-meta-btn--wa{ background:#25D366; color:#fff; border-color:#25D366; box-shadow:0 4px 12px rgba(37,211,102,.28); }
  .ws-meta-btn--wa:hover{ background:#1FB356; color:#fff; transform:translateY(-1px); box-shadow:0 6px 16px rgba(37,211,102,.32); }
  .ws-meta-btn--ask{ background:#fff; color:#4C3F6D; border-color:#DDD6FE; }
  .ws-meta-btn--ask:hover{ background:#F5F3FF; color:#4C3F6D; border-color:#C4B5FD; }
  .ws-meta-btn svg{ flex-shrink:0; }
  .ws-meta-div{ width:1px; height:28px; background:#EDEEF2; flex-shrink:0; }
  .ws-meta-info{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; justify-content:flex-end; min-width:0; }
  .ws-meta-loc{ display:flex; align-items:center; gap:10px; min-width:0; max-width:420px; text-align:left; }
  .ws-meta-loc-icon{ width:36px; height:36px; border-radius:999px; background:#F9FAFB; border:1px solid #F3F4F6; display:flex; align-items:center; justify-content:center; color:#6B7280; flex-shrink:0; }
  .ws-meta-loc-text{ min-width:0; }
  .ws-meta-loc-label{ font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; color:#9AA0AE; font-weight:700; line-height:1; margin-bottom:3px; }
  .ws-meta-loc-value{ font-size:13px; font-weight:500; color:#1F2430; line-height:1.35; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
  .ws-meta-loc-value a{ color:#1F2430; text-decoration:none; border-bottom:1px dashed #D1D5DB; text-underline-offset:3px; }
  .ws-meta-loc-value a:hover{ color:#4C3F6D; border-bottom-color:#4C3F6D; }
  .ws-meta-pill{ display:inline-flex; align-items:center; gap:6px; background:#F5F3FF; border:1px solid #DDD6FE; color:#4C3F6D; font-size:12.5px; font-weight:700; padding:7px 12px; border-radius:999px; white-space:nowrap; }
  .ws-meta-pill b{ color:#111; }
  .ws-meta-share{ display:inline-flex; align-items:center; gap:7px; height:36px; padding:0 14px; border-radius:999px; background:#fff; border:1px solid #E5E7EB; color:#374151; font-size:13px; font-weight:600; text-decoration:none; transition:all .15s; white-space:nowrap; }
  .ws-meta-share:hover{ background:#F9FAFB; color:#111; border-color:#D1D5DB; }
  .ws-meta-band:focus-within{ outline:2px solid #DDD6FE; outline-offset:2px; }
  @media(max-width:992px){
    .ws-meta-band{ flex-direction:column; align-items:stretch; }
    .ws-meta-div{ display:none; }
    .ws-meta-info{ justify-content:space-between; border-top:1px solid #F3F4F6; padding-top:12px; }
    .ws-meta-loc{ max-width:none; flex:1; }
  }
  @media(max-width:576px){
    .ws-meta-actions{ display:grid; grid-template-columns:1fr 1fr; }
    .ws-meta-btn{ justify-content:center; padding:0 12px; }
    .ws-meta-info{ gap:10px; }
    .ws-meta-loc-value{ -webkit-line-clamp:2; }
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
                                <img src="{{ asset($shopProfile['logo']) }}" width="84" height="84"
                                    alt="{{ $shopName }} logo">
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
                        <div class="shop-profile-facts">
                            <span class="shop-profile-fact"><i
                                    class="icon icon-Tag"></i>{{ $shopProfile['shop_type'] ?: 'General Store' }}</span>
                            @if (!empty($shopProfile['audiences']))
                                <span class="shop-profile-fact"><i
                                        class="icon icon-Users"></i>{{ implode(', ', $shopProfile['audiences']) }}</span>
                            @endif
                            <span class="shop-profile-fact"><i
                                    class="icon icon-Package"></i>{{ $shopProfile['product_count'] }} products</span>
                            <span class="shop-profile-fact"><i class="icon icon-ShieldCheck"></i>Active Shop</span>
                        </div>
                    </div>

                    <div class="shop-profile-slider">
                        <div dir="ltr" class="swiper tf-swiper" data-preview="1" data-tablet="1" data-mobile="1"
                            data-space="0" data-loop="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}"
                            data-auto="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}" data-delay="4500">
                            <div class="swiper-wrapper">
                                @forelse ($heroSlides as $slide)
                                    <div class="swiper-slide">
                                        @if (!empty($slide['url']))
                                            <a href="{{ $slide['url'] }}" class="shop-profile-slide-card">
                                            @else
                                                <div class="shop-profile-slide-card">
                                        @endif
                                        <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" src="{{ $slide['image'] }}"
                                            alt="{{ $slide['title'] }}">
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

        @if ($middleBanners->isNotEmpty())
            <section class="shop-profile-section">
                <div class="row g-3">
                    @foreach ($middleBanners as $banner)
                        @if ($banner->desktop_image_path)
                            <div class="col-md-{{ $middleBanners->count() === 1 ? '12' : '6' }}">
                                <img class="w-100 rounded-2" loading="lazy"
                                    src="{{ asset('storage/' . $banner->desktop_image_path) }}"
                                    alt="{{ $banner->title }}">
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if (($shopPromotions ?? collect())->isNotEmpty())
            @php
                $initialOfferCount = 4;
                $hiddenOfferCount = max(0, $shopPromotions->count() - $initialOfferCount);
            @endphp
            <section class="shop-profile-section shop-profile-offers" aria-labelledby="shop-profile-offers-title" data-shop-offers>
                <div class="shop-profile-section-head">
                    <div>
                        <h2 class="shop-profile-section-title" id="shop-profile-offers-title">Offers from {{ $shopName }}</h2>
                        <div class="text-caption-01 cl-text-2 mt-1">Current deals and promotions available from this shop.</div>
                    </div>
                </div>

                <div class="shop-profile-offer-grid">
                    @foreach ($shopPromotions as $offer)
                        <article class="shop-profile-offer-card {{ $loop->iteration > $initialOfferCount ? 'shop-profile-offer-extra' : '' }}"
                            @if ($loop->iteration > $initialOfferCount) hidden data-shop-offer-extra @endif>
                            <div class="shop-profile-offer-label">
                                @if (!empty($offer['icon']))
                                    <i class="icon {{ $offer['icon'] }}" aria-hidden="true"></i>
                                @endif
                                {{ $offer['label'] }}
                            </div>

                            <h3 class="shop-profile-offer-title">{{ $offer['name'] }}</h3>

                            @if (!empty($offer['description']))
                                <p class="shop-profile-offer-desc">{{ $offer['description'] }}</p>
                            @endif

                            <div class="shop-profile-offer-meta">
                                <span>{{ $offer['scope'] }}</span>
                                @if (!empty($offer['ends_at']))
                                    <span>Ends {{ $offer['ends_at']->format('d M') }}</span>
                                @endif
                            </div>

                            @if (!empty($offer['products_url']))
                                <a href="{{ $offer['products_url'] }}" class="shop-profile-offer-link">
                                    View Products <i class="icon icon-CaretRightThin"></i>
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if ($hiddenOfferCount > 0)
                    <div class="shop-profile-offer-toggle-wrap">
                        <button type="button"
                            class="shop-profile-offer-toggle"
                            data-shop-offers-toggle
                            aria-expanded="false">
                            <span data-shop-offers-toggle-label>Show all offers ({{ $hiddenOfferCount }})</span>
                            <i class="icon icon-CaretDown" aria-hidden="true"></i>
                        </button>
                    </div>
                @endif
            </section>
        @endif

        @if (($offerProducts ?? collect())->isNotEmpty())
            <section class="shop-profile-section" id="shop-offer-products">
                <div class="shop-profile-section-head">
                    <div class="shop-section-heading">
                        <div class="shop-section-heading-icon">
                            <i class="icon icon-Gift"></i>
                        </div>
                        <div>
                            <h2 class="shop-profile-section-title">Products on Offer</h2>
                            <div class="text-caption-01 cl-text-2 mt-1">Current offers from {{ $shopName }}</div>
                        </div>
                    </div>
                    @if (($offerProductsTotal ?? 0) > $offerProducts->count())
                        <a href="{{ $offerProductsUrl }}" class="shop-profile-section-link">
                            View All Offer Products <i class="icon icon-CaretRightThin"></i>
                        </a>
                    @endif
                </div>

                <div class="shop-profile-product-grid">
                    @foreach ($offerProducts as $product)
                        @include('storefront.components.product-card', [
                            'product' => $product,
                            'wishlistedProductIds' => $wishlistedProductIds ?? [],
                            'wrapSlide' => false,
                        ])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="shop-profile-section" id="shop-products">
            <div class="shop-profile-section-head">
                <div class="shop-section-heading">
                    <div class="shop-section-heading-icon">
                        <i class="icon icon-Package"></i>
                    </div>
                    <div>
                        <h2 class="shop-profile-section-title">Products from {{ $shopName }}</h2>
                        <div class="text-caption-01 cl-text-2 mt-1">
                            Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of
                            {{ $products->total() }} products
                        </div>
                    </div>
                </div>
                @include('storefront.partials.product-listing-controls', [
                    'selectedFilters' => $selectedFilters,
                    'filterDrawerId' => 'filterShop',
                    'sticky' => false,
                    'controlsClass' => 'shop-section-controls',
                ])
            </div>

            @if ($products->count() > 0)
                <div class="shop-profile-product-grid">
                    @foreach ($products as $product)
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
                                        @include('storefront.components.wishlist-button', [
                                            'product' => $product,
                                            'wishlistedProductIds' => $wishlistedProductIds ?? [],
                                        ])
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
                                @include('storefront.components.product-promotion', [
                                    'product' => $product,
                                ])
                                {{-- <div class="product-action_bot">
                                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="tf-btn btn-white small w-100">Add to cart</a>
                                    </div> --}}
                            </div>
                            <div class="card-product_info">
                                <a href="{{ $product['url'] }}"
                                    class="name-product lh-24 fw-medium link-underline-text">{{ $product['name'] }}</a>
                                <div class="price-wrap">
                                    <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                                    @if ($product['old_price'])
                                        <span
                                            class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                                    @endif
                                </div>
                                @include('storefront.components.product-promotion-text', [
                                    'product' => $product,
                                ])
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

        @if (($similarShops ?? collect())->isNotEmpty())
            <section class="shop-profile-section" id="similar-shops">
                <div class="shop-profile-section-head">
                    <div class="shop-section-heading">
                        <div class="shop-section-heading-icon">
                            <i class="icon icon-Tag"></i>
                        </div>
                        <div>
                            <h2 class="shop-profile-section-title">Similar Shops</h2>
                            <div class="text-caption-01 cl-text-2 mt-1">
                                More shops like {{ $shopName }}.
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('storefront.stores') }}" class="shop-profile-section-link">
                        View All Shops <i class="icon icon-CaretRightThin"></i>
                    </a>
                </div>

                <div class="shop-profile-similar-grid">
                    @foreach ($similarShops as $store)
                        @include('storefront.components.shop-card', ['store' => $store])
                    @endforeach
                </div>
            </section>
        @endif

        @if (!empty($shopLocation['address']) || !empty($shopLocation['directions_url']))
            <section class="shop-profile-section shop-location-section" id="shop-location">
                <div class="shop-location-heading">
                    <div class="shop-location-icon">
                        <i class="icon icon-MapPin"></i>
                    </div>
                    <div>
                        <h2 class="shop-profile-section-title">Shop Location</h2>
                        <div class="text-caption-01 cl-text-2 mt-1">Visit our shop or get directions</div>
                    </div>
                </div>

                <div class="shop-location-card">
                    <div class="shop-location-details">
                        <h3 class="shop-location-name">{{ $shopName }}</h3>

                        @if (!empty($shopLocation['address']))
                            <div class="shop-location-address">
                                <i class="icon icon-MapPin"></i>
                                <span>{{ $shopLocation['address'] }}</span>
                            </div>
                        @endif

                        <div class="shop-location-facts">
                            @if (!empty($shopLocation['area']))
                                <div class="shop-location-fact">
                                    <span>Area</span>
                                    <strong>{{ $shopLocation['area'] }}</strong>
                                </div>
                            @endif
                            @if (!empty($shopLocation['city']))
                                <div class="shop-location-fact">
                                    <span>City</span>
                                    <strong>{{ $shopLocation['city'] }}</strong>
                                </div>
                            @endif
                            @if (!empty($shopLocation['state']))
                                <div class="shop-location-fact">
                                    <span>State</span>
                                    <strong>{{ $shopLocation['state'] }}</strong>
                                </div>
                            @endif
                            @if (!empty($shopLocation['pincode']))
                                <div class="shop-location-fact">
                                    <span>PIN Code</span>
                                    <strong>{{ $shopLocation['pincode'] }}</strong>
                                </div>
                            @endif
                        </div>

                        @if (!empty($shopLocation['directions_url']))
                            <a href="{{ $shopLocation['directions_url'] }}" class="shop-location-directions"
                                target="_blank" rel="noopener noreferrer">
                                <i class="icon icon-MapPin"></i>
                                Get Directions
                                <i class="icon icon-ArrowUpRight1"></i>
                            </a>
                            <div class="shop-location-note">This will open Google Maps in a new tab</div>
                        @endif
                    </div>

                    @if (!empty($shopLocation['map_available']))
                        <div class="shop-location-map-wrap">
                            <div class="shop-location-map" id="shop-location-map" aria-label="{{ $shopName }} map"></div>
                            @if (($shopLocation['map_precision'] ?? null) === 'postal_code')
                                <div class="shop-location-map-note">Map shows the PIN code area. Use directions for the shop address.</div>
                            @elseif (($shopLocation['map_precision'] ?? null) === 'city')
                                <div class="shop-location-map-note">Map shows the city area. Use directions for the shop address.</div>
                            @endif
                        </div>
                    @else
                        <div class="shop-location-map shop-location-map-empty">
                            Map unavailable because usable location coordinates are not set.
                        </div>
                    @endif
                </div>
            </section>
        @endif
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
        'filterTitle' => 'Filters - ' . $shopName,
    ])
@endsection

@push('scripts')
    @include('storefront.partials.product-listing-filter-script')
    @if (!empty($shopLocation['map_available']))
        <script src="{{ asset('assets/admin/js/vendor/maps/leaflet/leaflet.min.js') }}"></script>
    @endif
    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-shop-offers-toggle]');

            if (!button) {
                return;
            }

            const section = button.closest('[data-shop-offers]');
            const extras = section ? section.querySelectorAll('[data-shop-offer-extra]') : [];
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            const nextExpanded = !isExpanded;
            const label = button.querySelector('[data-shop-offers-toggle-label]');
            const hiddenCount = extras.length;

            extras.forEach((card) => {
                card.hidden = !nextExpanded;
            });

            button.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
            button.classList.toggle('is-expanded', nextExpanded);

            if (label) {
                label.textContent = nextExpanded ? 'Show less' : `Show all offers (${hiddenCount})`;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const mapData = @json($shopLocation ?? null);
            const mapElement = document.getElementById('shop-location-map');

            if (!mapData || !mapData.map_available || !mapElement || !window.L || mapElement.dataset.mapInitialized) {
                return;
            }

            mapElement.dataset.mapInitialized = '1';

            const shopMap = L.map(mapElement, {
                attributionControl: true,
                scrollWheelZoom: false,
            }).setView([mapData.map_latitude, mapData.map_longitude], mapData.zoom || 13);

            // Same OpenStreetMap tile setup used on /stores.
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(shopMap);

            const escapeHtml = function(value) {
                return String(value || '').replace(/[&<>"']/g, function(char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;',
                    }[char];
                });
            };

            if (mapData.show_marker) {
                const markerIcon = L.icon({
                    iconUrl: @json(asset('assets/admin/images/vendor/leaflet/marker-icon.png')),
                    iconRetinaUrl: @json(asset('assets/admin/images/vendor/leaflet/marker-icon-2x.png')),
                    shadowUrl: @json(asset('assets/admin/images/vendor/leaflet/marker-shadow.png')),
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41],
                });

                L.marker([mapData.map_latitude, mapData.map_longitude], {
                        icon: markerIcon,
                        title: mapData.name,
                    })
                    .addTo(shopMap)
                    .bindPopup(`<strong>${escapeHtml(mapData.name)}</strong><br>${escapeHtml(mapData.address)}`);
            }

            setTimeout(function() {
                shopMap.invalidateSize();
            }, 150);
        });
    </script>
@endpush
