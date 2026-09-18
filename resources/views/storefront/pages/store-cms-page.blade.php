@extends('storefront.layouts.app')

@section('title', $page->title . ' | ' . $shop->name . ' | ' . $marketplaceName)
@section('meta_description', $page->title . ' from ' . $shop->name . ' on ' . $marketplaceName . '.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-profile.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-cms-page.css') }}">
@endpush

@section('content')
    <main class="shop-profile-page shop-cms-page">
        <div class="container shop-profile-shell">
            <nav class="shop-profile-breadcrumbs" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}">Home</a>
                <span>/</span>
                <a href="{{ route('storefront.stores.show', $shop->slug) }}">{{ $shop->name }}</a>
                <span>/</span>
                <span>{{ $page->title }}</span>
            </nav>

            <header class="shop-cms-identity">
                <a class="shop-cms-identity-link" href="{{ route('storefront.stores.show', $shop->slug) }}">
                    @if ($shopProfile['logo'])
                        <img src="{{ asset($shopProfile['logo']) }}" width="54" height="54" alt="{{ $shop->name }} logo">
                    @else
                        <span class="shop-cms-initials">{{ $shopProfile['initials'] }}</span>
                    @endif
                    <span>{{ $shop->name }}</span>
                </a>
                <a class="shop-cms-back" href="{{ route('storefront.stores.show', $shop->slug) }}">Back to shop <i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
            </header>

            <nav class="shop-profile-nav" aria-label="Shop navigation">
                <div class="shop-profile-nav-items">
                    <a class="shop-profile-nav-item" href="{{ route('storefront.stores.show', $shop->slug) }}"><i class="icon icon-HouseLine" aria-hidden="true"></i>Shop Home</a>
                    <a class="shop-profile-nav-item" href="{{ route('storefront.stores.show', $shop->slug) }}#shop-products"><i class="icon icon-Package" aria-hidden="true"></i>All Products</a>
                    @if ($shopFooterPages->has('about'))
                        <a class="shop-profile-nav-item {{ $page->page_key === 'about' ? 'is-active' : '' }}" href="{{ route('storefront.stores.pages.show', [$shop->slug, $shopFooterPages['about']->slug]) }}"><i class="icon icon-Info" aria-hidden="true"></i>About Us</a>
                    @endif
                    <a class="shop-profile-nav-item" href="{{ route('storefront.stores.show', $shop->slug) }}#shop-location"><i class="icon icon-MapPin" aria-hidden="true"></i>Location</a>
                </div>
            </nav>

            <article class="shop-cms-article">
                <h1>{{ $page->title }}</h1>
                <div class="shop-cms-content">{!! app(\App\Services\Merchant\ShopPageContent::class)->render($page->body) !!}</div>
            </article>

            @include('storefront.partials.shop-mini-footer')
        </div>
    </main>
@endsection
