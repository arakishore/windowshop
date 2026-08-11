@extends('storefront.layouts.app')

@section('title', 'Shopping Cart | WindowShop')
@section('meta_description', 'Review your selected local shop products before checkout on WindowShop.')

@php
    $money = fn(float $amount): string => '$' . number_format($amount, 2);
    $productImage = fn(string $image): string => asset('assets/storefront/images/product/' . $image);
    $relatedProducts = [
        ['name' => 'Lyocell wrap top', 'image' => 'product-1.jpg', 'hover_image' => 'product-1_2.jpg', 'price' => '$69,99', 'old_price' => '$99,99', 'badge' => 'NEW'],
        ['name' => 'Buttons cotton top', 'image' => 'product-2.jpg', 'hover_image' => 'product-2_2.jpg', 'price' => '$29,99', 'old_price' => '$49,99', 'badge' => '-25%'],
        ['name' => 'Wool Midi Coat', 'image' => 'product-4.jpg', 'hover_image' => 'product-4_2.jpg', 'price' => '$45,99', 'old_price' => '$79,99', 'badge' => null],
        ['name' => 'Ribbed knit top', 'image' => 'product-5.jpg', 'hover_image' => 'product-5_2.jpg', 'price' => '$39,99', 'old_price' => '$59,99', 'badge' => 'NEW'],
    ];
@endphp

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <h1>Shopping Cart</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Shopping Cart</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-shoping-cart each-list-prd flat-spacing-2 pb-0">
        <div class="flat-spacing-2 pt-0">
            <div class="container">
                <div class="tf-cart-notification">
                    <div class="count-text">
                        <div class="ic">
                            <i class="icon icon-Timer"></i>
                        </div>
                        <div>
                            Your cart is saved for now. Review store availability before checkout.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <form class="form-shop-cart">
                        <div class="overflow-auto">
                            <table class="tf-table-page-cart">
                                <thead>
                                    <tr>
                                        <th>
                                            <p class="h6 fw-medium">Products</p>
                                        </th>
                                        <th>
                                            <p class="h6 fw-medium">Price</p>
                                        </th>
                                        <th>
                                            <p class="h6 fw-medium">Quantity</p>
                                        </th>
                                        <th class="text-end">
                                            <p class="h6 fw-medium">Total Price</p>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cartItems as $item)
                                        <tr class="tf-cart_item each-prd file-delete">
                                            <td class="cart_product">
                                                <a href="{{ route('storefront.product.detail') }}" class="img-prd">
                                                    <img loading="lazy" width="100" height="133"
                                                        src="{{ $productImage($item['image']) }}"
                                                        alt="{{ $item['name'] }}">
                                                </a>
                                                <div class="infor-prd">
                                                    <a href="{{ route('storefront.product.detail') }}"
                                                        class="prd_name fw-medium link lh-24">
                                                        {{ $item['name'] }}
                                                    </a>
                                                    <div class="prd_select text-caption-01">
                                                        <span class="type-text cl-text-3">Color:&nbsp;</span>
                                                        <div class="type-select">
                                                            <select class="bg-white">
                                                                @foreach (['Light Gray', 'Charcoal', 'Beige', 'Taupe', 'Sage'] as $color)
                                                                    <option {{ $color === $item['color'] ? 'selected' : '' }}>
                                                                        {{ $color }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="prd_select text-caption-01">
                                                        <span class="type-text cl-text-3">Size:&nbsp;</span>
                                                        <div class="type-select">
                                                            <select class="bg-white">
                                                                @foreach (['Small', 'Medium', 'Large', 'Extra Large', 'One Size'] as $size)
                                                                    <option {{ $size === $item['size'] ? 'selected' : '' }}>
                                                                        {{ $size }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="cart_remove tf-btn-line-3 type-primary remove">
                                                        <span class="text-caption-01 fw-semibold">Remove</span>
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="cart_price each-price fw-semibold text-primary" data-cart-title="Price">
                                                {{ $money($item['price']) }}
                                            </td>
                                            <td class="cart_quantity" data-cart-title="Quantity">
                                                <div class="wg-quantity">
                                                    <button type="button" class="btn-quantity minus-quantity">
                                                        <i class="icon icon-minus"></i>
                                                    </button>
                                                    <input class="quantity-product" type="text" name="number"
                                                        value="{{ $item['quantity'] }}">
                                                    <button type="button" class="btn-quantity plus-quantity">
                                                        <i class="icon icon-plus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cart_total fw-semibold text-primary each-subtotal-price">
                                                    {{ $money($item['price'] * $item['quantity']) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ip-discount-code">
                            <input type="text" placeholder="Add voucher discount">
                            <button class="tf-btn animate-btn" type="submit">Apply Code</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="fl-sidebar-cart mt-lg-0 sticky-top">
                        <div class="box-order-summary">
                            <div class="notification-progress">
                                <p>
                                    Buy
                                    <span class="text-primary fw-bold">{{ $money($freeShippingRemaining) }}</span>
                                    more to unlock free local delivery
                                </p>
                                <div class="progress-cart">
                                    <div class="value" style="width: 50%;" data-progress="50">
                                        <span class="round"></span>
                                    </div>
                                </div>
                            </div>

                            <h5 class="title mb-20">Order Summary</h5>
                            <div class="subtotal d-flex justify-content-between align-items-center">
                                <p class="fw-medium lh-24">Subtotal</p>
                                <span class="total fw-medium lh-24">{{ $money($subtotal) }}</span>
                            </div>
                            <div class="discount d-flex justify-content-between align-items-center">
                                <p class="fw-medium lh-24">Discounts</p>
                                <span class="total fw-medium lh-24">-{{ $money($discount) }}</span>
                            </div>
                            <div class="ship">
                                <p class="fw-medium lh-24">Shipping</p>
                                <div class="box-check-payment flex-grow-1">
                                    <fieldset class="ship-item">
                                        <input type="radio" name="ship-check" class="tf-check-rounded" id="free" checked>
                                        <label for="free">
                                            <span>Store Pickup</span>
                                            <span class="price">{{ $money(0) }}</span>
                                        </label>
                                    </fieldset>
                                    <fieldset class="ship-item">
                                        <input type="radio" name="ship-check" class="tf-check-rounded" id="local">
                                        <label for="local">
                                            <span>Local Delivery</span>
                                            <span class="price">{{ $money(35) }}</span>
                                        </label>
                                    </fieldset>
                                    <fieldset class="ship-item">
                                        <input type="radio" name="ship-check" class="tf-check-rounded" id="rate">
                                        <label for="rate">
                                            <span>Flat Rate</span>
                                            <span class="price">{{ $money(35) }}</span>
                                        </label>
                                    </fieldset>
                                </div>
                            </div>

                            <h5 class="total-order d-flex justify-content-between align-items-center">
                                <span>Total</span>
                                <span class="total each-total-price">{{ $money($total) }}</span>
                            </h5>

                            <fieldset class="checkbox-wrap check-agree">
                                <input type="checkbox" name="agree" class="tf-check-rounded" id="checkOutAgree">
                                <label for="checkOutAgree">
                                    I agree with the
                                    <a href="{{ route('storefront.terms') }}" class="fw-medium text-decoration-underline link">
                                        terms and conditions
                                    </a>
                                </label>
                            </fieldset>

                            <div class="list-ver text-center">
                                <a href="{{ route('storefront.checkout') }}" id="checkout-btn"
                                    class="action-checkout tf-btn w-100 animate-btn">
                                    <span class="fw-semibold">Proceed To Checkout</span>
                                </a>
                                <a href="{{ route('storefront.products') }}" class="link-underline link">
                                    <span class="fw-semibold">Or Continue Shopping</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading">
                <h4>You may be interested in...</h4>
            </div>
            <div class="tf-grid-layout tf-col-4">
                @foreach ($relatedProducts as $product)
                    <div class="card-product grid">
                        <div class="card-product_wrapper">
                            <a href="{{ route('storefront.product.detail') }}" class="product-img">
                                <img class="img-product" loading="lazy" width="330" height="440"
                                    src="{{ $productImage($product['image']) }}" alt="{{ $product['name'] }}">
                                <img class="img-hover" loading="lazy" width="330" height="440"
                                    src="{{ $productImage($product['hover_image']) }}" alt="{{ $product['name'] }}">
                            </a>
                            <ul class="product-action_list">
                                <li class="wishlist">
                                    <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                        <span class="icon icon-heart"></span>
                                        <span class="tooltip">Add to Wishlist</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                        <span class="icon icon-Eye"></span>
                                        <span class="tooltip">Quick view</span>
                                    </a>
                                </li>
                            </ul>
                            @if ($product['badge'])
                                <ul class="product-badge_list">
                                    <li class="product-badge_item text-caption-01 {{ $product['badge'] === 'NEW' ? 'new' : 'sale' }}">
                                        {{ $product['badge'] }}
                                    </li>
                                </ul>
                            @endif
                            <div class="product-action_bot">
                                <a href="#shoppingCart" data-bs-toggle="offcanvas" class="tf-btn btn-white small w-100">
                                    Add to cart
                                </a>
                            </div>
                        </div>
                        <div class="card-product_info">
                            <a href="{{ route('storefront.product.detail') }}"
                                class="name-product lh-24 fw-medium link-underline-text">{{ $product['name'] }}</a>
                            <div class="star-wrap d-flex align-items-center">
                                @for ($i = 0; $i < 5; $i++)
                                    <i class="icon icon-Star"></i>
                                @endfor
                            </div>
                            <div class="price-wrap">
                                <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                                <span class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
