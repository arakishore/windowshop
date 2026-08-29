<div class="modal modalCentered fade modal-search" id="search">
    @php
        $searchProducts = $searchProducts ?? [
            ['name' => 'V-neck cotton T-shirt', 'price' => '$59,99', 'old_price' => '$79,99', 'image' => 'assets/storefront/images/product/product-1.jpg', 'hover_image' => 'assets/storefront/images/product/product-1_2.jpg'],
            ['name' => 'Ribbed knit top', 'price' => '$45,99', 'old_price' => '$69,99', 'image' => 'assets/storefront/images/product/product-2.jpg', 'hover_image' => 'assets/storefront/images/product/product-2_2.jpg'],
            ['name' => 'Oversized denim jacket', 'price' => '$89,99', 'old_price' => '$119,99', 'image' => 'assets/storefront/images/product/product-3.jpg', 'hover_image' => 'assets/storefront/images/product/product-3_2.jpg'],
            ['name' => 'Linen slim-fit shirt', 'price' => '$45,99', 'old_price' => '$79,99', 'image' => 'assets/storefront/images/product/product-4.jpg', 'hover_image' => 'assets/storefront/images/product/product-4_2.jpg'],
        ];
    @endphp
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-heading">
                <form class="form-search">
                    <fieldset>
                        <input type="text" placeholder="Search products" required>
                    </fieldset>
                    <button type="submit" class="btn-search"><i class="icon icon-MagnifyingGlass"></i></button>
                </form>
                <span class="icon-close-popup flex-shrink-0" data-bs-dismiss="modal">
                    <i class="icon-X2"></i>
                </span>
            </div>
            <div class="modal-main">
                <h5 class="mb-24">You May Also Like</h5>
                <div dir="ltr" class="swiper tf-swiper mb-24" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space-lg="30" data-space-md="20" data-space="15">
                    <div class="swiper-wrapper">
                        @foreach(($searchProducts ?? []) as $product)
                            @include('storefront.components.product-card', ['product' => $product])
                        @endforeach
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal modalCentered fade modal-log" id="sign">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <h3 class="title-pop mb-8">Sign In</h3>
                <p class="desc-pop cl-text-2">Sign in to access your personalized experience.</p>
            </div>
            <div class="modal-main">
                <form class="form-log">
                    <div class="form-content">
                        <fieldset class="tf-field">
                            <label for="user-name-log" class="tf-lable fw-medium">Username or email address <span class="text-primary">*</span></label>
                            <input type="text" id="user-name-log" placeholder="Username or email address*" required>
                        </fieldset>
                        <fieldset class="tf-field password-wrapper">
                            <label for="password" class="tf-lable fw-medium">Password <span class="text-primary">*</span></label>
                            <div class="password-wrapper w-100">
                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                <input class="password-field" type="password" id="password" placeholder="Password" required>
                            </div>
                        </fieldset>
                    </div>
                    <div class="group-action">
                        <button type="submit" class="tf-btn animate-btn w-100">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-bottom canvas-compare" id="compare">
    <div class="canvas-wrapper">
        <div class="canvas-header">
            <h5 class="title">Compare</h5>
            <span class="icon-close-popup" data-bs-dismiss="offcanvas"><i class="icon-X2"></i></span>
        </div>
        <div class="canvas-body"><p class="text-center cl-text-2 mb-0">Static compare preview.</p></div>
    </div>
</div>

<div class="offcanvas offcanvas-end canvas-quickview" id="quickView">
    <div class="canvas-wrapper">
        <div class="canvas-header">
            <h5 class="title">Quick View</h5>
            <span class="icon-close-popup" data-bs-dismiss="offcanvas"><i class="icon-X2"></i></span>
        </div>
        <div class="canvas-body"><p class="cl-text-2 mb-0">Static product preview shell.</p></div>
    </div>
</div>

<div class="modal modalCentered fade modal-quickadd" id="quickAdd">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="d-flex cs-pointer link" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading"><h5 class="title">Quick Add</h5></div>
            <div class="modal-main">
                <button type="button" class="tf-btn animate-btn w-100" data-bs-dismiss="modal">Add To Cart</button>
            </div>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end popup-shopping-cart" id="shoppingCart">
    @php
        $miniCart = $storefrontMiniCart ?? [
            'is_empty' => true,
            'shop_groups' => [],
            'subtotal' => 'INR 0.00',
            'total' => 'INR 0.00',
        ];
    @endphp
    <div class="canvas-wrapper" data-mini-cart>
        <div class="popup-header">
            <div class="d-flex align-items-center justify-content-between mb-12">
                <h5 class="title">Shopping Cart</h5>
                <span class="icon-X2 icon-close-popup" data-bs-dismiss="offcanvas"></span>
            </div>
        </div>
        <div class="wrap">
            <div class="tf-mini-cart-wrap list-file-delete wrap-empty_text">
                <div class="tf-mini-cart-main">
                    <div class="tf-mini-cart-sroll">
                        <div class="tf-mini-cart-items list-empty" data-mini-cart-items>
                            <div class="box-text_empty type-shop_cart" data-mini-cart-empty {{ $miniCart['is_empty'] ? '' : 'hidden' }}>
                                <div class="shop-empty_top">
                                    <span class="icon">
                                        <i class="icon-Handbag"></i>
                                    </span>
                                    <h4 class="text-emp">Your cart is empty</h4>
                                    <p class="cl-text-2">Your cart is currently empty. Let us assist you in finding the right product</p>
                                </div>
                                <div class="shop-empty_bot">
                                    <a href="{{ route('storefront.products') }}" class="tf-btn animate-btn">Shopping</a>
                                    <a href="{{ route('storefront.home') }}" class="tf-btn btn-stroke">Back to home</a>
                                </div>
                            </div>
                        @foreach ($miniCart['shop_groups'] as $shopGroup)
                            <div class="mini-cart-shop" data-mini-cart-shop="{{ $shopGroup['shop_id'] }}">
                                <div class="d-flex align-items-center justify-content-between mb-12">
                                    <p class="fw-semibold mb-0">{{ $shopGroup['shop_name'] }}</p>
                                    <span class="text-caption-01 cl-text-2" data-mini-shop-subtotal="{{ $shopGroup['shop_id'] }}">{{ $shopGroup['subtotal'] }}</span>
                                </div>
                                @foreach ($shopGroup['items'] as $item)
                                    <div class="tf-mini-cart-item file-delete" data-mini-cart-item="{{ $item['id'] }}" data-mini-cart-shop-id="{{ $shopGroup['shop_id'] }}">
                                        <a href="{{ $item['product_url'] }}" class="tf-mini-cart-image">
                                            <img loading="lazy" src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}">
                                        </a>
                                        <div class="tf-mini-cart-info">
                                            <a href="{{ $item['product_url'] }}" class="name fw-medium link text-line-clamp-1">{{ $item['product_name'] }}</a>
                                            @foreach ($item['attributes'] as $attribute)
                                                <div class="tf-prd-select text-caption-01 mb-4">
                                                    <span class="type-text cl-text-3">{{ $attribute['label'] }}:&nbsp;</span>
                                                    <span class="fw-medium">{{ $attribute['value'] }}</span>
                                                </div>
                                            @endforeach
                                            @if (! empty($item['availability_message']))
                                                <p class="text-caption-01 {{ $item['is_available'] ? 'cl-text-2' : 'text-danger' }} mb-0">{{ $item['availability_message'] ?: 'Currently unavailable.' }}</p>
                                            @endif
                                        </div>
                                        <div class="tf-mini-cart-price">
                                            <div class="fw-semibold d-flex align-items-center justify-content-between gap-4">
                                                <span class="number" data-mini-cart-item-quantity>{{ $item['quantity'] }}</span>
                                                <span>x</span>
                                                <span class="price" data-mini-cart-item-price>{{ $item['unit_price'] }}</span>
                                            </div>
                                            <div class="text-caption-01 cl-text-2 text-end" data-mini-cart-item-subtotal>{{ $item['line_subtotal'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                        </div>
                    </div>
                </div>
                <div class="tf-mini-cart-bottom box-empty_clear" data-mini-cart-filled {{ $miniCart['is_empty'] ? 'hidden' : '' }}>
                    <div class="tf-mini-cart-bottom-wrap">
                        <div class="tf-mini-cart-total">
                            <h5 class="text-total d-flex align-content-center justify-content-between">
                                <span class="subtotal">Sub-total</span>
                                <span class="total-price" data-mini-cart-subtotal>{{ $miniCart['subtotal'] }}</span>
                            </h5>
                        </div>
                        <div class="tf-mini-cart-view-checkout">
                            <a href="{{ route('storefront.cart') }}" class="tf-btn btn-stroke">
                                View cart
                            </a>
                            <a href="{{ route('storefront.checkout') }}" class="tf-btn animate-btn">
                                Check Out
                            </a>
                        </div>
                        <a href="{{ route('storefront.products') }}" class="d-flex justify-content-center fw-semibold text-center link">
                            Or Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        window.WindowShopMiniCart = window.WindowShopMiniCart || {};
        window.WindowShopMiniCart.sync = (payload) => {
            const miniCart = document.querySelector('[data-mini-cart]');

            if (!miniCart || !payload) {
                return;
            }

            const productsUrl = @json(route('storefront.products'));
            const homeUrl = @json(route('storefront.home'));
            document.querySelectorAll('[data-storefront-cart-count]').forEach((count) => {
                count.textContent = payload.cart_count || '0';
            });

            let empty = miniCart.querySelector('[data-mini-cart-empty]');
            const filled = miniCart.querySelector('[data-mini-cart-filled]');
            const subtotal = miniCart.querySelector('[data-mini-cart-subtotal]');
            const itemsWrap = miniCart.querySelector('[data-mini-cart-items]');

            if (subtotal && payload.subtotal) {
                subtotal.textContent = payload.subtotal;
            }

            if (payload.is_empty) {
                if (filled) {
                    filled.setAttribute('hidden', '');
                    filled.style.display = 'none';
                }
                if (itemsWrap) {
                    itemsWrap.innerHTML = `
                        <div class="box-text_empty type-shop_cart" data-mini-cart-empty>
                            <div class="shop-empty_top">
                                <span class="icon">
                                    <i class="icon-Handbag"></i>
                                </span>
                                <h4 class="text-emp">Your cart is empty</h4>
                                <p class="cl-text-2">Your cart is currently empty. Let us assist you in finding the right product</p>
                            </div>
                            <div class="shop-empty_bot">
                                <a class="tf-btn animate-btn">Shopping</a>
                                <a class="tf-btn btn-stroke">Back to home</a>
                            </div>
                        </div>
                    `;
                    itemsWrap.querySelector('.shop-empty_bot .tf-btn.animate-btn').href = productsUrl;
                    itemsWrap.querySelector('.shop-empty_bot .tf-btn.btn-stroke').href = homeUrl;
                    empty = itemsWrap.querySelector('[data-mini-cart-empty]');
                }
                if (empty) {
                    empty.removeAttribute('hidden');
                    empty.style.display = '';
                }
                return;
            }

            if (empty) {
                empty.setAttribute('hidden', '');
                empty.style.display = 'none';
            }

            if (filled) {
                filled.removeAttribute('hidden');
                filled.style.display = '';
            }

            if (itemsWrap) {
                itemsWrap.replaceChildren();
            }

            (payload.shop_groups || []).forEach((shop) => {
                if (!itemsWrap) {
                    return;
                }

                const shopWrap = document.createElement('div');
                shopWrap.className = 'mini-cart-shop';
                shopWrap.dataset.miniCartShop = shop.shop_id;
                shopWrap.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between mb-12">
                        <p class="fw-semibold mb-0"></p>
                        <span class="text-caption-01 cl-text-2" data-mini-shop-subtotal></span>
                    </div>
                `;
                shopWrap.querySelector('p').textContent = shop.shop_name || 'Shop';
                shopWrap.querySelector('[data-mini-shop-subtotal]').textContent = shop.subtotal || '';

                (shop.items || []).forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'tf-mini-cart-item file-delete';
                    row.dataset.miniCartItem = item.id;
                    row.dataset.miniCartShopId = shop.shop_id;
                    row.innerHTML = `
                        <a class="tf-mini-cart-image">
                            <img loading="lazy">
                        </a>
                        <div class="tf-mini-cart-info">
                            <a class="name fw-medium link text-line-clamp-1"></a>
                            <div data-mini-cart-attributes></div>
                            <p class="text-caption-01 text-danger mb-0" data-mini-cart-warning hidden></p>
                        </div>
                        <div class="tf-mini-cart-price">
                            <div class="fw-semibold d-flex align-items-center justify-content-between gap-4">
                                <span class="number" data-mini-cart-item-quantity></span>
                                <span>x</span>
                                <span class="price" data-mini-cart-item-price></span>
                            </div>
                            <div class="text-caption-01 cl-text-2 text-end" data-mini-cart-item-subtotal></div>
                        </div>
                    `;
                    row.querySelector('.tf-mini-cart-image').href = item.product_url || '#';
                    row.querySelector('.tf-mini-cart-image img').src = item.image || '';
                    row.querySelector('.tf-mini-cart-image img').alt = item.product_name || 'Product';
                    row.querySelector('.name').href = item.product_url || '#';
                    row.querySelector('.name').textContent = item.product_name || 'Product';
                    row.querySelector('[data-mini-cart-item-quantity]').textContent = item.quantity || '0';
                    row.querySelector('[data-mini-cart-item-price]').textContent = item.unit_price || '';
                    row.querySelector('[data-mini-cart-item-subtotal]').textContent = item.line_subtotal || '';

                    const attributesWrap = row.querySelector('[data-mini-cart-attributes]');
                    (item.attributes || []).forEach((attribute) => {
                        const attributeLine = document.createElement('div');
                        attributeLine.className = 'tf-prd-select text-caption-01 mb-4';
                        attributeLine.textContent = `${attribute.label}: ${attribute.value}`;
                        attributesWrap.append(attributeLine);
                    });

                    if (!item.is_available) {
                        const warning = row.querySelector('[data-mini-cart-warning]');
                        warning.textContent = item.availability_message || 'Currently unavailable.';
                        warning.hidden = false;
                    }

                    shopWrap.append(row);
                });

                itemsWrap.append(shopWrap);
            });
        };
    </script>
@endpush
