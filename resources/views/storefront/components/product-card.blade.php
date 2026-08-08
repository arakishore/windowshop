<div class="swiper-slide wow fadeInUp">
    <div class="card-product {{ $product['has_size'] ?? false ? 'has-size' : '' }}">
        <div class="card-product_wrapper">
            <a href="{{ $product['url'] ?? '#;' }}" class="product-img">
                <img class="img-product" loading="lazy" width="330" height="440" src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}">
                <img class="img-hover" loading="lazy" width="330" height="440" src="{{ asset($product['hover_image'] ?? $product['image']) }}" alt="{{ $product['name'] }}">
            </a>
            <ul class="product-action_list">
                <li class="wishlist">
                    <a href="#;" class="hover-tooltip tooltip-left box-icon">
                        <span class="icon icon-heart"></span>
                        <span class="tooltip">Add to Wishlist</span>
                    </a>
                </li>
                <li class="compare">
                    <a href="#compare" data-bs-toggle="offcanvas" class="hover-tooltip tooltip-left box-icon">
                        <span class="icon icon-ArrowsLeftRight"></span>
                        <span class="tooltip">Compare</span>
                    </a>
                </li>
                <li>
                    <a href="#quickView" data-bs-toggle="offcanvas" class="hover-tooltip tooltip-left box-icon">
                        <span class="icon icon-Eye"></span>
                        <span class="tooltip">Quick view</span>
                    </a>
                </li>
            </ul>
            @if(!empty($product['badge']))
                <ul class="product-badge_list">
                    <li class="product-badge_item text-caption-01 {{ strtolower($product['badge']) }}">{{ $product['badge'] }}</li>
                </ul>
            @endif
            <div class="product-action_bot">
                <a href="#quickAdd" data-bs-toggle="modal" class="tf-btn btn-white small w-100">Quick Add</a>
            </div>
        </div>
        <div class="card-product_info">
            <a href="{{ $product['url'] ?? '#;' }}" class="name-product lh-24 fw-medium link-underline-text">{{ $product['name'] }}</a>
            <div class="star-wrap d-flex align-items-center">
                @for($i = 0; $i < 5; $i++)
                    <i class="icon icon-Star"></i>
                @endfor
            </div>
            <div class="price-wrap">
                <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                @if(!empty($product['old_price']))
                    <span class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                @endif
            </div>
            @if(!empty($product['swatches']))
                <ul class="product-color_list">
                    @foreach($product['swatches'] as $swatch)
                        <li class="product-color-item color-swatch hover-tooltip tooltip-bot {{ $loop->first ? 'active' : '' }}">
                            <span class="tooltip color-filter">{{ $swatch['label'] }}</span>
                            <span class="swatch-value {{ $swatch['class'] }}"></span>
                            <img src="{{ asset($swatch['image']) }}" data-src="{{ asset($swatch['image']) }}" alt="{{ $swatch['label'] }}">
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
