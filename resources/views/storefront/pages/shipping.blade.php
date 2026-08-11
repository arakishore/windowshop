@extends('storefront.layouts.app')

@section('title', 'Shipping | WindowShop')
@section('meta_description', 'WindowShop shipping information for local store orders and delivery expectations.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Shipping</p>
                </div>
                <h3>Shipping</h3>
            </div>
        </div>
    </section>

    <section class="section-term-user flat-spacing">
        <div class="container">
            <div class="content">
                <div class="term-item">
                    <h5 class="term-title">1. Shipping Methods</h5>
                    <p class="term-text cl-text-2">Shipping or delivery options may differ by merchant, area, product type, and order value. Available options should be confirmed before checkout or directly with the shop.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">2. Processing Time</h5>
                    <p class="term-text cl-text-2">Orders are usually processed during merchant working hours. Some products may need additional time for confirmation, packing, customization, or store-level availability checks.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">3. Shipping Costs</h5>
                    <p class="term-text cl-text-2">Delivery charges may depend on distance, package size, selected fulfilment method, merchant policy, and any active offer. Charges should be shown or confirmed before purchase.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">4. Local Pickup</h5>
                    <p class="term-text cl-text-2">Some merchants may offer store pickup. Customers should confirm pickup timing, order readiness, and required proof before visiting the store.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">5. Delivery Support</h5>
                    <p class="term-text cl-text-2">For delivery questions, contact the merchant first. WindowShop can help with platform-level support and routing where needed.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
