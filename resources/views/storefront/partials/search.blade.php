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
    <div class="canvas-wrapper">
        <div class="canvas-header">
            <h5 class="title">Shopping Cart</h5>
            <span class="icon-X2 icon-close-popup" data-bs-dismiss="offcanvas"></span>
        </div>
        <div class="canvas-body">
            <div class="box-text_empty type-shop_cart">
                <div class="shop-empty_top">
                    <p class="text-center cl-text-2 mb-0">Your cart is empty.</p>
                </div>
            </div>
        </div>
    </div>
</div>
