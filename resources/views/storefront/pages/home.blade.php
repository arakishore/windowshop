@extends('storefront.layouts.app')

@section('title', 'WindowShop Storefront')
@section('meta_description', 'Static WindowShop storefront Blade preview converted from the selected HTML template.')

@php
    $heroSlides = [
        [
            'image' => 'assets/storefront/images/slider/slider-1.jpg',
            'eyebrow' => 'SUMMER COLLECTION',
            'title' => 'Elevate Your Everyday Style',
            'subtitle' => 'Fresh marketplace picks from local sellers and curated brands.',
            'button' => 'Shop Styles',
        ],
        [
            'image' => 'assets/storefront/images/slider/slider-2.jpg',
            'eyebrow' => 'Join WindowShop Today',
            'title' => 'Onboard your store and reach a wider audience with WindowShop',
            'subtitle' => '',
            'button' => 'Register now',
        ],

    ];

    $categories = [
        [
            'name' => 'Outerwear',
            'image' => 'assets/storefront/images/category/cate-1.jpg',
            'url' => '#top-picks',
        ],
        [
            'name' => 'Tops & Shirts',
            'image' => 'assets/storefront/images/category/cate-2.jpg',
            'url' => '#top-picks',
        ],
        [
            'name' => 'Bottoms',
            'image' => 'assets/storefront/images/category/cate-3.jpg',
            'url' => '#top-picks',
        ],
        [
            'name' => 'Dresses',
            'image' => 'assets/storefront/images/category/cate-4.jpg',
            'url' => '#top-picks',
        ],
        [
            'name' => 'Footwear',
            'image' => 'assets/storefront/images/category/cate-5.jpg',
            'url' => '#top-picks',
        ],
        [
            'name' => 'Accessories',
            'image' => 'assets/storefront/images/category/cate-6.jpg',
            'url' => '#top-picks',
        ],
    ];

    $demoProducts = [
        [
            'name' => 'V-neck cotton T-shirt',
            'price' => '$59,99',
            'old_price' => '$79,99',
            'image' => 'assets/storefront/images/product/product-1.jpg',
            'hover_image' => 'assets/storefront/images/product/product-1_2.jpg',
            'badge' => 'NEW',
            'swatches' => [
                ['label' => 'Black', 'class' => 'bg-dark', 'image' => 'assets/storefront/images/product/product-1.jpg'],
                [
                    'label' => 'Brown',
                    'class' => 'bg-brown',
                    'image' => 'assets/storefront/images/product/product-1_3.jpg',
                ],
            ],
        ],
        [
            'name' => 'Ribbed knit top',
            'price' => '$45,99',
            'old_price' => '$69,99',
            'image' => 'assets/storefront/images/product/product-2.jpg',
            'hover_image' => 'assets/storefront/images/product/product-2_2.jpg',
            'swatches' => [
                ['label' => 'Grey', 'class' => 'bg-grey', 'image' => 'assets/storefront/images/product/product-2.jpg'],
            ],
        ],
        [
            'name' => 'Oversized denim jacket',
            'price' => '$89,99',
            'old_price' => '$119,99',
            'image' => 'assets/storefront/images/product/product-3.jpg',
            'hover_image' => 'assets/storefront/images/product/product-3_2.jpg',
            'badge' => 'SALE',
            'has_size' => true,
            'swatches' => [
                [
                    'label' => 'Blue',
                    'class' => 'bg-dark-blue-2',
                    'image' => 'assets/storefront/images/product/product-3.jpg',
                ],
            ],
        ],
        [
            'name' => 'Linen slim-fit shirt',
            'price' => '$45,99',
            'old_price' => '$79,99',
            'image' => 'assets/storefront/images/product/product-4.jpg',
            'hover_image' => 'assets/storefront/images/product/product-4_2.jpg',
            'badge' => 'NEW',
            'swatches' => [
                [
                    'label' => 'Blue',
                    'class' => 'bg-dark-blue-2',
                    'image' => 'assets/storefront/images/product/product-4.jpg',
                ],
            ],
        ],
    ];

    $newArrivals = [
        [
            'name' => 'Leather shopper bag with stitching',
            'price' => '$99,99',
            'old_price' => '$129,99',
            'image' => 'assets/storefront/images/product/product-5.jpg',
            'hover_image' => 'assets/storefront/images/product/product-5_2.jpg',
            'swatches' => [
                [
                    'label' => 'Tan',
                    'class' => 'bg-light-brown',
                    'image' => 'assets/storefront/images/product/product-5.jpg',
                ],
            ],
        ],
        [
            'name' => 'Relaxed utility jacket',
            'price' => '$119,99',
            'image' => 'assets/storefront/images/product/product-6.jpg',
            'hover_image' => 'assets/storefront/images/product/product-6_2.jpg',
            'has_size' => true,
        ],
        [
            'name' => 'Wide leg cotton trousers',
            'price' => '$74,99',
            'old_price' => '$94,99',
            'image' => 'assets/storefront/images/product/product-7.jpg',
            'hover_image' => 'assets/storefront/images/product/product-7_2.jpg',
            'badge' => 'NEW',
        ],
        [
            'name' => 'Everyday crossbody bag',
            'price' => '$65,99',
            'image' => 'assets/storefront/images/product/product-8.jpg',
            'hover_image' => 'assets/storefront/images/product/product-8_2.jpg',
        ],
    ];

    $collectionBanners = [
        [
            'title' => 'Shop Women',
            'image' => 'assets/storefront/images/collection/cls-6.jpg',
            'class' => 'collection-position-2 hover-img h-100',
            'width' => 700,
            'height' => 933,
        ],
        ['title' => 'Shop Men', 'image' => 'assets/storefront/images/collection/cls-7.jpg'],
        ['title' => 'Shop Essentials', 'image' => 'assets/storefront/images/collection/cls-8.jpg'],
    ];

    $searchProducts = $demoProducts;
@endphp

@section('content')
    @include('storefront.partials.hero')

    <section id="categories" class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center wow fadeInUp">
                <h3 class="s-title">
                    Shop By Categories
                </h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Top styles everyone's talking about.
                </p>
            </div>
            <div dir="ltr" class="swiper tf-swiper" data-preview="6" data-tablet="4" data-mobile-sm="3" data-mobile="2"
                data-space-lg="30" data-space-md="15" data-space="10" data-pagination="2" data-pagination-sm="3"
                data-pagination-md="4" data-pagination-lg="6">
                <div class="swiper-wrapper">
                    @foreach ($categories as $category)
                        @include('storefront.components.category-card', ['category' => $category])
                    @endforeach
                </div>
                <div class="sw-line-default style-2 tf-sw-pagination"></div>
            </div>
        </div>
    </section>
    <!-- banner -->
    <div class="banner-v01">
        <div class="bn_image">
            <img loading="lazy" width="1920" height="620"
                src="{{ asset('assets/storefront/images/section/banner-40.jpg') }}" alt="Image">
        </div>
        <div class="bn_content">
            <div class="container">
                <div class="h1 title text-white mb-12">
                    Elevate Your <br>
                    Workout Style
                </div>
                <p class="desc text-white mb-32">
                    Premium activewear crafted for comfort, <br>
                    performance, and confidence.
                </p>
                <a href="shop-default.html" class="tf-btn btn-white">
                    Shop Styles
                </a>
            </div>
        </div>
        <div class="infiniteSlide-text wow fadeInUp">
            <div class="infiniteSlide infiniteSlide-wrapper" data-clone="5">
                <p class="text h1 fw-semibold">NEW SEASON PICKS</p>
                <p class="text h1 fw-semibold">TRENDING STYLES</p>
                <p class="text h1 fw-semibold">LIMITED DROPS</p>
            </div>
        </div>
    </div>
    <!-- /banner -->


    <!-- Collection -->
    <div class="flat-spacing">
        <div class="container">
            <div class="tf-grid-layout md-col-2 xl-col-3 xl-gap-20">
                <div class="banner-image-text type-abs style-4">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="450" height="608"
                            src="{{ asset('assets/storefront/images/section/banner-12.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link">
                            Save 25% <br class="d-none d-sm-block">
                            Today
                        </a>
                        <p class="desc cl-text-3 mb-28">
                            T-Shirts, Hoodies & More
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white small ">
                            View More
                        </a>
                    </div>
                </div>
                <div class="tf-grid-layout gap-20">
                    <div class="box-image_v03 hover-img4">
                        <a href="shop-default.html" class="box-image_img img-style4">
                            <img loading="lazy" width="450" height="294"
                                src="{{ asset('assets/storefront/images/category/cate-12.jpg') }}" alt="Image">
                        </a>
                        <div class="box-image_content">
                            <a href="shop-default.html" class="title h6 fw-medium link">
                                Up To 35% Off
                                <i class="icon icon-ArrowUpRight"></i>
                            </a>
                        </div>
                    </div>
                    <div class="box-image_v03 hover-img4">
                        <a href="shop-default.html" class="box-image_img img-style4">
                            <img loading="lazy" width="450" height="294"
                                src="{{ asset('assets/storefront/images/category/cate-13.jpg') }}" alt="Image">
                        </a>
                        <div class="box-image_content">
                            <a href="shop-default.html" class="title h6 fw-medium link">
                                Free Shipping On All Orders
                                <i class="icon icon-ArrowUpRight"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="tf-grid-layout gap-20 md-col-2 xl-col-1 xl-wd-full">
                    <div class="box-image_v03 hover-img4">
                        <a href="shop-default.html" class="box-image_img img-style4">
                            <img loading="lazy" width="450" height="294"
                                src="{{ asset('assets/storefront/images/category/cate-14.jpg') }}" alt="Image">
                        </a>
                        <div class="box-image_content">
                            <a href="shop-default.html" class="title h6 fw-medium link">
                                Free Gift With Purchase
                                <i class="icon icon-ArrowUpRight"></i>
                            </a>
                        </div>
                    </div>
                    <div class="box-image_v03 hover-img4">
                        <a href="shop-default.html" class="box-image_img img-style4">
                            <img loading="lazy" width="450" height="294"
                                src="{{ asset('assets/storefront/images/category/cate-15.jpg') }}" alt="Image">
                        </a>
                        <div class="box-image_content">
                            <a href="shop-default.html" class="title h6 fw-medium link">
                                Limited Time Offer
                                <i class="icon icon-ArrowUpRight"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Collection -->
    <!-- Banner Countdown -->
    <div class="banner-countdown-v01 bg-primary">
        <div class="container">
            <div class="content">
                <div class="col-left">
                    <h3 class="text-white mb-8">Hurry! Deals On</h3>
                    <p class="text-white">Up to 50% Off Selected Styles. Don't Miss Out.</p>
                </div>
                <div class="countdown-v01 text-white">
                    <div class="js-countdown cd-has-zero cd-custom" data-timer="1093120"
                        data-labels="Days,Hours,Mins,Secs">
                    </div>
                </div>
                <a href="shop-default.html" class="tf-btn btn-white">
                    Shop Now
                </a>
            </div>
        </div>
    </div>
    <!-- Collection -->
    <section class="flat-spacing pb-0">
        <div class="container-full">
            <div class="tf-grid-layout md-col-2 gap-10">
                <div class="banner-image-text style-bottom bt-center">
                    <a href="shop-default.html" class="bn-image img-style radius-20">
                        <img loading="lazy" width="440" height="440"
                            src="{{ asset('assets/storefront/images/section/banner-66.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <a href="shop-default.html" class="title h2 link-underline-text mb-12">
                            SALE OFF UP TO 50%
                        </a>
                        <p class="desc text-body-1 mb-24">
                            Grab top sport shoes at unbeatable deals — limited time.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Order Now
                        </a>
                    </div>
                </div>
                <div class="banner-image-text style-bottom bt-center">
                    <a href="shop-default.html" class="bn-image img-style radius-20">
                        <img loading="lazy" width="440" height="440"
                            src="{{ asset('assets/storefront/images/section/banner-67.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <a href="shop-default.html" class="title h2 text-white link-underline-white mb-12">
                            BIG SEASON SALE
                        </a>
                        <p class="desc text-body-1 text-white mb-24">
                            Exclusive deals on top-style sneakers. Save more while stocks last.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Order Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /Collection -->

    <!-- Collection -->
    <section class="flat-spacing pb-0">
        <div class="container">
            <div class="tf-grid-layout xs-col-1 sm-col-3 md-col-4 flat-spacing-2 pt-0">
                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>

                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>

                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>

                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>

                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>

                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>
                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>
                <div class="banner-image-text type-abs style-1 wow fadeInLeft">
                    <a href="shop-default.html" class="bn-image img-style">
                        <img loading="lazy" width="690" height="388"
                            src="{{ asset('assets/storefront/images/section/banner-4.jpg') }}" alt="Image">
                    </a>
                    <div class="bn-content">
                        <a href="shop-default.html" class="title h3 fw-medium text-white link-dark">
                            Nature’s Support <br class="d-none d-sm-block">
                            for Modern Life
                        </a>
                        <p class="desc text-white text-body-1">
                            Boost vitality and balance with clean, <br class="d-none d-sm-block">
                            mindful ingredients.
                        </p>
                        <a href="shop-default.html" class="btn-action tf-btn btn-white">
                            Shop Styles
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>
    <!-- /Collection -->

    <!-- /Banner Countdown -->
    <section id="top-picks" class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center wow fadeInUp">
                <h3 class="s-title">
                    New Arrivals
                </h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Fresh styles just in! Elevate your look.
                </p>
            </div>
            <div dir="ltr" class="swiper tf-swiper wrap-sw-over" data-preview="4" data-tablet="3"
                data-mobile-sm="2" data-mobile="1" data-space-lg="30" data-space-md="20" data-space="15">
                <div class="swiper-wrapper">
                    @foreach ($demoProducts as $product)
                        @include('storefront.components.product-card', ['product' => $product])
                    @endforeach
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
        </div>
    </section>

    <!-- Store -->
    <section class="themesFlat">
        <div class="container">
            <div class="sect-heading type-2 text-center wow fadeInUp">
                <h3 class="s-title">
                    Shop by Store
                </h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Elevate your wardrobe with fresh finds today!
                </p>
            </div>
            <div dir="ltr" class="swiper tf-swiper" data-preview="5" data-tablet="3" data-mobile-sm="3"
                data-mobile="2" data-space="10" data-pagination="2" data-pagination-sm="3" data-pagination-md="4"
                data-pagination-lg="5">
                <div class="swiper-wrapper">
                    <!-- slide 1 -->
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay wow fadeInUp">
                            <div class="image img-style">
                                <img loading="lazy" width="274" height="274"
                                    src="{{ asset('assets/storefront/images/gallery/gallery-1.jpg') }}" alt="Image">
                            </div>
                            <a href="product-detail.html" class="box-icon hover-tooltip">
                                <span class="icon icon-Eye"></span>
                                <span class="tooltip">View product</span>
                            </a>
                        </div>
                    </div>
                    <!-- slide 2 -->
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay wow fadeInUp">
                            <div class="image img-style">
                                <img loading="lazy" width="274" height="274"
                                    src="{{ asset('assets/storefront/images/gallery/gallery-2.jpg') }}" alt="Image">
                            </div>
                            <a href="product-detail.html" class="box-icon hover-tooltip">
                                <span class="icon icon-Eye"></span>
                                <span class="tooltip">View product</span>
                            </a>
                        </div>
                    </div>
                    <!-- slide 3 -->
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay wow fadeInUp">
                            <div class="image img-style">
                                <img loading="lazy" width="274" height="274"
                                    src="{{ asset('assets/storefront/images/gallery/gallery-3.jpg') }}" alt="Image">
                            </div>
                            <a href="product-detail.html" class="box-icon hover-tooltip">
                                <span class="icon icon-Eye"></span>
                                <span class="tooltip">View product</span>
                            </a>
                        </div>
                    </div>
                    <!-- slide 4 -->
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay wow fadeInUp">
                            <div class="image img-style">
                                <img loading="lazy" width="274" height="274"
                                    src="{{ asset('assets/storefront/images/gallery/gallery-4.jpg') }}" alt="Image">
                            </div>
                            <a href="product-detail.html" class="box-icon hover-tooltip">
                                <span class="icon icon-Eye"></span>
                                <span class="tooltip">View product</span>
                            </a>
                        </div>
                    </div>
                    <!-- slide 5 -->
                    <div class="swiper-slide">
                        <div class="gallery-item hover-img hover-overlay wow fadeInUp">
                            <div class="image img-style">
                                <img loading="lazy" width="274" height="274"
                                    src="{{ asset('assets/storefront/images/gallery/gallery-5.jpg') }}" alt="Image">
                            </div>
                            <a href="product-detail.html" class="box-icon hover-tooltip">
                                <span class="icon icon-Eye"></span>
                                <span class="tooltip">View product</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
        </div>
    </section>
    <!-- /Gallery -->
    <!-- Testimonial -->
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center wow fadeInUp">
                <h3 class="s-title">
                    Customer Say!
                </h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Our customers adore our products, and we constantly aim to delight them.
                </p>
            </div>
            <div dir="ltr" class="swiper tf-swiper" data-preview="2" data-tablet="2" data-mobile-sm="1"
                data-mobile="1" data-space-lg="30" data-space-md="15" data-space="10" data-pagination="1"
                data-pagination-sm="2" data-pagination-md="2" data-pagination-lg="2">
                <div class="swiper-wrapper">
                    <!-- slide 1 -->
                    <div class="swiper-slide">
                        <div class="testimonial-v01 style-2 wow fadeInUp">
                            <div class="tes-content">
                                <div class="star-wrap d-flex align-items-center">
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                </div>
                                <div class="tes_author">
                                    <h5 class="author-name">Emma Collins</h5>
                                    <div class="br-line"></div>
                                    <div class="author-verified">
                                        <i class="icon icon-CheckCircle1"></i>
                                        <span class="cl-text-2">
                                            Verified Buyer
                                        </span>
                                    </div>
                                </div>
                                <p class="tes_text h6 fw-medium text-capitalize">
                                    “I love how calm and balanced I feel after using these products. Everything
                                    feels more natural, lighter, and easy again <br class="d-none d-xxl-block">
                                    every day.”
                                </p>
                                <div class="tes_product">
                                    <div class="product-image">
                                        <img loading="lazy" width="60" height="60"
                                            src="{{ asset('assets/storefront/images/product/mental/product-1.jpg') }}"
                                            alt="Image">
                                    </div>
                                    <div class="product-infor">
                                        <a href="product-detail.html" class="link fw-medium lh-24">
                                            Gaia Herbs Relax Gummies
                                        </a>
                                        <div class="price-wrap prd_price">
                                            <span class="price-new text-primary fw-semibold">$74.99</span>
                                            <span class="price-old text-caption-01 cl-text-3">$89,99</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- slide 2 -->
                    <div class="swiper-slide">
                        <div class="testimonial-v01 style-2 wow fadeInUp">
                            <div class="tes-content">
                                <div class="star-wrap d-flex align-items-center">
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                </div>
                                <div class="tes_author">
                                    <h5 class="author-name">Sophia Ramirez</h5>
                                    <div class="br-line"></div>
                                    <div class="author-verified">
                                        <i class="icon icon-CheckCircle1"></i>
                                        <span class="cl-text-2">
                                            Verified Buyer
                                        </span>
                                    </div>
                                </div>
                                <p class="tes_text h6 fw-medium text-capitalize">
                                    “These supplements have become part of my nightly routine. I sleep deeper, rest
                                    longer, wake up feeling genuinely refreshed every morning.”
                                </p>
                                <div class="tes_product">
                                    <div class="product-image">
                                        <img loading="lazy" width="60" height="60"
                                            src="{{ asset('assets/storefront/images/product/mental/product-3.jpg') }}"
                                            alt="Image">
                                    </div>
                                    <div class="product-infor">
                                        <a href="product-detail.html" class="link fw-medium lh-24">
                                            Blooming Blends Sleep Drops
                                        </a>
                                        <div class="price-wrap prd_price">
                                            <span class="price-new text-primary fw-semibold">$74.99</span>
                                            <span class="price-old text-caption-01 cl-text-3">$89,99</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- slide 1 -->
                    <div class="swiper-slide">
                        <div class="testimonial-v01 style-2 wow fadeInUp">
                            <div class="tes-content">
                                <div class="star-wrap d-flex align-items-center">
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                    <i class="icon icon-Star fs-24"></i>
                                </div>
                                <div class="tes_author">
                                    <h5 class="author-name">Emma Collins</h5>
                                    <div class="br-line"></div>
                                    <div class="author-verified">
                                        <i class="icon icon-CheckCircle1"></i>
                                        <span class="cl-text-2">
                                            Verified Buyer
                                        </span>
                                    </div>
                                </div>
                                <p class="tes_text h6 fw-medium text-capitalize">
                                    “I love how calm and balanced I feel after using these products. Everything
                                    feels more natural, lighter, and easy again <br class="d-none d-xxl-block">
                                    every day.”
                                </p>
                                <div class="tes_product">
                                    <div class="product-image">
                                        <img loading="lazy" width="60" height="60"
                                            src="{{ asset('assets/storefront/images/product/mental/product-1.jpg') }}"
                                            alt="Image">
                                    </div>
                                    <div class="product-infor">
                                        <a href="product-detail.html" class="link fw-medium lh-24">
                                            Gaia Herbs Relax Gummies
                                        </a>
                                        <div class="price-wrap prd_price">
                                            <span class="price-new text-primary fw-semibold">$74.99</span>
                                            <span class="price-old text-caption-01 cl-text-3">$89,99</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sw-line-default style-2 tf-sw-pagination"></div>
            </div>
        </div>
    </section>
    <!-- /Testimonial -->
@endsection
