@php
    $wishlistedIds = collect($wishlistedProductIds ?? [])->map(fn ($id) => (int) $id)->all();
    $isWishlisted = in_array((int) ($product['product_id'] ?? 0), $wishlistedIds, true);
    $label = $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist';
    $tooltipClass = $tooltipClass ?? 'tooltip-left';
    $extraClass = $extraClass ?? '';
@endphp

@if (! empty($product['product_id']) && ! empty($product['wishlist_store_url']) && ! empty($product['wishlist_destroy_url']))
    <a href="#;"
        class="hover-tooltip {{ $tooltipClass }} box-icon js-wishlist-toggle {{ $extraClass }} {{ $isWishlisted ? 'is-wishlisted' : '' }}"
        data-wishlist-toggle
        data-wishlist-product-id="{{ $product['product_id'] }}"
        data-wishlist-store-url="{{ $product['wishlist_store_url'] }}"
        data-wishlist-destroy-url="{{ $product['wishlist_destroy_url'] }}"
        data-wishlist-state="{{ $isWishlisted ? '1' : '0' }}"
        data-login-url="{{ route('storefront.login') }}"
        title="{{ $label }}"
        aria-label="{{ $label }}">
        <span class="icon icon-heart"></span>
        @if (($showTooltip ?? true) === true)
            <span class="tooltip">{{ $label }}</span>
        @endif
    </a>

    @include('storefront.components.wishlist-assets')
@else
    <a href="#;" class="hover-tooltip {{ $tooltipClass }} box-icon {{ $extraClass }}" title="Add to Wishlist" aria-label="Add to Wishlist">
        <span class="icon icon-heart"></span>
        @if (($showTooltip ?? true) === true)
            <span class="tooltip">Add to Wishlist</span>
        @endif
    </a>
@endif
