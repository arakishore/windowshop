@extends('storefront.layouts.app')

@section('title', 'Privacy Policy | WindowShop')
@section('meta_description', 'WindowShop privacy policy covering information collection, use, retention, and customer choices.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Privacy Policy</p>
                </div>
                <h3>Privacy Policy</h3>
            </div>
        </div>
    </section>

    <section class="section-term-user flat-spacing">
        <div class="container">
            <div class="content">
                <div class="term-item">
                    <h5 class="term-title">1. Information We Collect</h5>
                    <p class="term-text cl-text-2">We may collect information you provide directly, such as name, email address, contact details, store enquiry details, and messages sent through forms. We may also collect basic device and usage information to improve the website.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">2. How We Use Information</h5>
                    <p class="term-text cl-text-2">We use information to operate WindowShop, respond to enquiries, improve discovery, support merchant pages, prevent misuse, and communicate relevant updates where permitted.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">3. Sharing Information</h5>
                    <p class="term-text cl-text-2">We do not sell personal information. We may share limited information with merchants or service providers only when needed to support customer requests, platform operations, or legal requirements.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">4. Data Retention</h5>
                    <p class="term-text cl-text-2">We keep information only as long as needed for business, support, legal, and security purposes. You may contact us for correction or deletion requests where applicable.</p>
                </div>
                <div class="term-item">
                    <h5 class="term-title">5. Your Choices</h5>
                    <p class="term-text cl-text-2">You can choose not to submit optional information, unsubscribe from non-essential messages, and contact support for privacy-related questions.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
