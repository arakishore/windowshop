@extends('storefront.layouts.app')

@section('title', 'About Us - WindowShop')
@section('meta_description', 'Learn how WindowShop helps local shops reach nearby customers and helps customers discover trusted stores around them.')

@push('styles')
    <style>
        .about-value-grid .about-value-card {
            position: relative;
            min-height: 100%;
            padding: 30px 24px;
            border: 1px solid rgba(18, 18, 18, .08);
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8f8f6 100%);
            box-shadow: 0 14px 40px rgba(18, 18, 18, .06);
            overflow: hidden;
            transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease;
        }

        .about-value-grid .about-value-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #111111, #e14343);
            transform: scaleX(.35);
            transform-origin: left;
            transition: transform .28s ease;
        }

        .about-value-grid .about-value-card::after {
            content: "";
            position: absolute;
            right: -36px;
            bottom: -42px;
            width: 118px;
            height: 118px;
            border-radius: 50%;
            background: rgba(225, 67, 67, .08);
        }

        .about-value-grid .about-value-card:hover {
            transform: translateY(-8px);
            border-color: rgba(225, 67, 67, .24);
            box-shadow: 0 22px 56px rgba(18, 18, 18, .11);
        }

        .about-value-grid .about-value-card:hover::before {
            transform: scaleX(1);
        }

        .about-value-grid .value-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background: #111111;
            color: #ffffff;
            font-size: 24px;
            box-shadow: 0 10px 24px rgba(18, 18, 18, .14);
        }

        .about-value-grid .value-label {
            color: #e14343;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .about-value-grid .value-word {
            line-height: 1;
        }

        .about-story-image.article-blog {
            gap: 0;
        }

        .about-story-image .blog-image {
            width: 100%;
            background: #f6f1e8;
        }

        .about-story-image .blog-image img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
        }

        .about-client-cards .banner-image-text .bn-image {
            position: relative;
        }

        .about-client-cards .banner-image-text .bn-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, .05) 20%, rgba(0, 0, 0, .66) 100%);
            z-index: 1;
        }

        .about-client-cards .banner-image-text .bn-content {
            z-index: 2;
            padding: 0 20px 24px;
        }

        .about-client-cards .banner-image-text .title,
        .about-client-cards .banner-image-text .desc {
            color: #ffffff;
            text-shadow: 0 2px 14px rgba(0, 0, 0, .35);
        }

        @media (max-width: 575px) {
            .about-value-grid .about-value-card {
                padding: 24px 20px;
            }
        }
    </style>
@endpush

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">About Us</p>
                </div>
                <h3>About Us</h3>
                <p class="text-body-1 cl-text-2">
                    WindowShop helps people discover trusted local stores, compare what is available nearby,
                    and connect with merchants who already serve their city.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-6">
                    <article class="article-blog style-3 hover-img about-story-image wow fadeInUp">
                        <div class="blog-image img-style">
                            <img loading="lazy" width="640" height="640"
                                src="{{ asset('assets/storefront/images/section/store-4.jpg') }}" alt="Local shop digital presence">
                            <div class="wrap-tags">
                                <span class="tag fw-semibold text-caption-01">LOCAL</span>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="col-lg-6">
                    <p class="text-caption-01 fw-semibold text-primary mb-12 text-uppercase">Who We Are</p>
                    <h3 class="mb-16">Driven by local shop growth, not just online traffic</h3>
                    <p class="text-body-1 cl-text-2 mb-16">
                        WindowShop is built for merchants who want customers to see their store before they walk in.
                        Products, banners, categories, and shop details come together as a simple digital window for the
                        local market.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-0">
                        Our aim is to help nearby customers discover real shops, compare what is available, and choose
                        trusted local sellers with more confidence.
                    </p>
                </div>
            </div>

            <div class="row align-items-center gy-4 flat-spacing pb-0">
                <div class="col-lg-6 order-2 order-lg-1">
                    <p class="text-caption-01 fw-semibold text-primary mb-12 text-uppercase">Our Promise</p>
                    <h3 class="mb-16">We make local discovery useful before, during, and after every visit</h3>
                    <p class="text-body-1 cl-text-2 mb-16">
                        Shops should not need a complicated ecommerce setup just to be visible. WindowShop gives them a
                        practical place to show what they sell, what is new, and why local customers should choose them.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-0">
                        For customers, it keeps the buying journey simple: discover nearby options, check store pages,
                        and reach the right business faster.
                    </p>
                </div>
                <div class="col-lg-6 order-1 order-lg-2">
                    <article class="article-blog style-3 hover-img about-story-image wow fadeInUp">
                        <div class="blog-image img-style">
                            <img loading="lazy" width="640" height="640"
                                src="{{ asset('assets/storefront/images/section/store-5.jpg') }}" alt="Local customers discovering shops">
                            <div class="wrap-tags">
                                <span class="tag fw-semibold text-caption-01">PROMISE</span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="section-main-about flat-spacing pt-0">
        <div class="container">
            <div class="about-value-grid">
                <div class="tf-grid-layout md-col-2 xl-col-4 gap-20">
                    <div class="box-why about-value-card wow fadeInUp">
                        <span class="value-icon"><i class="icon icon-storefront"></i></span>
                        <p class="text-caption-01 fw-semibold value-label mb-0">Nearby First</p>
                        <p class="h1 fw-medium value-word">Local</p>
                        <p class="title h5 fw-medium">Store Discovery</p>
                        <p class="sub cl-text-2">
                            Customers can find shops by category, location, product interest, and current offers.
                        </p>
                    </div>
                    <div class="box-why about-value-card wow fadeInUp" data-wow-delay=".08s">
                        <span class="value-icon"><i class="icon icon-Layout"></i></span>
                        <p class="text-caption-01 fw-semibold value-label mb-0">Simple Setup</p>
                        <p class="h1 fw-medium value-word">Easy</p>
                        <p class="title h5 fw-medium">Digital Catalogue</p>
                        <p class="sub cl-text-2">
                            Merchants can showcase products, banners, brands, and store information in one place.
                        </p>
                    </div>
                    <div class="box-why about-value-card wow fadeInUp" data-wow-delay=".16s">
                        <span class="value-icon"><i class="icon icon-ShieldCheck"></i></span>
                        <p class="text-caption-01 fw-semibold value-label mb-0">Real Sellers</p>
                        <p class="h1 fw-medium value-word">Trust</p>
                        <p class="title h5 fw-medium">Known Sellers</p>
                        <p class="sub cl-text-2">
                            Local clients can shop with businesses they can visit, call, and build repeat relationships with.
                        </p>
                    </div>
                    <div class="box-why about-value-card wow fadeInUp" data-wow-delay=".24s">
                        <span class="value-icon"><i class="icon icon-Lightning"></i></span>
                        <p class="text-caption-01 fw-semibold value-label mb-0">More Reach</p>
                        <p class="h1 fw-medium value-word">Growth</p>
                        <p class="title h5 fw-medium">More Reach</p>
                        <p class="sub cl-text-2">
                            Shops get a wider audience while still keeping their own local identity and service style.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center wow fadeInUp">
                <h3 class="s-title">Good For Shops And Local Clients</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    A simple visual space to explain how WindowShop connects both sides of local buying.
                </p>
            </div>
            <div class="tf-grid-layout md-col-3 gap-20 about-client-cards">
                <div class="banner-image-text style-bottom bt-center">
                    <a href="#;" class="bn-image img-style radius-20">
                        <img loading="lazy" width="450" height="608"
                            src="{{ asset('assets/storefront/images/section/store-1.jpg') }}" alt="Local shop catalogue">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <h5 class="title mb-8">For Shop Owners</h5>
                        <p class="desc text-body-1 mb-0">
                            Put products, banners, and shop details in front of customers before they visit.
                        </p>
                    </div>
                </div>
                <div class="banner-image-text style-bottom bt-center">
                    <a href="#;" class="bn-image img-style radius-20">
                        <img loading="lazy" width="450" height="608"
                            src="{{ asset('assets/storefront/images/section/store-2.jpg') }}" alt="Local product discovery">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <h5 class="title mb-8">For Local Clients</h5>
                        <p class="desc text-body-1 mb-0">
                            Browse nearby choices, compare offers, and find stores that match your needs.
                        </p>
                    </div>
                </div>
                <div class="banner-image-text style-bottom bt-center">
                    <a href="#;" class="bn-image img-style radius-20">
                        <img loading="lazy" width="450" height="608"
                            src="{{ asset('assets/storefront/images/section/store-3.jpg') }}" alt="Neighbourhood market growth">
                    </a>
                    <div class="bn-content wow fadeInUp">
                        <h5 class="title mb-8">For The Market</h5>
                        <p class="desc text-body-1 mb-0">
                            Keep local commerce visible, searchable, and ready for repeat customers.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pb-0">
        <div class="position-relative flat-spacing pb-0">
            <div class="br-line fake-class top-0"></div>
            <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3" data-mobile-sm="2"
                data-mobile="1" data-space-lg="40" data-space-md="20" data-space="10" data-pagination="1"
                data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <div class="box-why couter-side counted">
                            <p class="h1 fw-medium">8.2k</p>
                            <p class="title h5 fw-medium">Products Available</p>
                            <p class="sub cl-text-2">
                                We offer a wide selection of quality products across everyday local needs.
                            </p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="box-why view-counter">
                            <p class="h1 fw-medium">
                                <span class="number" data-speed="1000" data-to="10">10</span><span>k</span>
                            </p>
                            <p class="title h5 fw-medium">Happy Customers</p>
                            <p class="sub cl-text-2">
                                Helping local customers discover shops, offers, and products around them.
                            </p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="box-why view-counter">
                            <p class="h1 fw-medium">
                                <span class="number" data-speed="1000" data-to="96">96</span>
                            </p>
                            <p class="title h5 fw-medium">Partner Brands</p>
                            <p class="sub cl-text-2">
                                Bringing trusted shop and brand listings into one simple local catalogue.
                            </p>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="box-why view-counter">
                            <p class="h1 fw-medium">
                                <span class="number" data-speed="1000" data-to="16">16</span><span>k</span>
                            </p>
                            <p class="title h5 fw-medium">Products For Sale</p>
                            <p class="sub cl-text-2">
                                A growing product base for shoppers to browse before they choose a store.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="sw-dot-default tf-sw-pagination"></div>
            </div>
        </div>
    </section>

    @include('storefront.partials.customer-say')
@endsection
