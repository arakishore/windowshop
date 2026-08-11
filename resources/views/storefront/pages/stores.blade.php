@extends('storefront.layouts.app')

@section('title', 'Our Stores | WindowShop')
@section('meta_description', 'Browse local shops available on WindowShop and visit their store websites.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <h1>Our Stores</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Our Stores</p>
                </div>

                <p class="text-body-1 cl-text-2">
                    Explore local shop pages, browse their catalogues, and open the website URL we provide for each store.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="tf-grid-layout sm-col-2 xl-col-3 flat-spacing-2 pb-0">
                @foreach($stores as $store)
                    <div class="card-store{{ $loop->first ? ' hover-img4' : '' }}">
                        <div class="store-image{{ $loop->first ? ' img-style4' : '' }}">
                            <img loading="lazy" width="450" height="338" src="{{ asset($store['image']) }}"
                                alt="{{ $store['name'] }}">
                        </div>
                        <div class="store-infor">
                            <h5 class="info_name">{{ $store['name'] }}</h5>
                            <ul class="list-info d-grid gap-4">
                                <li>
                                    <span class="cl-text-2">Category:</span>
                                    <span>{{ $store['area'] }}</span>
                                </li>
                                <li>
                                    <span class="cl-text-2">Address:</span>
                                    <span>{{ $store['address'] }}</span>
                                </li>
                                <li>
                                    <span class="cl-text-2">Website URL:</span>
                                    <a href="{{ $store['website_url'] }}" class="link">
                                        {{ $store['website_url'] }}
                                    </a>
                                </li>
                            </ul>
                            <a href="{{ $store['website_url'] }}" class="d-inline-flex align-items-center gap-4 link">
                                <span class="text-caption-01 fw-semibold">Open Store Website</span>
                                <i class="icon icon-ArrowUpRight1 fs-20"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
