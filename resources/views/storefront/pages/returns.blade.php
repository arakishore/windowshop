@extends('storefront.layouts.app')

@section('title', 'Return & Refund | WindowShop')
@section('meta_description', 'WindowShop return and refund information for customers and local merchant orders.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Return & Refund</p>
                </div>
                <h3>Return & Refund</h3>
            </div>
        </div>
    </section>

    <section class="section-term-user flat-spacing">
        <div class="container">
            <div class="content">
                <div class="term-item">
                    <h5 class="term-title">1. Returns</h5>
                    <p class="term-text cl-text-2">Return eligibility may depend on the merchant, product type, item condition, and timing. Products should usually be unused, with original packaging and purchase proof.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">2. Return Process</h5>
                    <p class="term-text cl-text-2">Contact the merchant or WindowShop support with your order details and reason for return. The team will guide you on whether pickup, store return, or another process applies.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">3. Refunds</h5>
                    <p class="term-text cl-text-2">Approved refunds are processed after the returned item is checked. Refund timelines may vary based on payment method, merchant approval, and bank processing time.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">4. Damaged Or Incorrect Items</h5>
                    <p class="term-text cl-text-2">If an item is damaged, defective, or incorrect, contact support as soon as possible with photos and order details so the issue can be reviewed quickly.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">5. Non-Returnable Items</h5>
                    <p class="term-text cl-text-2">Some items may not be returnable due to hygiene, personalization, perishability, final-sale terms, or merchant-specific restrictions.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
