@extends('storefront.layouts.app')

@php
    $marketplaceName = app(\App\Services\System\SystemSettingService::class)->marketplaceName();
    $singleImage = fn(string $image): string => $image;
    $productMetaTitle = $product['meta_title'].' | '.$marketplaceName;
    $productMetaDescription = rtrim($product['meta_description'], '.').' on '.$marketplaceName.'.';
    $selectedColor = $product['colors'][0]['name'] ?? '';
    $selectedSize = $product['sizes'][0]['name'] ?? '';
    $deliveryCheck = session('delivery_check');
    $deliveryCheck = is_array($deliveryCheck) && ($deliveryCheck['product_slug'] ?? null) === $product['slug'] ? $deliveryCheck : null;
    $deliveryCheckError = $errors->getBag('deliveryCheck')->first('postal_code');
    $deliveryCheckMessage = $deliveryCheckError ?: (is_array($deliveryCheck) ? ($deliveryCheck['message'] ?? '') : '');
    $deliveryCheckMessageType = $deliveryCheckError ? 'error' : (($deliveryCheck['status'] ?? null) === 'available' ? 'success' : 'error');
    $shopOfferUrl = $product['store_url'] ?: '#;';
    $shopOffers = [
        [
            'title' => 'Shop Offers',
            'subtitle' => 'Store deals will appear here',
            'description' => 'This section is ready for merchant offers, discounts, and local shop promotions.',
            'button' => 'View Shop',
            'image' => asset('assets/storefront/images/section/banner-4.jpg'),
            'url' => $shopOfferUrl,
        ],
        [
            'title' => 'Local Deals',
            'subtitle' => 'Coming soon from this shop',
            'description' => 'Show coupon codes, seasonal savings, and in-store offer notes once connected.',
            'button' => 'View Shop',
            'image' => asset('assets/storefront/images/section/banner-4.jpg'),
            'url' => $shopOfferUrl,
        ],
    ];
@endphp

@section('title', $productMetaTitle)
@section('meta_description', $productMetaDescription)

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/drift-basic.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/photoswipe.css') }}">
@endpush

@section('content')
    <div class="flat-spacing pt-0 pb-0">
        <div class="container">
            <div class="category-listing-header product-detail-breadcrumb-header">
                <div class="category-listing-breadcrumbs pb-3">
                    <a href="{{ route('storefront.home') }}">Home</a>
                    <span>/</span>
                    <a href="{{ route('storefront.products') }}">Products</a>
                    <span>/</span>
                    <a href="{{ $product['category_url'] }}">{{ $product['category'] }}</a>
                    <span>/</span>
                    <span>{{ $product['name'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <section class="section-product-single tf-main-product section-image-zoom flat-spacing pt-0">
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
                                            <div class="swiper-slide" data-color="{{ $selectedColor }}" data-size="{{ $selectedSize }}">
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
                                <p class="product-infor-cate text-caption-01 mb-4">{{ $product['availability'] }}</p>
                                <div class="product-heading-row mb-12">
                                    <h1 class="product-infor-name mb-0">{{ $product['name'] }}</h1>
                                    <a href="#;" class="box-icon product-wishlist-btn" title="Add to Wishlist" aria-label="Add to Wishlist">
                                        <span class="icon icon-heart"></span>
                                    </a>
                                </div>

                                <div class="product-infor-meta mb-16">
                                    <div class="meta_rate">
                                        @if ($product['reviews'] !== '0 reviews')
                                            <div class="star-wrap normal d-flex align-items-center">
                                                @for ($i = 0; $i < 5; $i++)
                                                    <i class="icon icon-Star"></i>
                                                @endfor
                                            </div>
                                            <span class="text-caption-01 cl-text-2">({{ $product['reviews'] }})</span>
                                        @else
                                            <span class="text-caption-01 cl-text-2">Reviews coming soon</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="product-infor-price mb-12">
                                    <h4 class="price-on-sale">{{ $product['price'] }}</h4>
                                    <div class="br-line type-vertical"></div>
                                    @if ($product['old_price'])
                                        <p class="cl-text-3 text-decoration-line-through">{{ $product['old_price'] }}</p>
                                    @endif
                                    @if ($product['discount'])
                                        <span class="badge-sale text-white fw-semibold text-caption-02">
                                            {{ $product['discount'] }}
                                        </span>
                                    @endif
                                </div>

                                <div class="product-infor-desc-wrap mb-12" data-product-description>
                                    <p class="product-infor-desc cl-text-2 mb-0">{{ $product['description'] }}</p>
                                    <button type="button" class="product-infor-desc-toggle" data-product-description-toggle hidden>
                                        Read more
                                    </button>
                                </div>

                            </div>

                            <div class="br-line"></div>

                            <div class="tf-product-variant">
                                @if (! empty($product['colors']))
                                    <div class="variant-picker-item variant-color">
                                        <div class="variant-picker-label">
                                            <div>
                                                Colors:
                                                <span class="variant-picker-label-value value-currentColor text-capitalize fw-medium">
                                                    {{ $selectedColor }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="variant-picker-values">
                                            @foreach ($product['colors'] as $color)
                                                <div class="hover-tooltip tooltip-bot color-btn {{ $loop->first ? 'active' : '' }}"
                                                    data-color="{{ $color['name'] }}">
                                                    <span class="swatch-value rounded-circle border"
                                                        style="display: block; width: 34px; height: 34px; background: {{ $color['hex'] ?: '#f3f4f6' }};"></span>
                                                    <span class="tooltip">{{ $color['name'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($product['sizes']))
                                    <div class="variant-picker-item variant-size">
                                        <div class="variant-picker-label">
                                            <div>
                                                Size:
                                                <span class="variant-picker-label-value value-currentSize text-capitalize fw-medium">{{ $selectedSize }}</span>
                                            </div>
                                            @if (! empty($product['size_guide']))
                                                <a href="#findSize" data-bs-toggle="modal"
                                                    class="tf-btn-line-2 style-primary text-caption-01 fw-semibold">
                                                    Size Guide
                                                </a>
                                            @endif
                                        </div>
                                        <div class="variant-picker-values">
                                            @foreach ($product['sizes'] as $size)
                                                <span class="size-btn {{ $loop->first ? 'active' : '' }}"
                                                    data-size="{{ $size['name'] }}"
                                                    data-price="{{ $size['raw_price'] }}"
                                                    data-price-label="{{ $size['price'] }}"
                                                    data-variant-id="{{ $size['variant_id'] }}">{{ $size['name'] }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="tf-product-total-quantity">
                                    <p>Quantity:</p>
                                    <form method="POST" action="{{ $product['add_to_cart_url'] }}" class="group-action" data-add-to-cart-form>
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $product['selected_variant_id'] }}" data-cart-variant-input>
                                        <div class="wg-quantity">
                                            <button class="btn-quantity btn-decrease" type="button">
                                                <i class="icon icon-minus"></i>
                                            </button>
                                            <input class="quantity-product" type="text" name="quantity" value="1" inputmode="numeric" data-cart-quantity-input>
                                            <button class="btn-quantity btn-increase" type="button">
                                                <i class="icon icon-plus"></i>
                                            </button>
                                        </div>
                                        <button type="submit"
                                            class="btn-action-price tf-btn type-xl animate-btn w-100"
                                            data-add-to-cart-button
                                            {{ $product['can_add_to_cart'] ? '' : 'disabled' }}>
                                            {{ $product['can_add_to_cart'] ? 'Add To Cart' : 'Out of Stock' }}
                                            <span class="d-none d-sm-block d-md-none d-lg-block">&nbsp;-&nbsp;</span>
                                            <span class="price-add d-none d-sm-block d-md-none d-lg-block">{{ $product['price'] }}</span>
                                        </button>
                                    </form>
                                    <p class="text-caption-01 mt-2 mb-0 {{ $errors->getBag('cart')->any() ? 'text-danger' : 'text-success' }}"
                                        data-add-to-cart-message
                                        role="status"
                                        {{ $errors->getBag('cart')->any() || session('cart_success') ? '' : 'hidden' }}>
                                        {{ $errors->getBag('cart')->first('quantity') ?: $errors->getBag('cart')->first('product_variant_id') ?: session('cart_success') }}
                                    </p>
                                </div>
                            </div>

                            <div class="br-line"></div>

                            <div class="product-delivery-check" data-product-delivery-check>
                                <div class="product-delivery-check__heading">
                                    <label for="product-delivery-postal-code" class="product-delivery-check__label">
                                        Check Delivery Availability
                                    </label>
                                    <span class="product-delivery-check__current" data-product-delivery-current {{ $currentPostalCode ? '' : 'hidden' }}>
                                        Current PIN: {{ $currentPostalCode }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ $product['delivery_check_url'] }}" class="product-delivery-check__form" data-product-delivery-form>
                                    @csrf
                                    <input
                                        id="product-delivery-postal-code"
                                        type="text"
                                        name="postal_code"
                                        value="{{ old('postal_code', $currentPostalCode ?? '') }}"
                                        inputmode="numeric"
                                        pattern="[0-9]{6}"
                                        maxlength="6"
                                        autocomplete="postal-code"
                                        placeholder="Enter Delivery Pincode"
                                        class="product-delivery-check__input {{ $deliveryCheckError ? 'is-invalid' : '' }}"
                                        data-product-delivery-input>
                                    <button type="submit" class="product-delivery-check__button" data-product-delivery-button>Check</button>
                                </form>
                                <p
                                    class="product-delivery-check__message product-delivery-check__message--{{ $deliveryCheckMessageType }}"
                                    data-product-delivery-message
                                    role="status"
                                    {{ $deliveryCheckMessage !== '' ? '' : 'hidden' }}>
                                    {{ $deliveryCheckMessage }}
                                </p>
                            </div>

                            <div class="sold-by-card">
                                <h5 class="sold-by-card__title">Sold By</h5>
                                <div class="sold-by-card__content">
                                    <div class="sold-by-card__icon">
                                        <i class="icon icon-storefront"></i>
                                    </div>
                                    <div class="sold-by-card__body">
                                        <p class="sold-by-card__name">{{ $product['store'] }}</p>
                                        @if ($product['store_address'])
                                            <p class="sold-by-card__meta">{{ $product['store_address'] }}</p>
                                        @endif
                                        <p class="sold-by-card__meta">Local Availability: {{ $product['availability'] }}</p>
                                    </div>
                                    @if ($product['store_url'])
                                        <a href="{{ $product['store_url'] }}" class="tf-btn btn-line sold-by-card__button">View Shop</a>
                                    @endif
                                </div>
                                <div class="sold-by-card__actions">
                                    @if ($product['store_whatsapp_url'])
                                        <a href="{{ $product['store_whatsapp_url'] }}" class="link sold-by-card__whatsapp" target="_blank" rel="noopener noreferrer" aria-label="Chat with {{ $product['store'] }} on WhatsApp">
                                            <svg aria-hidden="true" class="sold-by-card__whatsapp-icon" viewBox="0 0 32 32" focusable="false">
                                                <path d="M19.11 17.54c-.29-.14-1.71-.84-1.98-.94-.27-.1-.46-.14-.65.14-.19.29-.75.94-.92 1.13-.17.19-.34.21-.63.07-.29-.14-1.22-.45-2.33-1.44-.86-.77-1.44-1.72-1.61-2.01-.17-.29-.02-.44.13-.59.13-.13.29-.34.43-.51.14-.17.19-.29.29-.48.1-.19.05-.36-.02-.51-.07-.14-.65-1.57-.89-2.15-.23-.56-.47-.48-.65-.49h-.56c-.19 0-.51.07-.77.36-.27.29-1.01.99-1.01 2.41s1.04 2.8 1.18 2.99c.14.19 2.04 3.12 4.95 4.37.69.3 1.23.48 1.65.61.69.22 1.32.19 1.82.12.56-.08 1.71-.7 1.95-1.37.24-.67.24-1.25.17-1.37-.07-.12-.26-.19-.55-.34ZM16.03 4.8c-6.17 0-11.18 5.01-11.18 11.18 0 2.11.59 4.08 1.61 5.76L4.75 28l6.4-1.68a11.1 11.1 0 0 0 4.88 1.13c6.17 0 11.18-5.01 11.18-11.18S22.2 4.8 16.03 4.8Zm0 20.74c-1.61 0-3.18-.41-4.58-1.18l-.33-.18-3.8 1 1.01-3.7-.22-.38a9.48 9.48 0 0 1-1.36-4.89c0-5.25 4.03-9.52 9.28-9.52s9.28 4.27 9.28 9.52-4.03 9.33-9.28 9.33Z"></path>
                                            </svg>
                                            WhatsApp
                                        </a>
                                    @endif
                                    <a href="#;" class="link">
                                        <i class="icon icon-Question"></i>
                                        Ask A Question
                                    </a>
                                    <a href="#;" class="link">
                                        <i class="icon icon-ShareNetwork"></i>
                                        Share
                                    </a>
                                </div>
                            </div>

                            <div class="br-line"></div>

                            <div class="tf-product-delivery-return">
                                <div class="product-delivery">
                                    <i class="icon icon-Timer"></i>
                                    <p>
                                        Local Availability:
                                        <span class="fw-semibold">{{ $product['availability'] }}</span>
                                    </p>
                                </div>
                                @if ($product['availability_note'])
                                    <div class="product-delivery return">
                                        <i class="icon icon-Info"></i>
                                        <p>{{ $product['availability_note'] }}</p>
                                    </div>
                                @endif
                                <div class="product-delivery return">
                                    <i class="icon icon-ArrowClockwise"></i>
                                    <p>
                                        Return details are handled by the listed store and will be shown once live shop
                                        policies are connected.
                                    </p>
                                </div>
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
                    <form method="POST" action="{{ $product['add_to_cart_url'] }}" data-add-to-cart-form>
                        @csrf
                        <input type="hidden" name="product_variant_id" value="{{ $product['selected_variant_id'] }}" data-cart-variant-input>
                        @if (! empty($product['sizes']))
                            <div class="tf-sticky-atc-variant-price">
                                <p class="title">Size:</p>
                                <div class="tf-select style-2">
                                    <select data-sticky-variant-select>
                                        @foreach ($product['sizes'] as $size)
                                            <option value="{{ $size['name'] }}"
                                                data-price="{{ $size['raw_price'] }}"
                                                data-price-label="{{ $size['price'] }}"
                                                data-variant-id="{{ $size['variant_id'] }}"
                                                {{ $loop->first ? 'selected' : '' }}>{{ $size['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        <div class="tf-product-info-quantity">
                            <p class="title">Quantity:</p>
                            <div class="wg-quantity style-2">
                                <button class="btn-quantity minus-btn" type="button">
                                    <i class="icon icon-minus"></i>
                                </button>
                                <input class="quantity-product" type="text" name="quantity" value="1" inputmode="numeric" data-cart-quantity-input>
                                <button class="btn-quantity plus-btn" type="button">
                                    <i class="icon icon-plus"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit"
                            class="tf-btn animate-btn btn-add-to-cart"
                            data-add-to-cart-button
                            {{ $product['can_add_to_cart'] ? '' : 'disabled' }}>
                            {{ $product['can_add_to_cart'] ? 'Add To Cart' : 'Out of Stock' }} - <span class="sticky-price-add">{{ $product['price'] }}</span>
                        </button>
                        <p class="text-caption-01 mt-2 mb-0" data-add-to-cart-message role="status" hidden></p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if (! empty($product['size_guide']))
        <div class="modal modalCentered fade modal-find_size" id="findSize">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-heading d-flex align-items-center justify-content-between">
                        <h4 class="title-pop">{{ $product['size_guide']['title'] }}</h4>
                        <span class="cs-pointer d-flex link" data-bs-dismiss="modal">
                            <i class="icon-X2 fs-24"></i>
                        </span>
                    </div>
                    <div class="modal-main">
                        <div class="tf-rte">
                            <div class="tf-table-res-df mb-0">
                                <p class="h6 fw-medium mb-16 cl-text-main">{{ $product['size_guide']['subtitle'] }}</p>
                                <img loading="lazy" class="img-fluid w-100"
                                    src="{{ $product['size_guide']['image'] }}"
                                    alt="{{ $product['size_guide']['title'] }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <section class="product-shop-offers flat-spacing pt-0 pb-0">
        <div class="container">
            <div class="product-shop-offers__heading">
                <h5>Offers from {{ $product['store'] }}</h5>
                <p>Shop-level offers will appear here once merchant promotions are connected.</p>
            </div>
            <div class="tf-grid-layout xs-col-1 sm-col-2 md-col-2 flat-spacing-2 pt-0">
                @foreach ($shopOffers as $offer)
                    <div class="banner-image-text type-abs style-1 product-shop-offer">
                        <a href="{{ $offer['url'] }}" class="bn-image img-style">
                            <img loading="lazy" width="690" height="388" src="{{ $offer['image'] }}" alt="{{ $offer['title'] }}">
                        </a>
                        <div class="bn-content">
                            <p class="product-shop-offer__eyebrow text-white">{{ $offer['subtitle'] }}</p>
                            <a href="{{ $offer['url'] }}" class="title h4 fw-medium text-white link-dark">
                                {{ $offer['title'] }}
                            </a>
                            <p class="desc text-white text-body-1">
                                {{ $offer['description'] }}
                            </p>
                            <a href="{{ $offer['url'] }}" class="btn-action tf-btn btn-white">
                                {{ $offer['button'] }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <section class="section-product-description flat-spacing pt-0">
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
                                <h5 class="desc_title">Description</h5>
                                <div class="desc_info">
                                    <p class="cl-text-2">
                                        {{ $product['description'] }}
                                    </p>
                                </div>
                            </div>
                            <div class="box-desc product-specification">
                                <h5 class="desc_title">Product Details</h5>
                                <div class="product_d_table product-detail-spec-table">
                                    <table>
                                        <tbody>
                                            <tr>
                                                <td>Sold By</td>
                                                <td>{{ $product['store'] }}</td>
                                            </tr>
                                            <tr>
                                                <td>Category</td>
                                                <td>{{ $product['category'] }}</td>
                                            </tr>
                                            <tr>
                                                <td>SKU</td>
                                                <td>{{ $product['sku'] }}</td>
                                            </tr>
                                            <tr>
                                                <td>Availability</td>
                                                <td>{{ $product['availability'] }}</td>
                                            </tr>
                                            @foreach ($product['other_attributes'] as $attribute)
                                                <tr>
                                                    <td>{{ $attribute['label'] }}</td>
                                                    <td>{{ $attribute['value'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (! empty($product['disclaimers']))
                    <div class="accordion-item_v2 style-2">
                        <div class="accordion-action h5 fw-medium collapsed" data-bs-target="#product-disclaimer"
                            data-bs-toggle="collapse" aria-expanded="false" aria-controls="product-disclaimer"
                            role="button">
                            <span>Product Disclaimer</span>
                            <span class="icon ic-accordion-custom cl-2"></span>
                        </div>
                        <div id="product-disclaimer" class="collapse" data-bs-parent="#prdDes">
                            <div class="accordion-content tab-content_desc">
                                <div class="box-desc product-disclaimer">
                                    <div class="product-disclaimer-list">
                                        @foreach ($product['disclaimers'] as $disclaimer)
                                            <p>{{ $disclaimer }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

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
                                    <p class="rate-number">Sample review layout</p>
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
                                        <p class="author_date text-caption-01 cl-text-3">Sample placeholder</p>
                                    </div>
                                </div>
                                <p class="comment_text text-body-1">
                                    This review area is a storefront placeholder so we can finalise the UI before real
                                    customer reviews are connected.
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
                                        {{ $product['store_address'] ?: 'Store address and contact details will appear here when available.' }}
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
                @forelse ($relatedProducts as $relatedProduct)
                    <div class="card-product grid">
                        <div class="card-product_wrapper">
                            <a href="{{ $relatedProduct['url'] }}" class="product-img">
                                <img class="img-product" loading="lazy" width="330" height="440"
                                    src="{{ $relatedProduct['image'] }}"
                                    alt="{{ $relatedProduct['name'] }}">
                                <img class="img-hover" loading="lazy" width="330" height="440"
                                    src="{{ $relatedProduct['hover_image'] }}"
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
                                    <li class="product-badge_item text-caption-01 {{ $relatedProduct['badge_class'] }}">
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
                            <a href="{{ $relatedProduct['url'] }}"
                                class="name-product lh-24 fw-medium link-underline-text">
                                {{ $relatedProduct['name'] }}
                            </a>
                            <div class="price-wrap">
                                <span class="price-new text-primary fw-semibold">{{ $relatedProduct['price'] }}</span>
                                @if ($relatedProduct['old_price'])
                                    <span class="price-old text-caption-01 cl-text-3">{{ $relatedProduct['old_price'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-center cl-text-2 mb-0">No related products found.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/storefront/js/plugin/drift.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/plugin/photoswipe-lightbox.umd.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/plugin/photoswipe.umd.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/zoom.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-product-description]').forEach((wrap) => {
                const text = wrap.querySelector('.product-infor-desc');
                const toggle = wrap.querySelector('[data-product-description-toggle]');

                if (!text || !toggle) {
                    return;
                }

                wrap.classList.add('is-collapsed');

                if (text.scrollHeight <= text.clientHeight + 2) {
                    wrap.classList.remove('is-collapsed');
                    return;
                }

                toggle.hidden = false;
                toggle.addEventListener('click', () => {
                    const collapsed = wrap.classList.toggle('is-collapsed');
                    toggle.textContent = collapsed ? 'Read more' : 'Read less';
                });
            });

            document.querySelectorAll('[data-add-to-cart-form]').forEach((form) => {
                const variantInput = form.querySelector('[data-cart-variant-input]');
                const quantityInput = form.querySelector('[data-cart-quantity-input]');
                const button = form.querySelector('[data-add-to-cart-button]');
                const message = form.parentElement ? form.parentElement.querySelector('[data-add-to-cart-message]') : null;
                const defaultButtonText = button ? button.textContent.trim() : 'Add To Cart';

                const csrfToken = () => {
                    const tokenInput = form.querySelector('input[name="_token"]');
                    const metaToken = document.querySelector('meta[name="csrf-token"]');

                    return tokenInput ? tokenInput.value : (metaToken ? metaToken.getAttribute('content') : '');
                };

                const showCartMessage = (text, type) => {
                    if (!message) {
                        return;
                    }

                    message.textContent = text;
                    message.hidden = false;
                    message.classList.toggle('text-success', type === 'success');
                    message.classList.toggle('text-danger', type !== 'success');
                };

                const setLoading = (isLoading) => {
                    if (!button) {
                        return;
                    }

                    button.disabled = isLoading;
                    button.dataset.loading = isLoading ? 'true' : 'false';
                    button.childNodes[0].textContent = isLoading ? 'Adding...' : defaultButtonText.replace(/\s+-\s+.*$/, '');
                };

                document.querySelectorAll('[data-size][data-variant-id]').forEach((sizeButton) => {
                    sizeButton.addEventListener('click', () => {
                        if (variantInput) {
                            variantInput.value = sizeButton.dataset.variantId || variantInput.value;
                        }

                        const price = sizeButton.dataset.priceLabel;
                        const priceTarget = form.querySelector('.price-add') || form.querySelector('.sticky-price-add');

                        if (price && priceTarget) {
                            priceTarget.textContent = price;
                        }
                    });
                });

                const stickySelect = form.querySelector('[data-sticky-variant-select]');

                if (stickySelect) {
                    stickySelect.addEventListener('change', () => {
                        const option = stickySelect.selectedOptions[0];

                        if (!option) {
                            return;
                        }

                        if (variantInput) {
                            variantInput.value = option.dataset.variantId || variantInput.value;
                        }

                        const priceTarget = form.querySelector('.sticky-price-add');

                        if (option.dataset.priceLabel && priceTarget) {
                            priceTarget.textContent = option.dataset.priceLabel;
                        }
                    });
                }

                if (!quantityInput || !button || !window.fetch) {
                    return;
                }

                quantityInput.addEventListener('input', () => {
                    quantityInput.value = quantityInput.value.replace(/[^\d.]/g, '');
                });

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    setLoading(true);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken(),
                            },
                            body: new FormData(form),
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const errors = data.errors || {};
                            const firstError = Object.values(errors).flat()[0];
                            showCartMessage(firstError || data.message || 'Could not add this product to cart.', 'error');
                            return;
                        }

                        document.querySelectorAll('[data-storefront-cart-count]').forEach((count) => {
                            count.textContent = data.cart_count || '0';
                        });
                        showCartMessage(data.message || 'Product added to cart.', 'success');
                    } catch (error) {
                        showCartMessage('Could not add this product to cart. Please try again.', 'error');
                    } finally {
                        setLoading(false);
                    }
                });
            });

            document.querySelectorAll('[data-product-delivery-form]').forEach((form) => {
                const panel = form.closest('[data-product-delivery-check]');
                const input = form.querySelector('[data-product-delivery-input]');
                const button = form.querySelector('[data-product-delivery-button]');
                const current = panel ? panel.querySelector('[data-product-delivery-current]') : null;
                const message = panel ? panel.querySelector('[data-product-delivery-message]') : null;
                const defaultButtonText = button ? button.textContent : 'Check';

                const csrfToken = () => {
                    const tokenInput = form.querySelector('input[name="_token"]');
                    const metaToken = document.querySelector('meta[name="csrf-token"]');

                    return tokenInput ? tokenInput.value : (metaToken ? metaToken.getAttribute('content') : '');
                };

                const showMessage = (text, type) => {
                    if (!message) {
                        return;
                    }

                    message.textContent = text;
                    message.hidden = false;
                    message.classList.remove('product-delivery-check__message--success', 'product-delivery-check__message--error');
                    message.classList.add(type === 'success' ? 'product-delivery-check__message--success' : 'product-delivery-check__message--error');
                };

                const setLoading = (isLoading) => {
                    if (!button) {
                        return;
                    }

                    button.disabled = isLoading;
                    button.textContent = isLoading ? 'Checking...' : defaultButtonText;
                };

                const syncLocationLabels = (postalCode) => {
                    if (!postalCode) {
                        return;
                    }

                    if (current) {
                        current.textContent = `Current PIN: ${postalCode}`;
                        current.hidden = false;
                    }

                    document.querySelectorAll('.customer-location-trigger').forEach((trigger) => {
                        const label = `Shopping near ${postalCode}. Change location.`;
                        trigger.setAttribute('aria-label', label);
                        trigger.dataset.locationTooltip = label;
                    });

                    const modal = document.getElementById('customer-location-modal');
                    if (modal) {
                        modal.dataset.currentPostalCode = postalCode;

                        const modalInput = modal.querySelector('#customer-location-postal-code');
                        const modalCurrentWrap = modal.querySelector('.customer-location-current');
                        const modalCurrent = modal.querySelector('.customer-location-current span');

                        if (modalInput) {
                            modalInput.value = postalCode;
                        }

                        if (modalCurrent) {
                            modalCurrent.textContent = postalCode;
                        }

                        if (modalCurrentWrap) {
                            modalCurrentWrap.hidden = false;
                        }
                    }
                };

                if (!input || !button || !window.fetch) {
                    return;
                }

                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(0, 6);
                    input.classList.remove('is-invalid');
                });

                form.addEventListener('submit', async (event) => {
                    const postalCode = input.value.trim();

                    if (!/^\d{6}$/.test(postalCode)) {
                        event.preventDefault();
                        input.classList.add('is-invalid');
                        showMessage('Enter a valid 6-digit PIN code.', 'error');
                        return;
                    }

                    event.preventDefault();
                    input.classList.remove('is-invalid');
                    setLoading(true);

                    try {
                        const body = new FormData(form);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken(),
                            },
                            body,
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const errors = data.errors && data.errors.postal_code;
                            input.classList.add('is-invalid');
                            showMessage(errors && errors.length ? errors[0] : (data.message || 'Delivery check failed. Please try again.'), 'error');
                            return;
                        }

                        const nextPostalCode = data.postal_code || postalCode;
                        input.value = nextPostalCode;
                        syncLocationLabels(nextPostalCode);
                        showMessage(data.message || 'Delivery availability checked.', data.status === 'available' ? 'success' : 'error');
                    } catch (error) {
                        showMessage('Delivery check failed. Please try again.', 'error');
                    } finally {
                        setLoading(false);
                    }
                });
            });
        });
    </script>
@endpush
