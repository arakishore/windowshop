@php
    $shopName = $shopProfile['name'];
    $heroSlides = collect($heroBanners)
        ->map(fn ($banner) => [
            'title' => $banner->title ?: 'Featured at this shop',
            'subtitle' => $banner->subtitle ?: $banner->description,
            'image' => $banner->desktop_image_path ? asset('storage/' . $banner->desktop_image_path) : null,
            'url' => null,
        ])
        ->filter(fn ($slide) => !empty($slide['image']))
        ->values();

    if ($heroSlides->isEmpty() && (isset($heroProducts) || isset($products))) {
        $heroSlides = collect(($heroProducts ?? $products)->items())
            ->take(5)
            ->map(fn ($product) => [
                'title' => $product['name'],
                'subtitle' => $product['price'],
                'image' => $product['image'],
                'url' => $product['url'],
            ])
            ->values();
    }

    $coverImage = $shopProfile['cover']
        ? asset($shopProfile['cover'])
        : ($heroSlides->first()['image'] ?? asset('assets/storefront/images/category/cate-1.jpg'));
@endphp

<section class="shop-profile-hero" style="--shop-cover: url('{{ $coverImage }}');">
    <div class="shop-profile-hero-inner">
        <div class="shop-profile-identity">
            <div class="shop-profile-logo">
                @if ($shopProfile['logo'])
                    <img src="{{ asset($shopProfile['logo']) }}" width="84" height="84" alt="{{ $shopName }} logo">
                @else
                    <span>{{ $shopProfile['initials'] }}</span>
                @endif
            </div>
            @if (isset($page))
                <h2 class="shop-profile-title">{{ $shopName }}</h2>
            @else
                <h1 class="shop-profile-title">{{ $shopName }}</h1>
            @endif
            @if ($shopProfile['description'])
                <p class="shop-profile-description">{{ $shopProfile['description'] }}</p>
            @endif
            <div class="shop-profile-facts">
                <span class="shop-profile-fact"><i class="icon icon-Tag"></i>{{ $shopProfile['shop_type'] ?: 'General Store' }}</span>
                @if (!empty($shopProfile['audiences']))
                    <span class="shop-profile-fact"><i class="icon icon-Users"></i>{{ implode(', ', $shopProfile['audiences']) }}</span>
                @endif
                <span class="shop-profile-fact"><i class="icon icon-Package"></i>{{ $shopProfile['product_count'] }} products</span>
                <span class="shop-profile-fact"><i class="icon icon-ShieldCheck"></i>Active Shop</span>
            </div>
        </div>

        <div class="shop-profile-slider">
            <div dir="ltr" class="swiper tf-swiper" data-preview="1" data-tablet="1" data-mobile="1"
                data-space="0" data-loop="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}"
                data-auto="{{ $heroSlides->count() > 1 ? 'true' : 'false' }}" data-delay="4500">
                <div class="swiper-wrapper">
                    @forelse ($heroSlides as $slide)
                        <div class="swiper-slide">
                            @if (!empty($slide['url']))
                                <a href="{{ $slide['url'] }}" class="shop-profile-slide-card">
                            @else
                                <div class="shop-profile-slide-card">
                            @endif
                                <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}">
                                <div class="shop-profile-slide-caption">
                                    {{ $slide['title'] }}
                                    @if (!empty($slide['subtitle']))
                                        <div class="text-caption-01 fw-normal mt-1">{{ $slide['subtitle'] }}</div>
                                    @endif
                                </div>
                            @if (!empty($slide['url']))
                                </a>
                            @else
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="swiper-slide">
                            <div class="shop-profile-slide-card">
                                <img loading="eager" src="{{ $coverImage }}" alt="{{ $shopName }}">
                                <div class="shop-profile-slide-caption">{{ $shopName }}</div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($heroSlides->count() > 1)
                <div class="sw-dot-default tf-sw-pagination"></div>
            @endif
        </div>
    </div>
</section>
