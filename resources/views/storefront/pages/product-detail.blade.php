@extends('storefront.layouts.app')

@section('title', $product['name'] . ' | WindowShop')
@section('meta_description', 'View product details, images, store information, and local availability on WindowShop.')

@php
    $singleImage = fn(string $image): string => asset('assets/storefront/images/product/single/' . $image);
    $productImage = fn(string $image): string => asset('assets/storefront/images/product/' . $image);
@endphp

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <h1>{{ $product['name'] }}</h1>

                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('storefront.products') }}" class="text-caption-01 cl-text-3 link">Products</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">{{ $product['name'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-product-single tf-main-product section-image-zoom flat-spacing">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="tf-product-media-wrap sticky-top">
                        <div class="product-thumbs-slider style-row row_left">
                            <div class="flat-wrap-media-product">
                                <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started"
                                    data-spacing="0">
                                    <div class="swiper-wrapper">
                                        @foreach ($product['images'] as $image)
                                            <div class="swiper-slide" data-color="green" data-size="M">
                                                <a href="{{ $singleImage($image) }}" target="_blank" class="item"
                                                    data-pswp-width="576px" data-pswp-height="768px">
                                                    <img loading="lazy" width="576" height="768" class="tf-image-zoom"
                                                        data-zoom="{{ $singleImage($image) }}"
                                                        src="{{ $singleImage($image) }}" alt="{{ $product['name'] }}">
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom"
                                data-direction="vertical" data-preview="7">
                                <div class="swiper-wrapper stagger-wrap">
                                    @foreach ($product['images'] as $image)
                                        <div class="swiper-slide stagger-item">
                                            <div class="item">
                                                <img loading="lazy" width="82" height="110"
                                                    src="{{ $singleImage($image) }}" alt="{{ $product['name'] }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="tf-product-info-wrap position-relative mt-md-0">
                        <div class="tf-zoom-main sticky-top"></div>
                        <div class="tf-product-info-list other-image-zoom">
                            <div class="tf-product-info-heading">
                                <p class="product-infor-cate text-caption-01 mb-4">{{ $product['category'] }}</p>
                                <h3 class="product-infor-name mb-12">{{ $product['name'] }}</h3>

                                <div class="product-infor-meta mb-20">
                                    <div class="meta_rate">
                                        <div class="star-wrap normal d-flex align-items-center">
                                            @for ($i = 0; $i < 5; $i++)
                                                <i class="icon icon-Star"></i>
                                            @endfor
                                        </div>
                                        <span class="text-caption-01 cl-text-2">({{ $product['reviews'] }})</span>
                                    </div>
                                    <div class="br-line type-vertical"></div>
                                    <div class="meta_sold">
                                        <i class="icon icon-Lightning text-primary"></i>
                                        <span class="text-caption-01 cl-text-2">{{ $product['sold_text'] }}</span>
                                    </div>
                                    <div class="br-line type-vertical"></div>
                                    <div class="meta_prd_code text-caption-01">
                                        <span class="cl-text-2">SKU:</span>
                                        <span>{{ $product['sku'] }}</span>
                                    </div>
                                </div>

                                <div class="product-infor-price mb-12">
                                    <h4 class="price-on-sale">{{ $product['price'] }}</h4>
                                    <div class="br-line type-vertical"></div>
                                    <p class="cl-text-3 text-decoration-line-through">{{ $product['old_price'] }}</p>
                                    <span class="badge-sale text-white fw-semibold text-caption-02">
                                        {{ $product['discount'] }}
                                    </span>
                                </div>

                                <p class="product-infor-desc cl-text-2 mb-12">{{ $product['description'] }}</p>

                                <div class="product-infor-reality lh-24">
                                    <div class="ic d-flex">
                                        <i class="icon icon-Eye text-white"></i>
                                    </div>
                                    <span class="text-caption-01">{{ $product['viewing_text'] }}</span>
                                </div>
                            </div>

                            <div class="br-line"></div>

                            <div class="tf-product-variant">
                                <div class="variant-picker-item variant-color">
                                    <div class="variant-picker-label">
                                        <div>
                                            Colors:
                                            <span class="variant-picker-label-value value-currentColor text-capitalize fw-medium">
                                                {{ $product['colors'][0]['name'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="variant-picker-values">
                                        @foreach ($product['colors'] as $color)
                                            <div class="hover-tooltip tooltip-bot color-btn style-image {{ $loop->first ? 'active' : '' }}"
                                                data-color="{{ $color['color'] }}">
                                                <div class="img">
                                                    <img loading="lazy" width="60" height="60"
                                                        src="{{ $singleImage($color['image']) }}"
                                                        data-src="{{ $singleImage($color['image']) }}"
                                                        alt="{{ $color['name'] }}">
                                                </div>
                                                <span class="tooltip">{{ $color['name'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="variant-picker-item variant-size">
                                    <div class="variant-picker-label">
                                        <div>
                                            Size:
                                            <span class="variant-picker-label-value value-currentSize text-capitalize fw-medium">M</span>
                                        </div>
                                        <a href="#findSize" data-bs-toggle="modal"
                                            class="tf-btn-line-2 style-primary text-caption-01 fw-semibold">
                                            Size Guide
                                        </a>
                                    </div>
                                    <div class="variant-picker-values">
                                        @foreach ($product['sizes'] as $size)
                                            <span class="size-btn {{ $size === 'M' ? 'active' : '' }}"
                                                data-size="{{ $size }}">{{ $size }}</span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="tf-product-total-quantity">
                                    <p>Quantity:</p>
                                    <div class="group-action">
                                        <div class="wg-quantity">
                                            <button class="btn-quantity btn-decrease" type="button">
                                                <i class="icon icon-minus"></i>
                                            </button>
                                            <input class="quantity-product" type="text" name="number" value="1">
                                            <button class="btn-quantity btn-increase" type="button">
                                                <i class="icon icon-plus"></i>
                                            </button>
                                        </div>
                                        <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                            class="btn-action-price tf-btn type-xl animate-btn w-100">
                                            Add To Cart
                                            <span class="d-none d-sm-block d-md-none d-lg-block">&nbsp;-&nbsp;</span>
                                            <span class="price-add d-none d-sm-block d-md-none d-lg-block">{{ $product['price'] }}</span>
                                        </a>
                                    </div>
                                    <a href="#;" class="tf-btn type-xl btn-primary animate-btn w-100">Contact Store</a>
                                </div>
                            </div>

                            <div class="tf-product-extra-link">
                                <a href="#;" class="product-extra-icon link">
                                    <i class="icon icon-storefront"></i>
                                    {{ $product['store'] }}
                                </a>
                                <a href="#;" class="product-extra-icon link">
                                    <i class="icon icon-Question"></i>
                                    Ask A Question
                                </a>
                                <a href="#;" class="product-extra-icon link">
                                    <i class="icon icon-ShareNetwork"></i>
                                    Share
                                </a>
                            </div>

                            <div class="br-line"></div>

                            <div class="tf-product-delivery-return">
                                <div class="product-delivery">
                                    <i class="icon icon-Timer"></i>
                                    <p>
                                        Local Availability:
                                        <span class="fw-semibold">Ready at nearby store</span>
                                    </p>
                                </div>
                                <div class="product-delivery return">
                                    <i class="icon icon-ArrowClockwise"></i>
                                    <p>
                                        Return details are handled by the listed store and will be shown once live shop
                                        policies are connected.
                                    </p>
                                </div>
                            </div>

                            <div class="tf-product-trust-seal">
                                <p class="h6 text-seal">Payment Options:</p>
                                <ul class="list-card">
                                    @foreach (['visa.svg', 'master-card.svg', 'amex.svg', 'paypal.svg', 'water.svg', 'discover.svg'] as $card)
                                        <li class="card-item">
                                            <img width="50" height="32"
                                                src="{{ asset('assets/storefront/images/payment/' . $card) }}"
                                                alt="Payment">
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="tf-sticky-btn-atc">
        <div class="container">
            <div class="tf-height-observer w-100 d-flex align-items-center">
                <div class="tf-sticky-atc-product d-flex align-items-center">
                    <div class="atc-product-side">
                        <div class="prd_img">
                            <img loading="lazy" width="60" height="80" src="{{ $singleImage($product['images'][0]) }}"
                                alt="{{ $product['name'] }}">
                        </div>
                        <div class="prd_info d-none d-lg-grid">
                            <p class="name__prd fw-medium lh-24">{{ $product['name'] }}</p>
                            <p class="distribute__prd text-caption-01 cl-text-3">{{ $product['store'] }}</p>
                            <p class="price__prd fw-semibold">{{ $product['price'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="tf-sticky-atc-infos">
                    <form>
                        <div class="tf-sticky-atc-variant-price">
                            <p class="title">Size:</p>
                            <div class="tf-select style-2">
                                <select>
                                    @foreach ($product['sizes'] as $size)
                                        <option {{ $size === 'M' ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="tf-product-info-quantity">
                            <p class="title">Quantity:</p>
                            <div class="wg-quantity style-2">
                                <button class="btn-quantity minus-btn" type="button">
                                    <i class="icon icon-minus"></i>
                                </button>
                                <input class="quantity-product" type="text" name="number" value="1">
                                <button class="btn-quantity plus-btn" type="button">
                                    <i class="icon icon-plus"></i>
                                </button>
                            </div>
                        </div>
                        <a href="#shoppingCart" data-bs-toggle="offcanvas" class="tf-btn animate-btn btn-add-to-cart">
                            Add To Cart - {{ $product['price'] }}
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <section class="section-product-description flat-spacing">
        <div class="container">
            <div class="faq-descriptions" id="prdDes">
                <div class="accordion-item_v2 style-2">
                    <div class="accordion-action h5 fw-medium" data-bs-target="#description-introduction"
                        data-bs-toggle="collapse" aria-expanded="true" aria-controls="description-introduction"
                        role="button">
                        <span>Introduction</span>
                        <span class="icon ic-accordion-custom cl-2"></span>
                    </div>
                    <div id="description-introduction" class="collapse show" data-bs-parent="#prdDes">
                        <div class="accordion-content tab-content_desc tf-grid-layout md-col-2">
                            <div class="box-desc">
                                <h5 class="desc_title">Product Details</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">
                                        This page is ready for a merchant catalogue product. Images, price, variants,
                                        reviews, and store actions are static placeholders right now.
                                    </p>
                                    <p class="cl-text-2">
                                        The layout keeps the original gallery and product controls from the template,
                                        while the content is adjusted for local shop browsing.
                                    </p>
                                </div>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Highlights</h5>
                                <ul class="list">
                                    <li class="cl-text-2">- Clean product photo gallery</li>
                                    <li class="cl-text-2">- Local store name and availability section</li>
                                    <li class="cl-text-2">- Variant and quantity controls ready for live data</li>
                                    <li class="cl-text-2">- Contact-store action for WindowShop marketplace flow</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item_v2 style-2">
                    <div class="accordion-action h5 fw-medium collapsed" data-bs-target="#customer-reviews"
                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="customer-reviews" role="button">
                        <span>Customer Reviews</span>
                        <span class="icon ic-accordion-custom cl-2"></span>
                    </div>
                    <div id="customer-reviews" class="collapse" data-bs-parent="#prdDes">
                        <div class="accordion-content product-desc_review write-cancel-review-wrap">
                            <div class="box-rating mb-0">
                                <div class="rating-ratio">
                                    <p class="text-display fw-medium">4.8</p>
                                    <div class="star-wrap normal d-flex align-items-center">
                                        @for ($i = 0; $i < 5; $i++)
                                            <i class="icon icon-Star fs-24"></i>
                                        @endfor
                                    </div>
                                    <p class="rate-number">({{ $product['reviews'] }})</p>
                                </div>
                                <div class="rating-progress-list">
                                    @foreach ([60, 24, 10, 4, 2] as $index => $percent)
                                        <div class="rate-progress-star fw-medium">
                                            <span class="number-star">{{ 5 - $index }}</span>
                                            <i class="icon icon-Star fs-20 cl-text-yellow"></i>
                                            <div class="progress" role="progressbar" aria-valuenow="{{ $percent }}"
                                                aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: {{ $percent }}%;"></div>
                                            </div>
                                            <span class="number-percent">{{ $percent }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="box-comment">
                                <div class="comment_info">
                                    <div class="info_image">
                                        <img loading="lazy" width="60" height="60"
                                            src="{{ asset('assets/storefront/images/avatar/avatar-2.jpg') }}"
                                            alt="Customer">
                                    </div>
                                    <div class="info_author">
                                        <p class="h6 author__name">Useful product details before visiting</p>
                                        <p class="author_date text-caption-01 cl-text-3">1 day ago</p>
                                    </div>
                                </div>
                                <p class="comment_text text-body-1">
                                    The photos and store details made it easier to decide whether this was worth
                                    checking in person.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item_v2 style-2">
                    <div class="accordion-action h5 fw-medium collapsed" data-bs-target="#shipping-returns"
                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="shipping-returns" role="button">
                        <span>Shipping & Returns</span>
                        <span class="icon ic-accordion-custom cl-2"></span>
                    </div>
                    <div id="shipping-returns" class="collapse" data-bs-parent="#prdDes">
                        <div class="accordion-content tab-content_desc desc-2 tf-grid-layout sm-col-2 xl-col-4">
                            <div class="box-desc">
                                <h5 class="desc_title">Local Fulfilment</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">Pickup, delivery, or enquiry options can be decided per store.</p>
                                </div>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Store Policies</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">Return and exchange notes will come from each merchant profile.</p>
                                </div>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Availability</h5>
                                <ul class="list">
                                    <li class="cl-text-2">- In-store availability placeholder</li>
                                    <li class="cl-text-2">- Real stock can connect later</li>
                                </ul>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Need Help?</h5>
                                <ul class="list">
                                    <li class="cl-text-2">- Contact the store</li>
                                    <li class="cl-text-2">- Ask product questions</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item_v2 style-2">
                    <div class="accordion-action h5 fw-medium collapsed" data-bs-target="#return-policies"
                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="return-policies" role="button">
                        <span>Return Policies</span>
                        <span class="icon ic-accordion-custom cl-2"></span>
                    </div>
                    <div id="return-policies" class="collapse" data-bs-parent="#prdDes">
                        <div class="accordion-content tab-content_desc desc-3 d-grid">
                            <div class="box-desc">
                                <h5 class="desc_title">Return Policies</h5>
                                <p class="desc_info cl-text-2">
                                    Returns and exchanges depend on each local store policy. WindowShop can show those
                                    rules here once merchant policy data is connected.
                                </p>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Before You Buy</h5>
                                <ul class="list">
                                    <li class="cl-text-2">- Check size, color, and availability with the store</li>
                                    <li class="cl-text-2">- Keep bill or order reference for any return request</li>
                                    <li class="cl-text-2">- Final sale or custom products may not be returnable</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item_v2 style-2">
                    <div class="accordion-action h5 fw-medium collapsed" data-bs-target="#store-info"
                        data-bs-toggle="collapse" aria-expanded="false" aria-controls="store-info" role="button">
                        <span>Store Info</span>
                        <span class="icon ic-accordion-custom cl-2"></span>
                    </div>
                    <div id="store-info" class="collapse" data-bs-parent="#prdDes">
                        <div class="accordion-content tab-content_desc tf-grid-layout md-col-2">
                            <div class="box-desc">
                                <h5 class="desc_title">{{ $product['store'] }}</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">
                                        Local shop information can show address, business hours, website URL, and
                                        contact options when the merchant data is connected.
                                    </p>
                                </div>
                            </div>
                            <div class="box-desc">
                                <h5 class="desc_title">Why This Helps Customers</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">
                                        Customers can understand the product and the seller before visiting, calling, or
                                        placing an enquiry.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-title text-center">
                <h3 class="s-title mb-8">Related Products</h3>
                <p class="s-subtitle">More catalogue items from local shops.</p>
            </div>

            <div class="tf-grid-layout tf-col-4">
                @foreach ($relatedProducts as $relatedProduct)
                    <div class="card-product grid">
                        <div class="card-product_wrapper">
                            <a href="{{ route('storefront.product.detail') }}" class="product-img">
                                <img class="img-product" loading="lazy" width="330" height="440"
                                    src="{{ $productImage($relatedProduct['image']) }}"
                                    alt="{{ $relatedProduct['name'] }}">
                                <img class="img-hover" loading="lazy" width="330" height="440"
                                    src="{{ $productImage($relatedProduct['hover_image']) }}"
                                    alt="{{ $relatedProduct['name'] }}">
                            </a>
                            <ul class="product-action_list">
                                <li class="wishlist">
                                    <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                        <span class="icon icon-heart"></span>
                                        <span class="tooltip">Add to Wishlist</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#;" class="hover-tooltip tooltip-left box-icon">
                                        <span class="icon icon-Eye"></span>
                                        <span class="tooltip">Quick view</span>
                                    </a>
                                </li>
                            </ul>
                            @if ($relatedProduct['badge'])
                                <ul class="product-badge_list">
                                    <li class="product-badge_item text-caption-01 {{ $relatedProduct['badge'] === 'NEW' ? 'new' : 'sale' }}">
                                        {{ $relatedProduct['badge'] }}
                                    </li>
                                </ul>
                            @endif
                            <div class="product-action_bot">
                                <a href="#shoppingCart" data-bs-toggle="offcanvas" class="tf-btn btn-white small w-100">
                                    Add to cart
                                </a>
                            </div>
                        </div>
                        <div class="card-product_info">
                            <a href="{{ route('storefront.product.detail') }}"
                                class="name-product lh-24 fw-medium link-underline-text">
                                {{ $relatedProduct['name'] }}
                            </a>
                            <div class="star-wrap d-flex align-items-center">
                                @for ($i = 0; $i < 5; $i++)
                                    <i class="icon icon-Star"></i>
                                @endfor
                            </div>
                            <div class="price-wrap">
                                <span class="price-new text-primary fw-semibold">{{ $relatedProduct['price'] }}</span>
                                <span class="price-old text-caption-01 cl-text-3">{{ $relatedProduct['old_price'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/storefront/js/plugin/drift.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/zoom.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/plugin/photoswipe-lightbox.umd.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/plugin/photoswipe.umd.min.js') }}"></script>
@endpush
