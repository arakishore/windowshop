@php
    $onShopHome = !isset($page);
    $shopHomeUrl = route('storefront.stores.show', $shop->slug);
    $shopSectionUrl = fn (string $anchor) => $onShopHome ? '#'.$anchor : $shopHomeUrl.'#'.$anchor;
    $hasShopOffers = $hasShopOffers ?? ($shopPromotions ?? collect())->isNotEmpty();
@endphp

<nav class="shop-profile-nav" aria-label="Shop navigation">
    <div class="shop-profile-nav-items">
        <a class="shop-profile-nav-item {{ $onShopHome ? 'is-active' : '' }}" href="{{ $onShopHome ? '#shop-home' : $shopHomeUrl }}" @if ($onShopHome) aria-current="page" @endif><i class="icon icon-HouseLine" aria-hidden="true"></i>Shop Home</a>
        <span class="shop-profile-nav-item is-disabled" aria-disabled="true" title="Collections coming soon"><i class="icon icon-SquaresFour" aria-hidden="true"></i>Collections</span>
        @if ($hasShopOffers)
            <a class="shop-profile-nav-item" href="{{ $shopSectionUrl('offers') }}"><i class="icon icon-Tag" aria-hidden="true"></i>Offers</a>
        @else
            <span class="shop-profile-nav-item is-disabled" aria-disabled="true"><i class="icon icon-Tag" aria-hidden="true"></i>Offers</span>
        @endif
        <a class="shop-profile-nav-item" href="{{ $shopSectionUrl('shop-products') }}"><i class="icon icon-Package" aria-hidden="true"></i>All Products</a>
        @if ($shopFooterPages->has('about'))
            <a class="shop-profile-nav-item {{ isset($page) && $page->page_key === 'about' ? 'is-active' : '' }}" href="{{ route('storefront.stores.pages.show', [$shop->slug, $shopFooterPages['about']->slug]) }}" @if (isset($page) && $page->page_key === 'about') aria-current="page" @endif><i class="icon icon-Info" aria-hidden="true"></i>About Us</a>
        @else
            <span class="shop-profile-nav-item is-disabled" aria-disabled="true"><i class="icon icon-Info" aria-hidden="true"></i>About Us</span>
        @endif
        @if (!empty($shopLocation['address']) || !empty($shopLocation['directions_url']))
            <a class="shop-profile-nav-item" href="{{ $shopSectionUrl('shop-location') }}"><i class="icon icon-MapPin" aria-hidden="true"></i>Location</a>
        @else
            <span class="shop-profile-nav-item is-disabled" aria-disabled="true"><i class="icon icon-MapPin" aria-hidden="true"></i>Location</span>
        @endif
        @if ($shopWhatsappUrl)
            <a class="shop-profile-nav-item shop-profile-nav-whatsapp" href="{{ $shopWhatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="icon icon-WhatsappLogo" aria-hidden="true"></i>WhatsApp</a>
        @endif
    </div>
</nav>
