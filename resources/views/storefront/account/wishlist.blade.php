@extends('storefront.layouts.app')

@section('title', 'Wishlist | WindowShop')
@section('meta_description', 'WindowShop customer wishlist area.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Wishlist'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Wishlist</p>
            <h4 class="mb-10">Wishlist</h4>
            <p class="cl-text-2 mb-0">Save products you like and find them easily later.</p>
        </div>

        <div class="account-wishlist-grid" data-wishlist-grid>
            @foreach ($wishlistProducts as $product)
                <div class="account-wishlist-card" data-wishlist-card="{{ $product['product_id'] }}">
                    <a href="{{ $product['url'] }}" class="account-wishlist-image">
                        <img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" loading="lazy">
                    </a>
                    <div class="account-wishlist-info">
                        <p class="text-caption-01 cl-text-3 mb-4">{{ $product['store'] }}</p>
                        <a href="{{ $product['url'] }}" class="name-product fw-medium link-underline-text">{{ $product['name'] }}</a>
                        <div class="price-wrap mt-8">
                            <span class="price-new text-primary fw-semibold">{{ $product['price'] }}</span>
                            @if ($product['old_price'])
                                <span class="price-old text-caption-01 cl-text-3">{{ $product['old_price'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="account-wishlist-actions">
                        <a href="{{ $product['url'] }}" class="tf-btn btn-line small">View Product</a>
                        <button type="button"
                            class="tf-btn btn-line small js-wishlist-toggle is-wishlisted"
                            data-wishlist-toggle
                            data-wishlist-product-id="{{ $product['product_id'] }}"
                            data-wishlist-store-url="{{ $product['wishlist_store_url'] }}"
                            data-wishlist-destroy-url="{{ $product['wishlist_destroy_url'] }}"
                            data-wishlist-state="1"
                            data-login-url="{{ route('storefront.login') }}"
                            title="Remove from Wishlist"
                            aria-label="Remove from Wishlist">
                            <span data-wishlist-label>Remove</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="account-empty-panel {{ $wishlistProducts->isEmpty() ? '' : 'd-none' }}" data-wishlist-empty>
            <h6 class="mb-6">Your wishlist is empty.</h6>
            <p class="cl-text-2 mb-16">Save products you like and find them easily later.</p>
            <a href="{{ route('storefront.products') }}" class="tf-btn btn-fill small">Continue Shopping</a>
        </div>
    @endcomponent
@endsection

@include('storefront.components.wishlist-assets')

@push('styles')
    <style>
        .account-wishlist-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        }

        .account-wishlist-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }

        .account-wishlist-image {
            aspect-ratio: 3 / 4;
            background: #f7f7f7;
            display: block;
        }

        .account-wishlist-image img {
            height: 100%;
            object-fit: cover;
            width: 100%;
        }

        .account-wishlist-info {
            flex: 1;
            min-width: 0;
            padding: 14px;
        }

        .account-wishlist-actions {
            border-top: 1px solid var(--line);
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
            padding: 12px 14px;
        }

        .account-wishlist-actions .tf-btn {
            min-width: 0;
        }
    </style>
@endpush
