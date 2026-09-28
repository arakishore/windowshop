<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="author" content="{{ $marketplaceName }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="@yield('meta_description', $marketplaceName.' storefront preview.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $marketplaceName.' Storefront')</title>
    @hasSection('canonical_url')
        <link rel="canonical" href="@yield('canonical_url')">
    @endif

    <link rel="stylesheet" href="{{ asset('assets/storefront/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/icon/icomoon/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/storefront-layout.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/storefront/images/logo/favicon.svg') }}">
    <link rel="apple-touch-icon-precomposed" href="{{ asset('assets/storefront/images/logo/favicon.svg') }}">

    @stack('head')
    @stack('styles')
</head>

<body>
    <button id="goTop">
        <span class="border-progress"></span>
        <span class="ic-wrap">
            <span class="icon icon-CaretTopThin"></span>
        </span>
    </button>

    <div class="preload preload-container" id="preload">
        <div class="preload-logo">
            <div class="spinner"></div>
        </div>
    </div>

    <main id="wrapper">
        @include('storefront.partials.topbar')
        @include('storefront.partials.header')

        @yield('content')

        @include('storefront.partials.footer')
    </main>
    <!-- Toolbar -->
    <div class="tf-toolbar-bottom">
        <div class="toolbar-item">
            <a href="{{ route('storefront.products') }}">
                <span class="toolbar-icon">
                    <i class="icon icon-storefront"></i>
                </span>
                <span class="toolbar-label">Shop</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="#search" data-bs-toggle="modal">
                <span class="toolbar-icon">
                    <i class="icon icon-MagnifyingGlass"></i>
                </span>
                <span class="toolbar-label">Search</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="{{ route('storefront.account') }}">
                <span class="toolbar-icon">
                    <i class="icon icon-User"></i>
                </span>
                <span class="toolbar-label">Account</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="{{ route('storefront.account.wishlist') }}">
                <span class="toolbar-icon">
                    <i class="icon icon-HeartStraight"></i>
                </span>
                <span class="toolbar-label">Wishlist</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="{{ route('storefront.cart') }}">
                <span class="toolbar-icon">
                    <i class="icon icon-Handbag"></i>
                    <span class="toolbar-count" data-storefront-cart-count>{{ $storefrontCartCount ?? '0' }}</span>
                </span>
                <span class="toolbar-label">Cart</span>
            </a>
        </div>
    </div>
    <!-- /Toolbar -->
    @include('storefront.partials.mobile-menu')
    @include('storefront.partials.search')
    @include('storefront.partials.customer-location-modal')
    @include('storefront.partials.scripts')
    @stack('scripts')
</body>

</html>
