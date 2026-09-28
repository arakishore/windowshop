@extends('storefront.layouts.app')

@section('title', 'My Wishlist | ' . $marketplaceName)
@section('meta_description', 'Review saved products and continue shopping on '.$marketplaceName.'.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Wishlist', 'accountCompact' => true])
        <div class="wishlist-head">
            <div class="wishlist-heading">
                <div class="wishlist-title-row">
                    <span class="wishlist-title-icon" aria-hidden="true"><i class="icon icon-HeartStraight"></i></span>
                    <h1>My Wishlist</h1>
                    <span class="wishlist-count" data-wishlist-count>({{ $wishlistProducts->count() }})</span>
                </div>
                <p>Keep your favourite finds close and compare them whenever you are ready.</p>
            </div>
        </div>

        <div class="account-wishlist-grid" data-wishlist-grid>
            @foreach ($wishlistProducts as $product)
                <article class="account-wishlist-card" data-wishlist-card="{{ $product['product_id'] }}">
                    <div class="account-wishlist-media">
                        <a href="{{ $product['url'] }}" class="account-wishlist-image" aria-label="View {{ $product['name'] }}">
                            <img src="{{ asset($product['image']) }}" alt="{{ $product['name'] }}" loading="lazy" width="250" height="250">
                        </a>
                        @if ($product['badge'])
                            <span class="wishlist-discount">{{ $product['badge'] }} off</span>
                        @endif
                        <button type="button" class="wishlist-heart js-wishlist-toggle is-wishlisted"
                            data-wishlist-toggle data-wishlist-product-id="{{ $product['product_id'] }}"
                            data-wishlist-store-url="{{ $product['wishlist_store_url'] }}"
                            data-wishlist-destroy-url="{{ $product['wishlist_destroy_url'] }}" data-wishlist-state="1"
                            data-login-url="{{ route('storefront.login') }}" title="Remove from Wishlist"
                            aria-label="Remove {{ $product['name'] }} from Wishlist">
                            <i class="icon icon-HeartStraight"></i>
                        </button>
                    </div>
                    <div class="account-wishlist-info">
                        <p class="wishlist-store">{{ $product['store'] }}</p>
                        <a href="{{ $product['url'] }}" class="wishlist-product-name">{{ $product['name'] }}</a>
                        <div class="wishlist-price">
                            <span>{{ $product['price'] }}</span>
                            @if ($product['old_price']) <del>{{ $product['old_price'] }}</del> @endif
                        </div>
                        @if ($product['promotion_text']) <p class="wishlist-offer">{{ $product['promotion_text'] }}</p> @endif
                    </div>
                    <div class="account-wishlist-actions ">
                        <a href="{{ $product['url'] }}" class="wishlist-view-product animate-btn"><i class="icon icon-Eye"></i><span>View Product</span></a>
                        <button type="button" class="wishlist-remove js-wishlist-toggle is-wishlisted animate-btn"
                            data-wishlist-toggle data-wishlist-product-id="{{ $product['product_id'] }}"
                            data-wishlist-store-url="{{ $product['wishlist_store_url'] }}"
                            data-wishlist-destroy-url="{{ $product['wishlist_destroy_url'] }}" data-wishlist-state="1"
                            data-login-url="{{ route('storefront.login') }}" title="Remove from Wishlist"
                            aria-label="Remove {{ $product['name'] }} from Wishlist">
                            <i class="icon icon-X2"></i><span data-wishlist-label>Remove</span>
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="account-empty-panel {{ $wishlistProducts->isEmpty() ? '' : 'd-none' }} wishlist-empty" data-wishlist-empty>
            <span class="wishlist-empty-icon" aria-hidden="true"><i class="icon icon-HeartStraight"></i></span>
            <h2>Your wishlist is empty.</h2>
            <p>Save products you like and find them easily later. Explore local collections to discover something new.</p>
            <a href="{{ route('storefront.products') }}" class="wishlist-primary-action animate-btn">Continue Shopping</a>
        </div>

        <aside class="wishlist-discovery {{ $wishlistProducts->isEmpty() ? 'd-none' : '' }}" data-wishlist-discovery>
            <div class="wishlist-discovery-art" aria-hidden="true"><i class="icon icon-HeartStraight"></i></div>
            <div>
                <h2>Find more to love</h2>
                <p>Explore fresh arrivals from local shops and add your next favourite.</p>
            </div>
            <a href="{{ route('storefront.products') }}" class="wishlist-primary-action">Continue Shopping</a>
        </aside>
    @endcomponent
@endsection

@include('storefront.components.wishlist-assets')

@push('styles')
    <style>
        .wishlist-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
        .wishlist-title-row { display: flex; align-items: center; gap: 9px; }
        .wishlist-title-row h1 { margin: 0; font-size: 30px; line-height: 1.2; letter-spacing: 0; }
        .wishlist-title-icon { color: #e33243; font-size: 28px; line-height: 1; }
        .wishlist-count { color: #7b8490; font-size: 17px; font-weight: 700; }
        .wishlist-heading p { margin: 7px 0 0; color: #717985; font-size: 14px; }
        .account-wishlist-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .account-wishlist-card { display: flex; min-width: 0; flex-direction: column; overflow: hidden; border: 1px solid #e5e8ec; border-radius: 7px; background: #fff; box-shadow: 0 6px 18px rgba(20, 25, 31, .04); }
        .account-wishlist-media { position: relative; overflow: hidden; background: #f4f5f6; }
        .account-wishlist-image { display: block; aspect-ratio: 6 / 5; }
        .account-wishlist-image img { width: 100%; height: 100%; object-fit: contain; transition: transform .25s ease; }
        .account-wishlist-card:hover .account-wishlist-image img { transform: scale(1.025); }
        .wishlist-discount { position: absolute; top: 10px; left: 10px; padding: 5px 7px; border-radius: 4px; background: #e33243; color: #fff; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .wishlist-heart { position: absolute; top: 9px; right: 9px; display: inline-flex; width: 34px; height: 34px; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: rgba(255,255,255,.94) !important; color: #e33243 !important; box-shadow: 0 3px 12px rgba(20,25,31,.08); }
        .account-wishlist-info { flex: 1; padding: 13px 13px 10px; }
        .wishlist-store { margin: 0 0 5px; overflow: hidden; color: #78818c; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
        .wishlist-product-name { display: -webkit-box; min-height: 40px; overflow: hidden; color: #15191e; font-size: 14px; font-weight: 700; line-height: 1.4; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .wishlist-price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 7px; margin-top: 8px; }
        .wishlist-price span { color: #e33243; font-size: 15px; font-weight: 800; }
        .wishlist-price del { color: #8b939d; font-size: 11px; }
        .wishlist-offer { margin: 6px 0 0; color: #26724d; font-size: 11px; line-height: 1.4; }
        .account-wishlist-actions { display: grid; gap: 7px; padding: 0 13px 13px; }
        .wishlist-view-product, .wishlist-remove { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; border-radius: 6px; font-size: 12px; font-weight: 800; }
        .wishlist-view-product { border: 1px solid #15191e; background: #15191e; color: #fff; }
        .wishlist-view-product:hover { background: #e33243; border-color: #e33243; color: #fff; }
        .wishlist-remove { border: 1px solid #d7dce1; background: #fff !important; color: #424a54 !important; }
        .wishlist-remove:hover { border-color: #e33243; color: #e33243 !important; }
        .wishlist-discovery { display: grid; grid-template-columns: 82px minmax(0, 1fr) auto; gap: 20px; align-items: center; margin-top: 18px; padding: 20px 24px; border: 1px solid #f3dfe2; border-radius: 7px; background: #fff5f6; }
        .wishlist-discovery-art, .wishlist-empty-icon { display: inline-flex; align-items: center; justify-content: center; color: #e95362; }
        .wishlist-discovery-art { width: 72px; height: 64px; font-size: 48px; }
        .wishlist-discovery h2, .wishlist-empty h2 { margin: 0 0 4px; font-size: 19px; letter-spacing: 0; }
        .wishlist-discovery p, .wishlist-empty p { margin: 0; color: #707985; font-size: 13px; }
        .wishlist-primary-action { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; padding: 9px 17px; border-radius: 6px; background: #e33243; color: #fff; font-size: 13px; font-weight: 800; white-space: nowrap; }
        .wishlist-primary-action:hover { background: #15191e; color: #fff; }
        .wishlist-empty { padding: 58px 24px; border: 1px dashed #d9dee4; border-radius: 7px; background: #fafbfc; text-align: center; }
        .wishlist-empty-icon { width: 64px; height: 64px; margin-bottom: 15px; border-radius: 50%; background: #fff0f2; font-size: 30px; }
        .wishlist-empty p { margin-bottom: 18px; }
        @media (max-width: 1199px) { .account-wishlist-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767px) {
            .wishlist-head { align-items: stretch; flex-direction: column; }
            .wishlist-discovery { grid-template-columns: 60px minmax(0, 1fr); padding: 18px; }
            .wishlist-discovery-art { width: 54px; }
            .wishlist-discovery .wishlist-primary-action { grid-column: 1 / -1; }
        }
        @media (max-width: 575px) {
            .account-wishlist-grid { grid-template-columns: 1fr; }
            .wishlist-title-row h1 { font-size: 25px; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const grid = document.querySelector('[data-wishlist-grid]');
            const count = document.querySelector('[data-wishlist-count]');
            const discovery = document.querySelector('[data-wishlist-discovery]');
            if (!grid) return;

            const cards = () => [...grid.querySelectorAll('[data-wishlist-card]')];
            const sync = () => {
                const total = cards().length;
                if (count) count.textContent = `(${total})`;
                if (discovery) discovery.classList.toggle('d-none', total === 0);
            };
            new MutationObserver(sync).observe(grid, { childList: true });
        })();
    </script>
@endpush
