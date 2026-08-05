@props([
    'banners',
    'position',
    'hero' => false,
])

@php
    $resolver = app(\App\Services\Banner\BannerLinkResolver::class);
@endphp

@if($banners->isNotEmpty())
    <div class="windowshop-banner-slider windowshop-banner-slider--{{ $position }}">
        @foreach($banners as $banner)
            @php($url = $resolver->resolve($banner))
            <article class="windowshop-banner-slide">
                @if($url)<a href="{{ $url }}" class="windowshop-banner-link" @if($banner->open_in_new_tab) target="_blank" rel="noopener" @endif>@endif
                    <picture>
                        @if($banner->mobile_image_path)
                            <source media="(max-width: 767px)" srcset="{{ asset('storage/'.$banner->mobile_image_path) }}">
                        @endif
                        <img src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}" @if(! $hero || ! $loop->first) loading="lazy" @endif>
                    </picture>
                    @if($banner->title || $banner->subtitle || $banner->button_text)
                        <div class="windowshop-banner-copy">
                            @if($banner->title)<h2>{{ $banner->title }}</h2>@endif
                            @if($banner->subtitle)<p>{{ $banner->subtitle }}</p>@endif
                            @if($banner->button_text)<span>{{ $banner->button_text }}</span>@endif
                        </div>
                    @endif
                @if($url)</a>@endif
            </article>
        @endforeach
    </div>
@endif
