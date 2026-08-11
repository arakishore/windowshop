@php
    $storefrontNavigationCategories = $storefrontNavigationCategories ?? collect();
    $storefrontShop = $storefrontShop ?? null;

    $categoryUrl = function ($category) use ($storefrontShop): string {
        if ($storefrontShop) {
            return route('storefront.store.category.show', [$storefrontShop->slug, $category->slug]);
        }

        return route('storefront.category.show', $category->slug);
    };
@endphp

<nav class="box-navigation">
    <ul class="box-nav-menu">
        <li class="menu-item position-relative">
            <a href="{{ route('storefront.home') }}" class="item-link">
                <span class="text cus-text">Home</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="#;" class="item-link">
                <span class="text cus-text">Categories</span>
                <i class="icon icon-CaretDown"></i>
            </a>
            <div class="sub-menu mega-menu">
                <div class="container-full">
                    <div class="row">
                        {{-- TODO: Root category icon support. --}}
                        @forelse($storefrontNavigationCategories as $rootCategory)
                            <div class="col-2">
                                <div class="mega-menu-item menu-lv-2">
                                    <p class="menu-heading">{{ $rootCategory->name }}</p>
                                    <ul class="sub-menu_list">
                                        @forelse($rootCategory->children as $childCategory)
                                            <li>
                                                <a href="{{ $categoryUrl($childCategory) }}" class="sub-menu_link has-text">
                                                    <span class="cus-text">{{ $childCategory->name }}</span>
                                                </a>
                                            </li>
                                        @empty
                                            <li>
                                                <a href="{{ $categoryUrl($rootCategory) }}" class="sub-menu_link has-text">
                                                    <span class="cus-text">View {{ $rootCategory->name }}</span>
                                                </a>
                                            </li>
                                        @endforelse
                                    </ul>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="mega-menu-item menu-lv-2">
                                    <p class="menu-heading">Categories</p>
                                    <ul class="sub-menu_list">
                                        <li><span class="sub-menu_link has-text"><span class="cus-text">No categories available</span></span></li>
                                    </ul>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.stores') }}" class="item-link"><span class="text cus-text">Shops</span></a></li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.products') }}" class="item-link"><span class="text cus-text">Products</span></a></li>
        <li class="menu-item position-relative"><a href="#;" class="item-link"><span class="text cus-text">Brands</span></a></li>
        <li class="menu-item position-relative"><a href="#;" class="item-link"><span class="text cus-text">Offers</span></a></li>
        <li class="menu-item position-relative"><a href="#new-arrivals" class="item-link"><span class="text cus-text">New Arrivals</span></a></li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.contact') }}" class="item-link"><span class="text cus-text">Contact</span></a></li>
         
    </ul>
</nav>
