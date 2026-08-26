<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="author" content="WindowShop">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="@yield('meta_description', 'WindowShop storefront preview.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        .customer-location-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .storefront-account-initials {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid currentColor;
            border-radius: 50%;
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .customer-location-trigger .location-pin-icon {
            position: relative;
            width: 18px;
            height: 18px;
            display: inline-block;
        }

        .customer-location-trigger .location-pin-icon::before {
            content: "";
            position: absolute;
            left: 50%;
            top: 1px;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-radius: 50% 50% 50% 0;
            transform: translateX(-50%) rotate(-45deg);
        }

        .customer-location-trigger .location-pin-icon::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 6px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: currentColor;
            transform: translateX(-50%);
        }

        .customer-location-trigger[data-location-tooltip]::after {
            content: attr(data-location-tooltip);
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            z-index: 20;
            width: max-content;
            max-width: 240px;
            padding: 8px 10px;
            border-radius: 6px;
            background: #111;
            color: #fff;
            font-size: 12px;
            line-height: 1.35;
            white-space: normal;
            text-align: center;
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) translateY(-3px);
            transition: opacity .16s ease, transform .16s ease;
        }

        .customer-location-trigger[data-location-tooltip]::before {
            content: "";
            position: absolute;
            top: calc(100% + 5px);
            left: 50%;
            z-index: 21;
            width: 9px;
            height: 9px;
            background: #111;
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) rotate(45deg);
            transition: opacity .16s ease;
        }

        .customer-location-trigger:hover::after,
        .customer-location-trigger:focus-visible::after,
        .customer-location-trigger:hover::before,
        .customer-location-trigger:focus-visible::before {
            opacity: 1;
        }

        .customer-location-trigger:hover::after,
        .customer-location-trigger:focus-visible::after {
            transform: translateX(-50%) translateY(0);
        }

        .customer-location-modal .modal-dialog {
            max-width: 420px;
        }

        .customer-location-modal .modal-content {
            padding: 30px 28px 28px;
            border: 0;
        }

        .customer-location-modal .icon-close-popup {
            top: 20px;
            right: 20px;
        }

        .customer-location-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            height: 28px;
            padding: 0 12px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(225, 67, 67, .09);
            color: var(--primary);
            font-size: 12px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0;
        }

        .customer-location-modal .title-pop {
            margin-bottom: 10px;
            font-size: 30px;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .customer-location-modal .desc-pop {
            max-width: 320px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.55;
        }

        .customer-location-modal .modal-heading {
            margin-bottom: 24px;
        }

        .customer-location-detect-btn {
            width: 100%;
            min-height: 46px;
            margin-bottom: 16px;
        }

        .customer-location-or {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 16px;
            color: var(--text-2);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .customer-location-or::before,
        .customer-location-or::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(18, 18, 18, .1);
        }

        .customer-location-detected {
            display: none;
            margin-bottom: 16px;
            padding: 14px;
            border: 1px solid rgba(18, 18, 18, .12);
            border-radius: 8px;
            background: rgba(18, 18, 18, .025);
        }

        .customer-location-detected.is-visible {
            display: block;
        }

        .customer-location-detected-title {
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .customer-location-detected-pin {
            margin-bottom: 2px;
            font-size: 22px;
            line-height: 1.2;
            font-weight: 700;
            color: var(--main);
        }

        .customer-location-detected-meta {
            margin-bottom: 12px;
            color: var(--text-2);
            font-size: 13px;
            line-height: 1.45;
        }

        .customer-location-detected-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customer-location-form {
            display: flex;
            align-items: end;
            gap: 12px;
        }

        .customer-location-form .tf-field {
            flex: 1 1 auto;
            margin-bottom: 0;
        }

        .customer-location-form input {
            height: 48px;
        }

        .customer-location-form .tf-btn {
            min-width: 112px;
            height: 48px;
            flex: 0 0 auto;
            padding-inline: 22px;
        }

        .customer-location-error {
            display: none;
            margin-top: 10px;
            color: #c62828;
            font-size: 13px;
        }

        .customer-location-error.is-visible {
            display: block;
        }

        .customer-location-helper {
            max-width: none;
            margin-top: 12px;
            text-align: left;
        }

        @media (max-width: 575px) {
            .customer-location-modal .modal-content {
                padding: 28px 22px 24px;
            }

            .customer-location-modal .title-pop {
                font-size: 28px;
            }

            .customer-location-form {
                flex-direction: column;
                align-items: stretch;
            }

            .customer-location-detected-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .customer-location-form .tf-btn {
                width: 100%;
            }

            .customer-location-helper {
                text-align: center;
            }
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
