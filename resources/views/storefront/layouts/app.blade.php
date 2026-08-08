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

    @include('storefront.partials.mobile-menu')
    @include('storefront.partials.search')
    @include('storefront.partials.scripts')
    @stack('scripts')
</body>
</html>
