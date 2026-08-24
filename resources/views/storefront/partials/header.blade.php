@php
    $storefrontCustomer = app(\App\Services\Storefront\StorefrontCustomerContext::class)->user(request());
@endphp

<header class="tf-header header-s7 scr-box-shadow">
    <div class="container-full">
        <div class="header-inner">
            <div class="box-open-menu-mobile d-xl-none">
                <a href="#mobileMenu" data-bs-toggle="offcanvas" class="btn-open-menu">
                    <i class="icon icon-List"></i>
                </a>
            </div>
            <div class="header-left d-none d-xl-flex">
                <a href="{{ route('storefront.home') }}" class="logo-site">
                    <img loading="lazy" width="150" height="30" src="{{ $marketplaceLogoUrl }}" alt="WindowShop">
                </a>
                @include('storefront.partials.main-menu')
            </div>
            <div class="header-center d-xl-none">
                <a href="{{ route('storefront.home') }}" class="logo-site">
                    <img loading="lazy" width="150" height="30" src="{{ $marketplaceLogoUrl }}" alt="WindowShop">
                </a>
            </div>
            <div class="header-right">
                <form action="#;" class="form-search-nav style-3 d-none d-xl-block">
                    <fieldset>
                        <input type="text" placeholder="Search Products" required>
                    </fieldset>
                    <button type="submit" class="btn-action">
                        <i class="icon icon-MagnifyingGlass"></i>
                    </button>
                </form>
                <ul class="nav-icon-list">
                    <li class="d-none d-sm-block d-xl-none">
                        <a href="#search" data-bs-toggle="modal" class="nav-icon-item link">
                            <i class="icon icon-MagnifyingGlass"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#customer-location-modal"
                            data-bs-toggle="modal"
                            class="nav-icon-item link customer-location-trigger"
                            aria-label="{{ $currentPostalCode ? 'Shopping near '.$currentPostalCode.'. Change location.' : 'Choose location' }}"
                            data-location-tooltip="{{ $currentPostalCode ? 'Shopping near '.$currentPostalCode.'. Change location.' : 'Choose location' }}">
                            <span class="location-pin-icon" aria-hidden="true"></span>
                        </a>
                    </li>
                    
                    @if ($storefrontCustomer)
                        <li class="nav-account">
                            <a href="{{ route('storefront.account') }}" class="nav-icon-item link">
                                <i class="icon icon-User"></i>
                            </a>
                            <div class="dropdown-account">
                                <ul class="list-menu-item">
                                    <li>
                                        <a href="{{ route('storefront.account') }}" class="sub-menu_link type-pri">
                                            <span class="cus-text">My Account</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('storefront.account') }}#orders" class="sub-menu_link type-pri">
                                            <span class="cus-text">Your Order</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('storefront.account') }}#addresses" class="sub-menu_link type-pri">
                                            <span class="cus-text">My Address</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('storefront.account') }}#tracking" class="sub-menu_link type-pri">
                                            <span class="cus-text">Tracking</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('storefront.account') }}#settings" class="sub-menu_link type-pri">
                                            <span class="cus-text">Setting</span>
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('storefront.logout') }}">
                                            @csrf
                                            <button type="submit" class="sub-menu_link type-pri storefront-account-logout">
                                                <span class="cus-text">Logout</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('storefront.account') }}" class="nav-icon-item link">
                                <i class="icon icon-User"></i>
                            </a>
                        </li>
                    @endif
                    <li class="d-none d-sm-block">
                        <a href="#;" class="nav-icon-item link">
                            <i class="icon icon-HeartStraight"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="nav-icon-item link shop-cart">
                            <i class="icon icon-Handbag"></i>
                            <span class="count" data-storefront-cart-count>{{ $storefrontCartCount ?? '0' }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
