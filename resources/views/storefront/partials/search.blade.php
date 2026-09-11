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

@once
<style>
    .ws-drawer-ui .offcanvas-body,
    .ws-drawer-ui .wrap {
        background: #F4F4F5;
    }

    .ws-drawer-ui .ws-cart-title {
        font-size: 16px;
        color: #111;
    }

    .ws-drawer-ui .ws-cart-title-meta {
        font-size: 13px;
    }

    .ws-drawer-ui .ws-cart-close {
        width: 36px;
        height: 36px;
    }

    .ws-drawer-ui .ws-promo-title {
        color: #111;
    }

    .ws-drawer-ui .ws-card-side {
        border: 1px solid #E5E7EB;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
        flex: 0 0 auto;
    }

    .ws-drawer-ui .tf-mini-cart-items {
        height: auto;
        min-height: 100%;
    }

    .ws-drawer-ui .ws-progress {
        height: 6px;
        background: #E5E7EB;
        border-radius: 99px;
        overflow: hidden;
    }

    .ws-drawer-ui .ws-progress span {
        display: block;
        height: 100%;
        background: #111;
        border-radius: 99px;
        transition: width .4s;
    }

    .ws-drawer-ui .text-xxs {
        font-size: 11px;
    }

    .ws-drawer-ui .btn-ws-dark {
        height: 46px;
        background: #111;
        color: #fff;
        border-radius: 99px;
        font-weight: 700;
        font-size: 15px;
    }

    .ws-drawer-ui .btn-ws-dark:hover {
        background: #000;
        color: #fff;
    }

    .ws-drawer-ui .ws-shop-head {
        min-height: 42px;
    }

    .ws-drawer-ui .ws-shop-avatar {
        width: 28px;
        height: 28px;
        font-size: 11px;
        flex: 0 0 28px;
    }

    .ws-drawer-ui .ws-shop-avatar.is-violet {
        background: #F3EBFF;
        border: 1px solid #E8D9FF;
        color: #7C3AED;
    }

    .ws-drawer-ui .ws-shop-avatar.is-rose {
        background: #FFF1F2;
        border: 1px solid #FFE4E6;
        color: #E11D48;
    }

    .ws-drawer-ui .ws-shop-name {
        color: #111;
        min-width: 0;
    }

    .ws-drawer-ui .ws-count-badge,
    .ws-drawer-ui .ws-saved-badge,
    .ws-drawer-ui .ws-tip-badge {
        font-size: 10px;
        white-space: nowrap;
    }

    .ws-drawer-ui .ws-saved-badge {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0 !important;
    }

    .ws-drawer-ui .ws-tip-badge {
        background: #FFFBEB;
        color: #92400E;
        border-color: #FDE68A !important;
    }

    .ws-drawer-ui .ws-mini-item {
        min-height: 68px;
    }

    .ws-drawer-ui .ws-mini-thumb {
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
    }

    .ws-drawer-ui .ws-mini-thumb img,
    .ws-drawer-ui img.ws-item-img {
        width: 52px !important;
        height: 52px !important;
        max-width: 52px !important;
        min-width: 52px !important;
        max-height: 52px !important;
        min-height: 52px !important;
        aspect-ratio: 1 / 1 !important;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #F3F4F6;
        background: #F3F4F6;
        display: block;
    }

    .ws-drawer-ui .ws-thumb-fallback {
        background: #E5E7EB;
    }

    .ws-drawer-ui .ws-mini-info {
        min-width: 0;
    }

    .ws-drawer-ui .ws-mini-title {
        color: #111;
        font-size: 13px;
    }

    .ws-drawer-ui .ws-mini-meta,
    .ws-drawer-ui .ws-trust-row {
        font-size: 11px;
    }

    .ws-drawer-ui .ws-mini-saving {
        color: #047857;
        font-size: 11px;
        line-height: 1.15;
        text-align: right;
    }

    .ws-drawer-ui .ws-mini-saving-label {
        display: block;
        color: #059669;
        font-size: 10px;
        font-weight: 600;
        text-transform: lowercase;
    }

    .ws-drawer-ui .ws-summary-row {
        font-size: 13px;
    }

    .ws-drawer-ui .ws-total-row {
        font-size: 17px;
        color: #111;
    }
</style>
@endonce
<div class="offcanvas offcanvas-end popup-shopping-cart ws-drawer-ui" id="shoppingCart">
    @php
        $miniCart = $storefrontMiniCart ?? ['is_empty' => true,'shop_groups' => [],'subtotal' => 'INR 0.00','total' => 'INR 0.00','base_subtotal' => 'INR 0.00','promotion_discount' => 'None','promotion_discount_cents' => 0];
        $miniCartCount = $cartCount ?? ($storefrontCartCount ?? collect($miniCart['shop_groups'] ?? [])->flatMap(fn($g) => $g['items'] ?? [])->sum(fn($i) => (int)($i['quantity_value'] ?? $i['quantity'] ?? 0)));
        $miniShopCount = count($miniCart['shop_groups'] ?? []);
    @endphp
    <div class="canvas-wrapper d-flex flex-column h-100" data-mini-cart>
        <div class="popup-header p-3 d-flex align-items-center justify-content-between border-bottom">
            <div>
                <div class="fw-bold" style="font-size:16px;color:#111;">Your Cart <span class="fw-normal text-secondary" style="font-size:13px;">· <span data-mini-cart-count>{{ $miniCartCount }} items</span> · {{ $miniShopCount }} shops</span></div>
                <div class="text-xxs text-secondary">Checkout is per shop — pick a shop to continue</div>
            </div>
            <span class="icon-X2 icon-close-popup btn btn-light rounded-circle p-0 d-flex align-items-center justify-content-center ws-cart-close" data-bs-dismiss="offcanvas"></span>
        </div>
         
        <div class="wrap flex-grow-1 overflow-auto">
            <div class="tf-mini-cart-wrap list-file-delete wrap-empty_text">
                <div class="tf-mini-cart-main">
                    <div class="tf-mini-cart-sroll">
                        <div class="tf-mini-cart-items list-empty p-3 d-flex flex-column gap-3" data-mini-cart-items>
                            <div class="box-text_empty type-shop_cart" data-mini-cart-empty {{ $miniCart['is_empty'] ? '' : 'hidden' }}>
                                <div class="shop-empty_top text-center">
                                    <span class="icon"><i class="icon-Handbag"></i></span>
                                    <h4 class="text-emp">Your cart is empty</h4>
                                    <p class="cl-text-2">Your cart is currently empty. Let us assist you in finding the right product</p>
                                </div>
                                <div class="shop-empty_bot d-flex gap-2 justify-content-center">
                                    <a href="{{ route('storefront.products') }}" class="tf-btn animate-btn">Shopping</a>
                                    <a href="{{ route('storefront.home') }}" class="tf-btn btn-stroke">Back to home</a>
                                </div>
                            </div>
                        @foreach ($miniCart['shop_groups'] as $shopGroup)
                            @php
                                $initials = implode('', array_map(fn($w) => mb_substr($w,0,1), array_slice(preg_split('/\s+/', trim($shopGroup['shop_name'] ?? 'S')),0,2)));
                                $loopIdx = $loop->index;
                                $avatarBg = $loopIdx % 2 === 0 ? '#F3EBFF;border-color:#E8D9FF;color:#7C3AED' : '#FFF1F2;border-color:#FFE4E6;color:#E11D48';
                                $groupCount = count($shopGroup['items'] ?? []);
                            @endphp
                            <div class="ws-card-side" data-mini-cart-shop="{{ $shopGroup['shop_id'] }}">
                                <div class="px-3 py-2 d-flex align-items-center justify-content-between bg-white border-bottom">
                                    <div class="d-flex align-items-center gap-2"><span class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:28px;height:28px;font-size:11px;background:{{ explode(';', $avatarBg)[0] }};border:1px solid {{ explode(';', $avatarBg)[1] ?? '#E8D9FF' }};color:{{ explode(';', $avatarBg)[2] ?? '#7C3AED' }};">{{ strtoupper($initials) }}</span><span class="small fw-bold" style="color:#111;">{{ $shopGroup['shop_name'] }}</span><span class="badge bg-light text-secondary border fw-normal" style="font-size:10px;">{{ $groupCount }} {{ Str::plural('item', $groupCount) }}</span></div>
                                    @if(($shopGroup['promotion_discount_cents'] ?? 0) > 0)<span class="badge rounded-pill border" style="background:#ECFDF5;color:#065F46;border-color:#A7F3D0 !important;font-size:10px;">{{ ltrim($shopGroup['promotion_discount'] ?? '', '-') }} saved</span>
                                    @elseif(!empty($shopGroup['coupon']['code']))<span class="badge rounded-pill border" style="background:#FFFBEB;color:#92400E;border-color:#FDE68A !important;font-size:10px;">Tip: {{ $shopGroup['coupon']['code'] }}</span>@endif
                                </div>
                                @foreach ($shopGroup['items'] as $item)
                                    <div class="p-2 px-3 d-flex gap-2 align-items-center bg-white ws-mini-item {{ !$loop->last ? 'border-bottom' : '' }}" data-mini-cart-item="{{ $item['id'] }}" data-mini-cart-shop-id="{{ $shopGroup['shop_id'] }}">
                                        <a href="{{ $item['product_url'] }}" class="ws-mini-thumb"><img loading="lazy" src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}" class="ws-item-img {{ empty($item['image']) ? 'ws-thumb-fallback' : '' }}"></a>
                                        <div class="flex-grow-1 ws-mini-info">
                                            <a href="{{ $item['product_url'] }}" class="fw-bold text-truncate d-block link" style="color:#111;font-size:13px;">{{ $item['product_name'] }}</a>
                                            <div class="text-secondary" style="font-size:11px;">Qty <span data-mini-cart-item-quantity>{{ $item['quantity'] }}</span> · <span data-mini-cart-item-price>{{ $item['line_subtotal'] }}</span>@if(!empty($item['base_line_subtotal']) && ($item['promotion_discount_cents'] ?? 0) > 0)<span class="text-decoration-line-through ms-1">{{ $item['base_line_subtotal'] }}</span>@endif @if(!empty($item['is_generated_gift']))· <span class="text-success fw-bold">FREE</span>@endif</div>
                                            @if(!empty($item['availability_message']) && !($item['is_available'] ?? true))<p class="text-xxs text-danger mb-0">{{ $item['availability_message'] }}</p>@endif
                                        </div>
                                        @if(($item['promotion_discount_cents'] ?? 0) > 0)<span class="flex-shrink-0 ws-mini-saving" data-mini-cart-item-subtotal>{{ ltrim($item['promotion_discount'], '-') }}<span class="ws-mini-saving-label">saved</span></span>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-3 bg-white border-top" data-mini-cart-filled {{ $miniCart['is_empty'] ? 'hidden' : '' }}>
            <div class="d-flex justify-content-between ws-summary-row"><span class="text-secondary">Subtotal (<span data-mini-cart-count>{{ $miniCartCount }} items</span>)</span><span class="fw-semibold" data-mini-cart-base>{{ $miniCart['base_subtotal'] ?? $miniCart['subtotal'] }}</span></div>
            <div class="d-flex justify-content-between text-success ws-summary-row" @if(($miniCart['promotion_discount_cents'] ?? 0) <= 0) hidden @endif><span>Offer savings</span><span data-mini-cart-savings>{{ $miniCart['promotion_discount'] }}</span></div>
            <div class="d-flex justify-content-between fw-bold mt-1 ws-total-row"><span>Total</span><span data-mini-cart-subtotal>{{ $miniCart['total'] ?? $miniCart['subtotal'] }}</span></div>
            <a href="{{ route('storefront.cart') }}" class="btn btn-ws-dark w-100 mt-3 d-flex align-items-center justify-content-center">View full cart</a>
            <div class="d-flex align-items-center justify-content-center gap-2 text-secondary mt-2" style="font-size:11px;"><span>🔒 Secure</span>·<span>↺ Easy exchanges</span>·<span>💳 Cards & UPI</span></div>
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
            const filledEls = miniCart.querySelectorAll('[data-mini-cart-filled],[data-mini-cart-filled-promo]');
            const subtotal = miniCart.querySelector('[data-mini-cart-subtotal]');
            const baseEl = miniCart.querySelector('[data-mini-cart-base]');
            const savingsEl = miniCart.querySelector('[data-mini-cart-savings]');
            const itemsWrap = miniCart.querySelector('[data-mini-cart-items]');
            const checkoutUrl = @json(route('storefront.checkout'));
            const cartUrl = @json(route('storefront.cart'));

            if (subtotal && (payload.total || payload.subtotal)) subtotal.textContent = payload.total || payload.subtotal;
            if (baseEl && payload.base_subtotal) baseEl.textContent = payload.base_subtotal;
            if (savingsEl && payload.promotion_discount) savingsEl.textContent = payload.promotion_discount;
            miniCart.querySelectorAll('[data-mini-cart-count]').forEach((el) => { el.textContent = (payload.cart_count || '0') + ' items'; });

            if (payload.is_empty) {
                filledEls.forEach((f) => { f.setAttribute('hidden', ''); f.style.display = 'none'; });
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

            filledEls.forEach((f) => { f.removeAttribute('hidden'); f.style.display = ''; });

            if (itemsWrap) itemsWrap.replaceChildren();

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            (payload.shop_groups || []).forEach((shop) => {
                if (!itemsWrap) return;
                const initials = String(shop.shop_name || 'S').trim().split(/\s+/).slice(0,2).map((w) => w.charAt(0)).join('').toUpperCase();
                const shopWrap = document.createElement('div');
                shopWrap.className = 'ws-card-side';
                shopWrap.dataset.miniCartShop = shop.shop_id;
                const savedBadge = (shop.promotion_discount_cents > 0) ? `<span class="badge rounded-pill border" style="background:#ECFDF5;color:#065F46;border-color:#A7F3D0 !important;font-size:10px;">${esc(String(shop.promotion_discount || '').replace(/^-/, ''))} saved</span>` : ((shop.coupon && shop.coupon.code) ? `<span class="badge rounded-pill border" style="background:#FFFBEB;color:#92400E;border-color:#FDE68A !important;font-size:10px;">Tip: ${esc(shop.coupon.code)}</span>` : '');
                shopWrap.innerHTML = `<div class="px-3 py-2 d-flex align-items-center justify-content-between bg-white border-bottom"><div class="d-flex align-items-center gap-2"><span class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:28px;height:28px;background:#F3EBFF;border:1px solid #E8D9FF;color:#7C3AED;font-size:11px;">${esc(initials)}</span><span class="small fw-bold" style="color:#111;">${esc(shop.shop_name || 'Shop')}</span><span class="badge bg-light text-secondary border fw-normal" style="font-size:10px;">${(shop.items || []).length} items</span></div>${savedBadge}</div><div data-shop-items></div>`;
                const list = shopWrap.querySelector('[data-shop-items]');
                (shop.items || []).forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'p-2 px-3 d-flex gap-2 align-items-center bg-white border-bottom ws-mini-item';
                    row.dataset.miniCartItem = item.id; row.dataset.miniCartShopId = shop.shop_id;
                    const strike = (item.promotion_discount_cents > 0 && item.base_line_subtotal) ? `<span class="text-decoration-line-through ms-1">${esc(item.base_line_subtotal)}</span>` : '';
                    const itemSavings = esc(String(item.promotion_discount || '').replace(/^-/, ''));
                    row.innerHTML = `<a href="${esc(item.product_url || '#')}" class="ws-mini-thumb"><img loading="lazy" src="${esc(item.image || '')}" alt="${esc(item.product_name || 'Product')}" class="ws-item-img"></a><div class="flex-grow-1 ws-mini-info"><a href="${esc(item.product_url || '#')}" class="fw-bold text-truncate d-block link ws-mini-title">${esc(item.product_name || 'Product')}</a><div class="text-secondary ws-mini-meta">Qty ${esc(item.quantity || '0')} · ${esc(item.line_subtotal || '')} ${strike}${item.is_generated_gift ? ' · <span class="text-success fw-bold">FREE</span>' : ''}</div></div>${(item.promotion_discount_cents > 0) ? `<span class="flex-shrink-0 ws-mini-saving">${itemSavings}<span class="ws-mini-saving-label">saved</span></span>` : ''}`;
                    list.append(row);
                });
                itemsWrap.append(shopWrap);
            });
        };
    </script>
@endpush
