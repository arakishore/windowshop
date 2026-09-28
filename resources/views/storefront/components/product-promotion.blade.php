@if(!empty($product['promotion_label']))
    <div class="product-promo-badge" title="{{ $product['promotion_text'] ?? $product['promotion_label'] }}">
        @if(!empty($product['promotion_icon']))
            <i class="icon {{ $product['promotion_icon'] }}" aria-hidden="true"></i>
        @endif
        {{ $product['promotion_label'] }}
    </div>
@endif
