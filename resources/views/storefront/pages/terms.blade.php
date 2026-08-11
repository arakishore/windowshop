@extends('storefront.layouts.app')

@section('title', 'Terms & Conditions | WindowShop')
@section('meta_description', 'WindowShop terms and conditions for using the local storefront platform.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Terms & Conditions</p>
                </div>
                <h3>Terms & Conditions</h3>
            </div>
        </div>
    </section>

    <section class="section-term-user flat-spacing">
        <div class="container">
            <div class="content">
                <div class="term-item">
                    <h5 class="term-title">1. Use Of WindowShop</h5>
                    <p class="term-text cl-text-2">WindowShop provides a local storefront and discovery experience for customers and merchants. By using the website, you agree to use it for lawful browsing, discovery, communication, and shopping-related purposes.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">2. Store And Product Information</h5>
                    <p class="term-text cl-text-2">Product details, prices, stock availability, images, offers, and store information may be updated from time to time. Customers should confirm important details with the merchant before completing a purchase.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">3. Merchant Responsibility</h5>
                    <p class="term-text cl-text-2">Each merchant is responsible for the accuracy of their catalogue, store page, policies, service commitments, product quality, and fulfilment communication with customers.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">4. Accounts And Security</h5>
                    <p class="term-text cl-text-2">Users are responsible for maintaining accurate account information and keeping login details secure. Please contact support if you believe your account has been misused.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">5. Updates To Terms</h5>
                    <p class="term-text cl-text-2">We may update these terms as the platform grows. Continued use of WindowShop after updates means you accept the latest version.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
