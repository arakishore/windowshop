@php
    $imageUrl = $item['image_url'];
    $stockClass = $item['stock'] > 10 ? 'text-success' : ($item['stock'] > 0 ? 'text-warning' : 'text-danger');
@endphp

<div
    class="pos-product-card h-100 js-pos-product-card {{ $item['stock'] > 0 ? 'js-pos-add-card' : 'opacity-75' }}"
    data-variant-id="{{ $item['id'] }}"
    data-sku="{{ $item['sku'] }}"
    data-barcode="{{ $item['barcode'] }}"
    data-search="{{ Str::lower(trim($item['product_name'].' '.$item['variant_name'].' '.$item['sku'].' '.$item['barcode'].' '.$item['category_name'].' '.$item['attribute_search'])) }}"
    role="{{ $item['stock'] > 0 ? 'button' : 'group' }}"
    tabindex="{{ $item['stock'] > 0 ? '0' : '-1' }}"
>
    <div class="pos-product-image">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $item['product_name'] }}">
        @else
            <div class="pos-product-image-placeholder">
                <i class="ph-image"></i>
            </div>
        @endif
    </div>

    <div class="pos-product-body">
        <div class="fw-semibold pos-product-name">{{ $item['product_name'] }}</div>
        <div class="text-muted pos-product-variant">{{ $item['variant_name'] ?: 'Standard variant' }}</div>
        @if($item['sku'])
            <div class="text-muted fs-sm text-truncate">SKU: {{ $item['sku'] }}</div>
        @endif

        <div class="d-flex align-items-end justify-content-between gap-2 mt-auto">
            <div>
                <div class="fw-bold fs-6">INR {{ number_format($item['price'], 2) }}</div>
                <div class="fs-sm {{ $stockClass }}">{{ number_format($item['stock']) }} in stock</div>
            </div>
            <button
                type="button"
                class="btn btn-primary btn-icon rounded-pill js-pos-add"
                data-variant-id="{{ $item['id'] }}"
                {{ $item['stock'] < 1 ? 'disabled' : '' }}
                title="Add to cart"
            >
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
</div>
