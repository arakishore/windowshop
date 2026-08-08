{{-- TODO: Replace static slides with WindowShop active homepage_hero banners. --}}
<div class="tf-slideshow">
    <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade" data-loop="true" data-effect="fade" data-auto="true" data-delay="4000">
        <div class="swiper-wrapper">
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
        </div>
        <div class="sw-dot-default tf-sw-pagination"></div>
    </div>
</div>
