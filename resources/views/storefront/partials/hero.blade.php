@php
    $heroBanners = $heroBanners ?? collect();
    $heroCity = $heroCity ?? 'NASHIK';
    $bannerLinkResolver = app(\App\Services\Banner\BannerLinkResolver::class);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/home-hero.css') }}?v={{ filemtime(public_path('assets/storefront/css/home-hero.css')) }}">
@endpush

@if ($heroBanners->isNotEmpty())
    <div class="tf-slideshow">
        <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade"
            data-loop="true" data-effect="fade" data-auto="true" data-delay="4000">
            <div class="swiper-wrapper">
                @foreach ($heroBanners as $banner)
                    @php
                        $bannerUrl = $bannerLinkResolver->resolve($banner);
                        $desktopImage = asset('storage/'.$banner->desktop_image_path);
                        $mobileImage = asset('storage/'.($banner->mobile_image_path ?: $banner->desktop_image_path));
                    @endphp
                    <div class="swiper-slide">
                        @if ($bannerUrl)
                            <a href="{{ $bannerUrl }}" class="slider-wrap" @if ($banner->open_in_new_tab) target="_blank" rel="noopener" @endif>
                        @else
                            <div class="slider-wrap">
                        @endif
                            <div class="sld_image">
                                <picture>
                                    <source media="(max-width: 767px)" srcset="{{ $mobileImage }}">
                                    <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" width="1920" height="730"
                                        src="{{ $desktopImage }}" alt="{{ $banner->title }}">
                                </picture>
                            </div>
                            <div class="sld_content">
                                <div class="container">
                                    <div class="content-sld">
                                        @if ($banner->subtitle)
                                            <p class="sub-text_sld text-body-1 text-white fade-item fade-item-1 mb-15">{{ $banner->subtitle }}</p>
                                        @endif
                                        <h1 class="title_sld text-display fw-medium text-white fade-item fade-item-2">{{ $banner->title }}</h1>
                                        @if ($banner->description)
                                            <p class="desc-sld text-white fade-item fade-item-3">{{ $banner->description }}</p>
                                        @endif
                                        @if ($bannerUrl && $banner->button_text)
                                            <div class="fade-item fade-item-4">
                                                <span class="tf-btn btn-white">{{ $banner->button_text }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @if ($bannerUrl)
                            </a>
                        @else
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="sw-dot-default tf-sw-pagination"></div>
        </div>
    </div>
@else
    <section class="home-local-hero" aria-labelledby="home-local-hero-title">
        <div class="container home-local-hero__container">
            <div class="home-local-hero__content">
                <h1 id="home-local-hero-title" class="home-local-hero__title">
                    <span>Shop Local</span>
                    <span>Shop Genuine</span>
                </h1>
                <p class="home-local-hero__description">
                    Discover amazing products from trusted local shops.
                </p>
                <div>
                    <a href="{{ route('storefront.stores') }}" class="tf-btn animate-btn home-local-hero__cta">
                        Explore Stores
                        <i class="icon icon-ArrowRight" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="home-local-hero__benefits" aria-label="Shopping benefits">
                    <div class="home-local-hero__benefit">
                        <i class="icon icon-storefront" aria-hidden="true"></i>
                        <span><strong>Shop Local</strong>From Home or Office</span>
                    </div>
                    <div class="home-local-hero__benefit">
                        <i class="icon icon-ShieldCheck" aria-hidden="true"></i>
                        <span><strong>Shop Genuine</strong>Buy from trusted local sellers</span>
                    </div>
                    <div class="home-local-hero__benefit">
                        <i class="icon icon-ArrowsLeftRight" aria-hidden="true"></i>
                        <span><strong>Easy Exchange</strong>Hassle-free at the shop</span>
                    </div>
                    <div class="home-local-hero__benefit">
                        <i class="icon icon-CreditCard" aria-hidden="true"></i>
                        <span><strong>Cash at Shop</strong>Order online, pay when you visit</span>
                    </div>
                </div>
            </div>

            <div class="home-local-hero__visual" aria-label="{{ $heroCity }} local marketplace">
                <img class="home-local-hero__market" width="1536" height="1024"
                    src="{{ asset('assets/storefront/images/hero/hero-market.png') }}"
                    alt="A circular local market with neighbourhood shops">
                <img class="home-local-hero__shopper" width="1299" height="1186"
                    src="{{ asset('assets/storefront/images/hero/hero-lady.png') }}"
                    alt="Shopper carrying bags and browsing WindowShop on her phone">
                <div class="home-local-hero__location">
                    <img width="1024" height="1223"
                        src="{{ asset('assets/storefront/images/hero/hero-location-mark.png') }}"
                        alt="">
                    <p><strong>{{ $heroCity }}</strong><span>Your Local Market</span></p>
                </div>
            </div>
        </div>
    </section>
@endif

@push('scripts')
    <script>
        (() => {
            const hero = document.querySelector('.home-local-hero');

            if (!hero) return;

            const preloader = document.getElementById('preload');
            const reveal = () => requestAnimationFrame(() => hero.classList.add('is-revealed'));

            if (!preloader) {
                reveal();
                return;
            }

            const observer = new MutationObserver(() => {
                if (!document.body.contains(preloader)) {
                    observer.disconnect();
                    reveal();
                }
            });

            observer.observe(document.body, { childList: true });
        })();
    </script>
@endpush
