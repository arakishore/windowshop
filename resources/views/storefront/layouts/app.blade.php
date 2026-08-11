<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="author" content="WindowShop">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="@yield('meta_description', 'WindowShop storefront preview.')">
    <title>@yield('title', 'WindowShop Storefront')</title>

    <link rel="stylesheet" href="{{ asset('assets/storefront/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/icon/icomoon/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/styles.css') }}">
    <link rel="shortcut icon" href="{{ asset('assets/storefront/images/logo/favicon.svg') }}">
    <link rel="apple-touch-icon-precomposed" href="{{ asset('assets/storefront/images/logo/favicon.svg') }}">

    <style>
        .storefront-page-title {
            position: relative;
            margin-top: 0;
            padding: 34px 0 36px;
            background:
                linear-gradient(185deg, rgba(225, 67, 67, .08) 0%, rgba(255, 255, 255, .82) 48%, rgba(17, 17, 17, .06) 100%),
                #f7f4ef;
            border-top: 1px solid rgba(18, 18, 18, .06);
            border-bottom: 1px solid rgba(18, 18, 18, .08);
        }

        .storefront-page-title .main-page-title,
        .storefront-page-title .page-title {
            max-width: 820px;
            margin: 0 auto;
        }

        .storefront-page-title .breadcrumbs {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 14px;
            margin-top: 14px;
            margin-bottom: 0;
            border-radius: 999px;
        }

        .storefront-page-title h1,
        .storefront-page-title h3 {
            margin-bottom: 0;
        }

        .storefront-page-title h1 + .breadcrumbs,
        .storefront-page-title h3 + .breadcrumbs {
            margin-top: 14px;
        }

        .storefront-page-title .breadcrumbs + h1,
        .storefront-page-title .breadcrumbs + h3 {
            margin-top: 14px;
        }
    </style>

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
            <a href="{{ route('storefront.login') }}">
                <span class="toolbar-icon">
                    <i class="icon icon-User"></i>
                </span>
                <span class="toolbar-label">Account</span>
            </a>
        </div>
        <div class="toolbar-item">
            <a href="wishlist.html">
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
                    <span class="toolbar-count">12</span>
                </span>
                <span class="toolbar-label">Cart</span>
            </a>
        </div>
    </div>
    <!-- /Toolbar -->
    @include('storefront.partials.mobile-menu')
    @include('storefront.partials.search')
    @include('storefront.partials.scripts')
    @stack('scripts')
</body>

</html>
