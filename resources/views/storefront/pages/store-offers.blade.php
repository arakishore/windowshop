@extends('storefront.layouts.app')

@php
    $shopName = $shopProfile['name'];
@endphp

@section('title', 'Products on Offer from ' . $shopName . ' | ' . $marketplaceName)
@section('meta_description', 'Browse current offer products from ' . $shopName . '.')

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
                <a href="{{ route('storefront.stores.show', $shop->slug) }}">{{ $shopName }}</a>
                <span>/</span>
                <span>Products on Offer</span>
            </nav>

            <section class="shop-profile-section mt-0" id="shop-offer-products">
                @include('storefront.partials.product-listing-controls', [
                    'selectedFilters' => $selectedFilters,
                    'filterDrawerId' => 'filterOfferProducts',
                ])

                <div class="shop-profile-section-head">
                    <div>
                        <h1 class="shop-profile-section-title">Products on Offer</h1>
                        <div class="text-caption-01 cl-text-2 mt-1">
                            Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} of
                            {{ $products->total() }} offer products from {{ $shopName }}
                        </div>
                    </div>
                </div>

                @if ($products->count() > 0)
                    <div class="shop-profile-product-grid">
                        @foreach ($products as $product)
                            @include('storefront.components.product-card', [
                                'product' => $product,
                                'wishlistedProductIds' => $wishlistedProductIds ?? [],
                                'wrapSlide' => false,
                            ])
                        @endforeach
                    </div>

                    <div class="shop-profile-pagination">
                        @include('storefront.partials.pagination', ['paginator' => $products])
                    </div>
                @else
                    <div class="shop-profile-empty">
                        No offer products match the selected filters.
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
        'filterDrawerId' => 'filterOfferProducts',
        'filterTitle' => 'Offer Product Filters - ' . $shopName,
    ])
@endsection

@push('scripts')
    @include('storefront.partials.product-listing-filter-script')
@endpush
