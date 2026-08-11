@php
    $storefrontNavigationCategories = $storefrontNavigationCategories ?? collect();
    $storefrontShop = $storefrontShop ?? null;

    $mobileCategoryUrl = function ($category) use ($storefrontShop): string {
        if ($storefrontShop) {
            return route('storefront.store.category.show', [$storefrontShop->slug, $category->slug]);
        }

        return route('storefront.category.show', $category->slug);
    };
@endphp

<div class="offcanvas offcanvas-start canvas-mb" id="mobileMenu">
    <div class="canvas-header">
        <span class="icon-close-popup" data-bs-dismiss="offcanvas">
            <i class="icon icon-X2"></i>
        </span>
        <form class="form-search-nav">
            <fieldset>
                <input type="text" placeholder="What are you looking for?" required>
            </fieldset>
            <button type="submit" class="btn-action">
                <i class="icon icon-MagnifyingGlass"></i>
            </button>
        </form>
    </div>
    <div class="canvas-body">
        <div class="mb-content-top">
            <ul class="nav-ul-mb" id="wrapper-menu-navigation">
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.home') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">Home</a>
                </li>
                <li class="nav-mb-item">
                    <a href="#mobile_categories" class="mb-menu-link collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="mobile_categories">
                        <span>Categories</span>
                        <span class="ic-custom"></span>
                    </a>
                    <div id="mobile_categories" class="collapse">
                        <ul class="sub-nav-menu">
                            @forelse($storefrontNavigationCategories as $rootCategory)
                                <li>
                                    <p class="menu-heading mb-8 mt-12">{{ $rootCategory->name }}</p>
                                    <ul class="sub-menu_list">
                                        @forelse($rootCategory->children as $childCategory)
                                            <li>
                                                <a href="{{ $mobileCategoryUrl($childCategory) }}" class="sub-menu_link has-text" data-bs-dismiss="offcanvas">
                                                    <span class="cus-text">{{ $childCategory->name }}</span>
                                                </a>
                                            </li>
                                        @empty
                                            <li>
                                                <a href="{{ $mobileCategoryUrl($rootCategory) }}" class="sub-menu_link has-text" data-bs-dismiss="offcanvas">
                                                    <span class="cus-text">View {{ $rootCategory->name }}</span>
                                                </a>
                                            </li>
                                        @endforelse
                                    </ul>
                                </li>
                            @empty
                                <li><span class="sub-menu_link has-text"><span class="cus-text">No categories available</span></span></li>
                            @endforelse
                        </ul>
                    </div>
                </li>
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.stores') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">Shops</a>
                </li>
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.products') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">Products</a>
                </li>
                <li class="nav-mb-item">
                    <a href="#;" class="mb-menu-link" data-bs-dismiss="offcanvas">Brands</a>
                </li>
                <li class="nav-mb-item">
                    <a href="#;" class="mb-menu-link" data-bs-dismiss="offcanvas">Offers</a>
                </li>
                <li class="nav-mb-item">
                    <a href="#new-arrivals" class="mb-menu-link" data-bs-dismiss="offcanvas">New Arrivals</a>
                </li>
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.about') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">About</a>
                </li>
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.testimonials') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">Testimonials</a>
                </li>
                <li class="nav-mb-item">
                    <a href="{{ route('storefront.contact') }}" class="mb-menu-link" data-bs-dismiss="offcanvas">Contact</a>
                </li>
            </ul>
        </div>
        <div class="need-help-wrap">
            <p class="nd-title h6 fw-medium mb-16">Need Help?</p>
            <p class="lh-26 cl-text-2 mb-4">
                600 N Michigan Ave, Chicago, IL 60611, USA
            </p>
            <a href="#;" class="text-decoration-underline text-primary lh-26 mb-16">
                Open in Maps
            </a>
            <a href="mailto:hi.amere@gmail.com" class="cl-text-2 link mb-8">
                hi.amere@gmail.com
            </a>
            <a href="tel:3156666688" class="cl-text-2 link">
                315-666-6688
            </a>
        </div>
    </div>
</div>
