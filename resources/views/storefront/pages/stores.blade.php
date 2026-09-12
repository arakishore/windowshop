@extends('storefront.layouts.app')

@section('title', 'Stores Near You | WindowShop')
@section('meta_description', 'Discover local shops around your selected location on WindowShop.')

@push('styles')
    <style>
        .store-hero {
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border-bottom: 1px solid #e5e7eb;
            overflow: hidden;
            padding: 34px 0 30px;
            position: relative;
        }

        .store-hero-map {
            contain: paint;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            position: absolute;
            z-index: 0;
        }

        .store-hero-map .leaflet-container,
        .store-hero-map .leaflet-pane,
        .store-hero-map .leaflet-map-pane,
        .store-hero-map .leaflet-tile-pane,
        .store-hero-map .leaflet-layer,
        .store-hero-map .leaflet-tile-container,
        .store-hero-map .leaflet-tile {
            height: 100%;
            left: 0;
            position: absolute;
            top: 0;
            width: 100%;
        }

        .store-hero-map .leaflet-container {
            background: #edf2f7;
            font: 12px/1.5 Arial, sans-serif;
            overflow: hidden;
        }

        .store-hero-map .leaflet-tile {
            border: 0;
            filter: saturate(.72) contrast(.94);
            max-width: none !important;
            user-select: none;
        }

        .store-hero-map .leaflet-control-attribution {
            background: rgba(255, 255, 255, .76);
            bottom: 4px;
            color: #64748b;
            font-size: 10px;
            line-height: 1.2;
            padding: 2px 5px;
            position: absolute;
            right: 6px;
            z-index: 3;
        }

        .store-hero-map .leaflet-proxy {
            position: absolute;
            visibility: hidden;
        }

        .store-hero-overlay {
            background: rgba(255, 255, 255, .70);
            inset: 0;
            position: absolute;
            z-index: 1;
        }

        .store-hero .container {
            position: relative;
            z-index: 2;
        }

        .store-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 22px;
            align-items: end;
        }

        .store-eyebrow {
            color: #047857;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .04em;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .store-breadcrumbs {
            align-items: center;
            display: inline-flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .store-hero h1 {
            color: #111827;
            font-size: clamp(32px, 5vw, 54px);
            font-weight: 800;
            line-height: 1;
            margin: 0 0 12px;
        }

        .store-hero p {
            color: #4b5563;
            font-size: 16px;
            line-height: 1.6;
            margin: 0;
            max-width: 620px;
        }

        .store-location-card {
            background: #fff;
            border: 1px solid #d1fae5;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
            min-width: 260px;
            padding: 16px 18px;
        }

        .store-location-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .store-location-value {
            color: #111827;
            font-size: 18px;
            font-weight: 800;
            margin-top: 4px;
        }

        .store-location-change {
            color: #047857;
            display: inline-flex;
            font-size: 13px;
            font-weight: 700;
            margin-top: 8px;
            text-decoration: none;
        }

        .store-filter-shell {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 12px 34px rgba(15, 23, 42, .06);
            margin-top: -18px;
            padding: 18px;
            position: relative;
            z-index: 2;
        }

        .store-filter-form {
            display: grid;
            grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(150px, 1fr)) auto;
            gap: 12px;
        }

        .store-field {
            min-width: 0;
            position: relative;
        }

        .store-field i {
            color: #64748b;
            font-size: 16px;
            left: 15px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
        }

        .store-field input,
        .store-field select {
            appearance: none;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #111827;
            font-size: 14px;
            height: 48px;
            outline: none;
            padding: 0 14px 0 42px;
            width: 100%;
        }

        .store-field select {
            cursor: pointer;
            padding-right: 34px;
        }

        .store-search-btn {
            align-items: center;
            background: #111827;
            border: 0;
            border-radius: 12px;
            color: #fff;
            display: inline-flex;
            font-size: 14px;
            font-weight: 800;
            gap: 8px;
            height: 48px;
            justify-content: center;
            padding: 0 20px;
            white-space: nowrap;
        }

        .store-filter-foot {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            margin-top: 14px;
        }

        .store-nearby-label {
            align-items: center;
            color: #475569;
            display: inline-flex;
            font-size: 14px;
            gap: 7px;
        }

        .store-clear-link {
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            text-decoration: underline;
        }

        .stores-empty {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            color: #475569;
            padding: 36px;
            text-align: center;
        }

        .stores-pagination {
            margin-top: 28px;
        }

        @media (max-width: 1199px) {
            .store-filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .store-search-btn {
                grid-column: span 2;
            }

        }

        @media (max-width: 767px) {

            .store-hero-grid,
            .store-filter-form {
                grid-template-columns: 1fr;
            }

            .store-location-card {
                min-width: 0;
            }

            .store-search-btn {
                grid-column: auto;
            }
        }

        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 24px;

            margin: 0 auto;
        }

        .shop-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .shop-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
        }

        .shop-image-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 10;
            overflow: hidden;
        }

        .shop-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .shop-logo {
            position: absolute;
            top: 14px;
            left: 14px;
            width: 56px;
            height: 56px;

            border-radius: 12px;
            background: #fff;
            border: 2px solid rgba(255, 255, 255, 0.9);

            display: flex;
            align-items: center;
            justify-content: center;

            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
            overflow: hidden;
        }

        .shop-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 10px;
        }

        /* No-logo fallback */
        .shop-logo-initial {
            background: #fff;
        }

        .shop-initial {
            width: 100%;
            height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            color: #333;
            text-transform: uppercase;
        }

        .shop-logo.default {

            color: #fff;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            line-height: 1.2;
            padding: 4px;
        }

        .shop-content {
            padding: 18px 20px 22px;
        }

        .shop-name {
            font-size: 18px;
            font-weight: 700;
            color: #111;
            margin-bottom: 14px;
            line-height: 1.3;
        }

        .shop-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 18px;
        }

        .meta-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 14px;
            color: #444;
            line-height: 1.4;
        }

        .meta-row i {
            font-size: 15px;
            color: #666;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .meta-label {
            font-weight: 500;
            color: #555;
            white-space: nowrap;
        }

        .meta-value {
            color: #333;
            word-break: break-word;
        }

        .website-row {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }

        .website-url {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #333;
            font-size: 14px;
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #666;
            font-size: 15px;
            border-radius: 4px;
            flex-shrink: 0;
            transition: color 0.15s, background 0.15s;
        }

        .copy-btn:hover {
            color: #111;
            background: #f0f0f0;
        }

        .open-store-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #1a73e8;
            text-decoration: none;
            transition: color 0.15s;
        }

        .open-store-link:hover {
            color: #0d47a1;
            text-decoration: underline;
        }

        .open-store-link i {
            font-size: 13px;
        }

        /* Placeholder for missing image */
        .no-image {
            width: 100%;
            height: 100%;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 15px;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .shops-grid {
                grid-template-columns: 1fr;
            }

            .shop-content {
                padding: 16px;
            }

            .shop-name {
                font-size: 17px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $filters = $filters ?? [];
        $selectedPostalCode = $selectedPostalCode ?? ($currentPostalCode ?? null);
        $locationDistrict = $locationDistrict ?? null;
        $areaLabel = $locationDistrict ? 'All ' . $locationDistrict : 'All Areas';
        $resultCount = method_exists($stores, 'total') ? $stores->total() : count($stores);
        $storeHeroMap = $storeHeroMap ?? null;
    @endphp

    <section class="store-hero">
        @if ($storeHeroMap)
            <div class="store-hero-map" id="store-hero-map" aria-hidden="true"></div>
            <div class="store-hero-overlay" aria-hidden="true"></div>
        @endif
        <div class="container">
            <div class="store-hero-grid">
                <div>
                    <div class="store-breadcrumbs">
                        <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <span class="text-caption-01 cl-text-3">Our Stores</span>
                    </div>
                    <div class="store-eyebrow">WindowShop Marketplace</div>
                    <h1>Stores Near You</h1>
                    <p>Discover shops around your selected location.</p>
                </div>

                <div class="store-location-card">
                    <div class="store-location-label">Selected PIN</div>
                    <div class="store-location-value">{{ $selectedPostalCode ?: 'Not selected' }}</div>
                    <a href="#customer-location-modal" data-bs-toggle="modal"
                        class="store-location-change customer-location-trigger">
                        Change Location
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="store-filter-shell">
                <form method="GET" action="{{ route('storefront.stores') }}" class="store-filter-form">
                    <label class="store-field" aria-label="Search shops">
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                            placeholder="Search shops" autocomplete="off">
                    </label>

                    <label class="store-field" aria-label="Area">
                        <i class="icon icon-MapPin"></i>
                        <select name="area">
                            <option value="">{{ $areaLabel }}</option>
                            @foreach ($areaOptions as $area)
                                <option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ $area }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="store-field" aria-label="Shop Type">
                        <i class="icon icon-Tag"></i>
                        <select name="shop_type">
                            <option value="">All Shop Types</option>
                            @foreach ($shopTypeOptions as $shopType)
                                <option value="{{ $shopType->slug }}" @selected(($filters['shop_type'] ?? '') === $shopType->slug)>
                                    {{ $shopType->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="store-field" aria-label="Audience">
                        <i class="icon icon-Users"></i>
                        <select name="audience">
                            <option value="">All Audiences</option>
                            @foreach ($audienceOptions as $audience)
                                <option value="{{ $audience->slug }}" @selected(($filters['audience'] ?? '') === $audience->slug)>
                                    {{ $audience->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="store-search-btn">
                        <i class="icon icon-MagnifyingGlass"></i>
                        <span>Search Stores</span>
                    </button>
                </form>

                <div class="store-filter-foot">
                    <div class="store-nearby-label">
                        <i class="icon icon-MapPin"></i>
                        <span>
                            @if ($selectedPostalCode)
                                Showing stores near {{ $selectedPostalCode }}
                            @else
                                Select a PIN to see nearby stores
                            @endif
                            &bull; {{ $resultCount }} {{ \Illuminate\Support\Str::plural('store', $resultCount) }} found
                        </span>
                    </div>

                    @if ($hasActiveStoreFilters ?? false)
                        <a href="{{ route('storefront.stores') }}" class="store-clear-link">Clear filters</a>
                    @endif
                </div>
            </div>

            @if ($stores->count())
                <div class="tf-grid-layout sm-col-2 xl-col-3 flat-spacing-2 pb-0">
                    @forelse($stores as $store)
                        <div class="shop-card">
                            <div class="shop-image-wrapper">
                                <img class="shop-image" loading="lazy" width="450" height="338"
                                    src="{{ asset($store['image']) }}" alt="{{ $store['name'] }}"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="no-image" style="display:none;">No Image</div>

                                <div class="shop-logo {{ empty($store['logo']) ? 'shop-logo-initial' : '' }}">
                                    @if (!empty($store['logo']))
                                        <img loading="lazy" width="56" height="56" src="{{ asset($store['logo']) }}"
                                            alt="{{ $store['name'] }} logo">
                                    @else
                                        <span class="shop-initial">
                                            @php
                                                $initials = collect(preg_split('/\s+/', trim($store['name'])))
                                                    ->filter()
                                                    ->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                                                    ->take(3)
                                                    ->implode('');
                                            @endphp

                                            {{ $initials }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="shop-content">
                                <h3 class="shop-name">{{ $store['name'] }}</h3>

                                <div class="shop-meta">
                                    <div class="meta-row">
                                        <i class="icon icon-Tag"></i>
                                        <span><span class="meta-label">Shop Type:</span> <span
                                                class="meta-value">{{ $store['shop_type'] ?: 'General Store' }}</span></span>
                                    </div>

                                    @if (!empty($store['audiences']))
                                        <div class="meta-row">
                                            <i class="icon icon-Users"></i>
                                            <span><span class="meta-label">Audience:</span> <span
                                                    class="meta-value">{{ implode(', ', $store['audiences']) }}</span></span>
                                        </div>
                                    @endif

                                    <div class="meta-row">
                                        <i class="icon icon-MapPin"></i>
                                        <span>
                                            <span class="meta-label">Address:</span>
                                            <span class="meta-value">
                                                @if (!empty($store['maps_url']))
                                                    <a href="{{ $store['maps_url'] }}" target="_blank"
                                                        rel="noopener noreferrer" class="link"
                                                        title="Open address in Google Maps">
                                                        {{ $store['address'] ?: 'Address unavailable' }}
                                                        <i class="icon icon-ArrowUpRight1"></i>
                                                    </a>
                                                @else
                                                    <span>{{ $store['address'] ?: 'Address unavailable' }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </div>

                                    <div class="meta-row">
                                        <i class="icon icon-Globe"></i>
                                        <div class="website-row">
                                            <span class="meta-label">Website URL:</span>
                                            <span class="website-url" title="{{ $store['website_url'] }}">
                                                {{ $store['website_url'] }}</span>
                                            <button class="copy-btn" type="button" data-copy-url="{{ $store['website_url'] }}"
                                                title="Copy URL">
                                                <i class="icon icon-CopySimple"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ $store['store_url'] }}" class="open-store-link" target="_blank"
                                    rel="noopener">
                                    Open Store Website <i class="icon icon-ArrowUpRight1"></i>
                                </a>
                            </div>
                        </div>


                    @empty
                        <p class="text-body-1 cl-text-2">No stores are currently available.</p>
                    @endforelse
                </div>

                <div class="stores-pagination">
                    {{ $stores->links() }}
                </div>
            @else
                <div class="stores-empty mt-5">
                    @if ($hasActiveStoreFilters ?? false)
                        No stores match your current search.
                    @else
                        No stores are currently available for this location.
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    @if ($storeHeroMap)
        <script src="{{ asset('assets/admin/js/vendor/maps/leaflet/leaflet.min.js') }}"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mapData = @json($storeHeroMap);
            const mapElement = document.getElementById('store-hero-map');

            if (mapData && mapElement && window.L && !mapElement.dataset.mapInitialized) {
                mapElement.dataset.mapInitialized = '1';

                const heroMap = L.map(mapElement, {
                    zoomControl: false,
                    attributionControl: true,
                    dragging: false,
                    scrollWheelZoom: false,
                    doubleClickZoom: false,
                    boxZoom: false,
                    keyboard: false,
                    touchZoom: false,
                }).setView([mapData.latitude, mapData.longitude], mapData.zoom || 11);

                // Public OpenStreetMap tiles are fine for local/dev. Review a production tile provider before high traffic.
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(heroMap);
            }

            document.querySelectorAll('[data-copy-url]').forEach(function(button) {
                button.addEventListener('click', function() {
                    const url = button.getAttribute('data-copy-url') || '';

                    const fallbackCopy = function() {
                        const textarea = document.createElement('textarea');
                        textarea.value = url;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'absolute';
                        textarea.style.left = '-9999px';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                    };

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).catch(fallbackCopy);
                    } else {
                        fallbackCopy();
                    }
                });
            });
        });
    </script>
@endpush
