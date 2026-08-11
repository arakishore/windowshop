@extends('storefront.layouts.app')

@section('title', 'FAQs | WindowShop')
@section('meta_description', 'Frequently asked questions about WindowShop, local shops, orders, shipping, returns, and support.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">FAQs</p>
                </div>
                <h3>FAQs</h3>
                <p class="cl-text-2">
                    Got questions? Find quick answers about local shops, product discovery, orders, shipping, and returns.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="row">
                <div class="col-lg-9">
                    <ul class="faq-list">
                        <li class="faq-item" id="general">
                            <h4 class="faq_title">General</h4>
                            <div class="faq_wrap" id="general-faq">
                                <div class="accordion-faq">
                                    <div class="accordion-title" data-bs-target="#faq-windowshop" role="button"
                                        data-bs-toggle="collapse" aria-expanded="true" aria-controls="faq-windowshop">
                                        <span class="text h6">1. What is WindowShop?</span>
                                        <span class="icon"><span class="ic-accordion-custom"></span></span>
                                    </div>
                                    <div id="faq-windowshop" class="collapse show" data-bs-parent="#general-faq">
                                        <div class="accordion-body">
                                            <p class="cl-text-2">WindowShop helps customers discover nearby shops, browse products, check offers, and visit merchant store pages.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-faq">
                                    <div class="accordion-title collapsed" data-bs-target="#faq-local-shops" role="button"
                                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="faq-local-shops">
                                        <span class="text h6">2. Are the shops local?</span>
                                        <span class="icon"><span class="ic-accordion-custom"></span></span>
                                    </div>
                                    <div id="faq-local-shops" class="collapse" data-bs-parent="#general-faq">
                                        <div class="accordion-body">
                                            <p class="cl-text-2">Yes. The platform is designed around local merchants and store discovery, so customers can find sellers they can trust and visit.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="faq-item" id="orders">
                            <h4 class="faq_title">Orders & Support</h4>
                            <div class="faq_wrap" id="orders-faq">
                                <div class="accordion-faq">
                                    <div class="accordion-title collapsed" data-bs-target="#faq-order-help" role="button"
                                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="faq-order-help">
                                        <span class="text h6">1. Who handles order questions?</span>
                                        <span class="icon"><span class="ic-accordion-custom"></span></span>
                                    </div>
                                    <div id="faq-order-help" class="collapse" data-bs-parent="#orders-faq">
                                        <div class="accordion-body">
                                            <p class="cl-text-2">For store-specific stock, order, or product questions, customers should contact the listed shop. WindowShop can help route general platform questions.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-faq">
                                    <div class="accordion-title collapsed" data-bs-target="#faq-shipping" role="button"
                                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="faq-shipping">
                                        <span class="text h6">2. How do shipping and returns work?</span>
                                        <span class="icon"><span class="ic-accordion-custom"></span></span>
                                    </div>
                                    <div id="faq-shipping" class="collapse" data-bs-parent="#orders-faq">
                                        <div class="accordion-body">
                                            <p class="cl-text-2">Shipping, delivery, return, and refund terms can depend on the merchant. Please review the policy pages and any store-specific instructions before ordering.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <div class="faq-sidebar">
                        <h5 class="mb-16">Need More Help?</h5>
                        <p class="cl-text-2 mb-20">Send us your question and we will help you find the right information.</p>
                        <a href="{{ route('storefront.contact') }}" class="tf-btn animate-btn w-100">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
