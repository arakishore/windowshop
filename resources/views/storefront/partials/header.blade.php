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
                        <a href="{{ route('storefront.login') }}" class="nav-icon-item link">
                            <i class="icon icon-User"></i>
                        </a>
                    </li>
                    <li class="d-none d-sm-block">
                        <a href="#;" class="nav-icon-item link">
                            <i class="icon icon-HeartStraight"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="nav-icon-item link shop-cart">
                            <i class="icon icon-Handbag"></i>
                            <span class="count">12</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
