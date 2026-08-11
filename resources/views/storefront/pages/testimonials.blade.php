@extends('storefront.layouts.app')

@section('title', 'Testimonials | WindowShop')
@section('meta_description', 'Read real stories from local customers and merchants using WindowShop.')

@push('styles')
    <style>
        .testimonial-listing-grid .testimonial-v01 {
            height: 100%;
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
                    <p class="text-caption-01">Testimonials</p>
                </div>
                <h3>Testimonials</h3>
                <p class="text-body-1 cl-text-2">
                    Real stories from local customers and merchants who use WindowShop to discover, browse, and grow.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="tf-grid-layout sm-col-2 xl-col-4 gap-20 testimonial-listing-grid">
                @foreach($testimonials as $testimonial)
                    <div class="testimonial-v01 style-2 type-3 wow fadeInUp"
                        @if(! $loop->first) data-wow-delay="{{ number_format($loop->index / 10, 1) }}s" @endif>
                        <div class="tes-content">
                            <div class="tes_avatar">
                                <img loading="lazy" width="60" height="60"
                                    src="{{ asset($testimonial['avatar']) }}" alt="{{ $testimonial['name'] }}">
                            </div>
                            <div class="star-wrap d-flex align-items-center">
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                            </div>
                            <div class="tes_author">
                                <div class="h6 author-name">{{ $testimonial['name'] }}</div>
                                <div class="author-verified">
                                    <i class="icon icon-CheckCircle1"></i>
                                    <span class="text cl-text-2">Verified User</span>
                                </div>
                            </div>
                            <p class="tes_text h6 fw-medium">
                                "{{ $testimonial['quote'] }}"
                            </p>
                            <div class="tes_product">
                                <div class="product-image">
                                    <img loading="lazy" width="60" height="60"
                                        src="{{ asset($testimonial['product_image']) }}"
                                        alt="{{ $testimonial['product_name'] }}">
                                </div>
                                <div class="product-infor">
                                    <a href="#;" class="prd_name link fw-medium lh-24 text-line-clamp-1">
                                        {{ $testimonial['product_name'] }}
                                    </a>
                                    <p class="prd_price fw-semibold text-primary">{{ $testimonial['tag'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
