@extends('storefront.layouts.app')

@section('title', 'Checkout | WindowShop')
@section('meta_description', 'Complete checkout for selected local shop products on WindowShop.')

@php
    $money = fn(float $amount): string => '$' . number_format($amount, 2);
    $productImage = fn(string $image): string => asset('assets/storefront/images/product/' . $image);
    $paymentImage = fn(string $image): string => asset('assets/storefront/images/payment/' . $image);
@endphp

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <h1>Check Out</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('storefront.cart') }}" class="text-caption-01 cl-text-3 link">Shopping Cart</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Check Out</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-checkout flat-spacing-2">
        <div class="flat-spacing-2 pt-0">
            <div class="container">
                <div class="tf-cart-notification">
                    <div class="count-text">
                        <div class="ic">
                            <i class="icon icon-Timer"></i>
                        </div>
                        <div>
                            Review customer details and store fulfilment before placing the order.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <div class="tf-page-checkout mb-lg-0">
                        <div class="wrap-quick-login">
                            <p class="title cl-text-2">
                                Already have an account?
                                <a href="#sign" data-bs-toggle="modal" class="tf-btn-line-2 style-primary fw-semibold">
                                    Login Here
                                </a>
                                </p>
                                <form class="form-quick-login">
                                    <div class="tf-grid-layout sm-col-2">
                                        <input type="text" placeholder="Your username">
                                        <input type="password" placeholder="Password">
                                </div>
                                <button class="action tf-btn animate-btn small fw-semibold" type="submit">Login</button>
                            </form>
                        </div>

                        <form class="tf-checkout-cart-main">
                            <div class="box-ip-checkout estimate-shipping">
                                <div class="h5 title">Delivery Address</div>
                                <div class="form-content">
                                    <div class="tf-grid-layout sm-col-2">
                                        <input type="text" placeholder="First Name*" required>
                                        <input type="text" placeholder="Last Name*" required>
                                    </div>
                                    <input type="tel" placeholder="Phone Number*" required>
                                    <fieldset>
                                        <div class="tf-select">
                                            <select class="w-100" id="shipping-country-form" name="address[country]">
                                                <option disabled value="">Choose Country / Region</option>
                                                <option value="India" selected>India</option>
                                                <option value="United States">United States</option>
                                                <option value="United Kingdom">United Kingdom</option>
                                                <option value="Canada">Canada</option>
                                                <option value="Australia">Australia</option>
                                            </select>
                                        </div>
                                    </fieldset>
                                    <div class="tf-grid-layout sm-col-2">
                                        <input type="text" placeholder="Town/City*" required>
                                        <input type="text" placeholder="Street, Area, Landmark*" required>
                                    </div>
                                    <div class="tf-grid-layout sm-col-2">
                                        <div class="tf-select">
                                            <select id="shipping-province-form" name="address[province]">
                                                <option disabled value="">Choose State</option>
                                                <option selected>Gujarat</option>
                                                <option>Maharashtra</option>
                                                <option>Rajasthan</option>
                                                <option>Delhi</option>
                                                <option>Karnataka</option>
                                            </select>
                                        </div>
                                        <input type="text" placeholder="Postal Code*" required>
                                    </div>
                                    <fieldset class="d-grid">
                                        <textarea placeholder="Order note for shop or delivery..."></textarea>
                                    </fieldset>
                                </div>
                            </div>

                            <div class="box-ip-payment">
                                <h5 class="title">Billing Address</h5>
                                <div class="payment-method-box" id="billing-address-box">
                                    <div class="payment_accordion active">
                                        <label for="billing-same" class="payment_check checkbox-wrap"
                                            data-bs-toggle="collapse" data-bs-target="#billing-same-panel">
                                            <input type="radio" name="billing-address" class="tf-check-rounded style-2"
                                                id="billing-same" checked>
                                            <span class="pay-title fw-medium">Same as shipping address</span>
                                        </label>
                                        <div id="billing-same-panel" class="collapse show"
                                            data-bs-parent="#billing-address-box"></div>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="billing-different" class="payment_check checkbox-wrap collapsed"
                                            data-bs-toggle="collapse" data-bs-target="#billing-different-panel">
                                            <input type="radio" name="billing-address" class="tf-check-rounded style-2"
                                                id="billing-different">
                                            <span class="pay-title fw-medium">Use a different billing address</span>
                                        </label>
                                        <div id="billing-different-panel" class="collapse"
                                            data-bs-parent="#billing-address-box">
                                            <div class="payment_body form-content">
                                                <div class="tf-grid-layout sm-col-2">
                                                    <input type="text" placeholder="First Name*">
                                                    <input type="text" placeholder="Last Name*">
                                                </div>
                                                <input type="tel" placeholder="Phone Number*">
                                                <fieldset>
                                                    <div class="tf-select">
                                                        <select class="w-100" name="billing[country]">
                                                            <option disabled value="">Choose Country / Region</option>
                                                            <option value="India" selected>India</option>
                                                            <option value="United States">United States</option>
                                                            <option value="United Kingdom">United Kingdom</option>
                                                            <option value="Canada">Canada</option>
                                                            <option value="Australia">Australia</option>
                                                        </select>
                                                    </div>
                                                </fieldset>
                                                <div class="tf-grid-layout sm-col-2">
                                                    <input type="text" placeholder="Town/City*">
                                                    <input type="text" placeholder="Street, Area, Landmark*">
                                                </div>
                                                <div class="tf-grid-layout sm-col-2">
                                                    <div class="tf-select">
                                                        <select name="billing[state]">
                                                            <option disabled value="">Choose State</option>
                                                            <option selected>Gujarat</option>
                                                            <option>Maharashtra</option>
                                                            <option>Rajasthan</option>
                                                            <option>Delhi</option>
                                                            <option>Karnataka</option>
                                                        </select>
                                                    </div>
                                                    <input type="text" placeholder="Postal Code*">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="box-ip-payment">
                                <h5 class="title">Choose Payment Option:</h5>
                                <div class="payment-method-box" id="payment-method-box">
                                    <div class="payment_accordion type-2">
                                        <label for="credit-card" class="payment_check checkbox-wrap"
                                            data-bs-toggle="collapse" data-bs-target="#credit-card-payment">
                                            <input type="radio" name="payment-method" class="tf-check-rounded style-2"
                                                id="credit-card" checked>
                                            <span class="pay-title fw-medium lh-24">Credit card</span>
                                        </label>
                                        <div id="credit-card-payment" class="collapse show"
                                            data-bs-parent="#payment-method-box">
                                            <div class="payment_body form-content">
                                                <p class="text-payment cl-text-2">
                                                    Static checkout placeholder. Real payment gateway fields will be
                                                    connected later.
                                                </p>
                                                <fieldset>
                                                    <input type="text" placeholder="Name On Card*">
                                                </fieldset>
                                                <fieldset class="ip-card">
                                                    <input type="text" placeholder="Card Number*">
                                                    <div class="card-logo d-none d-sm-flex">
                                                        @foreach (['visa.svg', 'master-card.svg', 'amex.svg', 'paypal.svg', 'water.svg', 'discover.svg'] as $card)
                                                            <img width="38" height="24" src="{{ $paymentImage($card) }}"
                                                                alt="Payment Logo">
                                                        @endforeach
                                                    </div>
                                                </fieldset>
                                                <div class="tf-grid-layout sm-col-2">
                                                    <input type="text" placeholder="MM / YY">
                                                    <input type="text" placeholder="CVV*">
                                                </div>
                                                <div class="checkbox-wrap">
                                                    <input id="save-card" type="checkbox" class="tf-check style-2">
                                                    <label for="save-card" class="fw-medium lh-24">Save Card Details</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="cash-on" class="payment_check checkbox-wrap collapsed"
                                            data-bs-toggle="collapse" data-bs-target="#cash-on-payment">
                                            <input type="radio" name="payment-method" class="tf-check-rounded style-2"
                                                id="cash-on">
                                            <span class="pay-title fw-medium">Cash On Delivery</span>
                                        </label>
                                        <div id="cash-on-payment" class="collapse" data-bs-parent="#payment-method-box">
                                            <div class="payment_body form-content">
                                                <p class="text-payment cl-text-2">
                                                    Pay the shop directly when the order is delivered or picked up.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="upi-payment" class="payment_check checkbox-wrap collapsed"
                                            data-bs-toggle="collapse" data-bs-target="#upi-payment-box">
                                            <input type="radio" name="payment-method" class="tf-check-rounded style-2"
                                                id="upi-payment">
                                            <span class="pay-title fw-medium">UPI / Wallet</span>
                                        </label>
                                        <div id="upi-payment-box" class="collapse" data-bs-parent="#payment-method-box">
                                            <div class="payment_body form-content">
                                                <p class="text-payment cl-text-2">
                                                    UPI and wallet integrations can be enabled when payment setup is ready.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="payment_accordion">
                                        <label for="paypal" class="payment_check checkbox-wrap collapsed"
                                            data-bs-toggle="collapse" data-bs-target="#paypal-payment">
                                            <input type="radio" name="payment-method" class="tf-check-rounded style-2"
                                                id="paypal">
                                            <span class="pay-title fw-medium">
                                                <img loading="lazy" width="60" height="15"
                                                    src="{{ $paymentImage('paypal-2.svg') }}" alt="Paypal">
                                            </span>
                                        </label>
                                        <div id="paypal-payment" class="collapse" data-bs-parent="#payment-method-box"></div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="tf-btn animate-btn w-100">Place Order</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="fl-sidebar-cart type-2 mt-lg-0 sticky-top">
                        <div class="box-your-order">
                            <h5 class="title">Shopping Cart</h5>
                            <ul class="list-order-product">
                                @foreach ($cartItems as $item)
                                    <li class="order-item fw-medium">
                                        <a href="{{ route('storefront.product.detail') }}" class="img-prd">
                                            <img loading="lazy" width="100" height="133"
                                                src="{{ $productImage($item['image']) }}" alt="{{ $item['name'] }}">
                                        </a>
                                        <div class="infor-prd">
                                            <a href="{{ route('storefront.product.detail') }}"
                                                class="prd_name fw-medium lh-24 link link-underline">
                                                {{ $item['name'] }}
                                            </a>
                                            <div class="text-caption-01">
                                                <span class="cl-text-2">Color:</span>
                                                {{ $item['color'] }}
                                            </div>
                                            <div class="text-caption-01">
                                                <span class="cl-text-2">Size:</span>
                                                {{ $item['size'] }}
                                            </div>
                                        </div>
                                        <div class="quantity-price text-primary">
                                            {{ $money($item['price'] * $item['quantity']) }}
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            <form class="ip-discount-code">
                                <input type="text" placeholder="Add voucher discount">
                                <button class="action tf-btn animate-btn" type="submit">Apply Code</button>
                            </form>

                            <ul class="list-total">
                                <li class="total-item lh-24 fw-medium">
                                    <span>Subtotal</span>
                                    <span>{{ $money($subtotal) }}</span>
                                </li>
                                <li class="total-item lh-24 fw-medium">
                                    <span>Shipping</span>
                                    <span>{{ $shipping <= 0 ? 'Free' : $money($shipping) }}</span>
                                </li>
                                <li class="total-item lh-24 fw-medium">
                                    <span>Discounts</span>
                                    <span>-{{ $money($discount) }}</span>
                                </li>
                            </ul>

                            <div class="last-total h5 fw-medium">
                                <span>Total</span>
                                <span>{{ $money($total) }}</span>
                            </div>

                            <a href="{{ route('storefront.cart') }}" class="link-underline link">
                                <span class="fw-semibold">Back to cart</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
