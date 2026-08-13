@php
    $heroBanners = $heroBanners ?? collect();
    $bannerLinkResolver = app(\App\Services\Banner\BannerLinkResolver::class);
@endphp

<div class="tf-slideshow">
    <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade" data-loop="true" data-effect="fade" data-auto="true" data-delay="4000">
        <div class="swiper-wrapper">
            @forelse($heroBanners as $banner)
                @php
                    $bannerUrl = $bannerLinkResolver->resolve($banner);
                    $desktopImage = asset('storage/'.$banner->desktop_image_path);
                    $mobileImage = asset('storage/'.($banner->mobile_image_path ?: $banner->desktop_image_path));
                @endphp
                <div class="swiper-slide">
                    @if($bannerUrl)
                        <a href="{{ $bannerUrl }}" class="slider-wrap" @if($banner->open_in_new_tab) target="_blank" rel="noopener" @endif>
                    @else
                        <div class="slider-wrap">
                    @endif
                        <div class="sld_image">
                            <picture>
                                <source media="(max-width: 767px)" srcset="{{ $mobileImage }}">
                                <img loading="{{ $loop->first ? 'eager' : 'lazy' }}" width="1920" height="730" src="{{ $desktopImage }}" alt="{{ $banner->title }}">
                            </picture>
                        </div>
                        <div class="sld_content">
                            <div class="container">
                                <div class="content-sld">
                                    @if($banner->subtitle)
                                        <p class="sub-text_sld text-body-1 text-white fade-item fade-item-1 mb-15">{{ $banner->subtitle }}</p>
                                    @endif
                                    <h1 class="title_sld text-display fw-medium text-white fade-item fade-item-2">{{ $banner->title }}</h1>
                                    @if($banner->description)
                                        <p class="desc-sld text-white fade-item fade-item-3">{{ $banner->description }}</p>
                                    @endif
                                    @if($bannerUrl && $banner->button_text)
                                        <div class="fade-item fade-item-4">
                                            <span class="tf-btn btn-white">{{ $banner->button_text }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @if($bannerUrl)
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            @empty
                @foreach($heroSlides as $slide)
                <div class="swiper-slide">
                    <div class="slider-wrap">
                        <div class="sld_image">
                            <img loading="lazy" width="1920" height="730" src="{{ asset($slide['image']) }}" alt="{{ $slide['title'] }}">
                        </div>
                        <div class="sld_content">
                            <div class="container">
                                <div class="content-sld">
                                    <p class="sub-text_sld text-body-1 text-white fade-item fade-item-1 mb-15">{{ $slide['eyebrow'] }}</p>
                                    <h1 class="title_sld text-display fw-medium text-white fade-item fade-item-2">{{ $slide['title'] }}</h1>
                                    <p class="desc-sld text-white fade-item fade-item-3">{{ $slide['subtitle'] }}</p>
                                    <div class="fade-item fade-item-4">
                                        <a href="#top-picks" class="tf-btn btn-white">{{ $slide['button'] }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            @endforelse
        </div>
        <div class="sw-dot-default tf-sw-pagination"></div>
    </div>
</div>
