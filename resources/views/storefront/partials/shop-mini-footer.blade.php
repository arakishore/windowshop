@php
    $shopName = $shopProfile['name'];
    $shopFooterLocation = $shopFooterLocation ?? ($shopProfile['address'] ?? null);
@endphp
<footer class="shop-mini-footer" aria-label="{{ $shopName }} footer">
    <div class="shop-mini-footer-identity">
        <div class="shop-mini-footer-logo">
            @if ($shopProfile['logo'])
                <img src="{{ asset($shopProfile['logo']) }}" width="58" height="58" alt="{{ $shopName }} logo">
            @else
                <span>{{ $shopProfile['initials'] }}</span>
            @endif
        </div>
        <div class="shop-mini-footer-copy">
            <div class="shop-mini-footer-name">{{ $shopName }}</div>
            @if ($shopFooterLocation)
                <div class="shop-mini-footer-location">{{ $shopFooterLocation }}</div>
            @endif
        </div>
    </div>

    <nav class="shop-mini-footer-links" aria-label="{{ $shopName }} pages">
        @foreach (['about' => 'About Us', 'privacy' => 'Privacy Policy', 'terms' => 'Terms & Conditions', 'policies' => 'Shop Policies'] as $key => $label)
            @if ($shopFooterPages->has($key))
                <a href="{{ route('storefront.stores.pages.show', [$shop->slug, $shopFooterPages[$key]->slug]) }}">{{ $label }}</a>
            @endif
        @endforeach
        <a href="#" data-shop-footer-placeholder>Contact Us</a>
    </nav>

    @if (!empty($shopWhatsappUrl))
        <a class="shop-mini-footer-whatsapp" href="{{ $shopWhatsappUrl }}" target="_blank" rel="noopener noreferrer">
            <i class="icon icon-WhatsappLogo" aria-hidden="true"></i>
            Chat on WhatsApp
        </a>
    @endif
</footer>
