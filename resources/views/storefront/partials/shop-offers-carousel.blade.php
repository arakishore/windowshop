@php
    $offers = $offers ?? collect();
    $sectionId = $sectionId ?? 'offers';
    $titleId = $sectionId.'-title';
    $title = $title ?? 'Offers';
    $subtitle = $subtitle ?? 'Current offers and promotions available from this shop.';
    $primaryActionLabel = $primaryActionLabel ?? 'View All Offers';
    $primaryActionUrl = $primaryActionUrl ?? null;
    $secondaryActionLabel = $secondaryActionLabel ?? null;
    $secondaryActionUrl = $secondaryActionUrl ?? null;
    $featuredOffer = $featuredOffer ?? null;
    $actionLinkClass = $actionLinkClass ?? 'shop-profile-section-link';
@endphp

<section class="shop-profile-section shop-profile-offers tf-btn-swiper-main {{ $sectionClass ?? '' }}" id="{{ $sectionId }}" aria-labelledby="{{ $titleId }}">
    <div class="shop-profile-section-head">
        <div class="shop-section-heading">
            <div class="shop-section-heading-icon"><i class="icon icon-Tag" aria-hidden="true"></i></div>
            <div>
                <h2 class="shop-profile-section-title" id="{{ $titleId }}">{{ $title }}</h2>
                <div class="text-caption-01 cl-text-2 mt-1">{{ $subtitle }}</div>
            </div>
        </div>
        <div class="shop-offers-heading-actions">
            @if($secondaryActionUrl && $secondaryActionLabel)
                <a class="{{ $actionLinkClass }}" href="{{ $secondaryActionUrl }}">{{ $secondaryActionLabel }} <i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
            @endif
            @if($primaryActionUrl)
                <a class="{{ $actionLinkClass }}" href="{{ $primaryActionUrl }}">{{ $primaryActionLabel }} <i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
            @endif
        </div>
    </div>

    @if(!empty($featuredOffer['promotional_image_url']) && !empty($featuredOffer['products_url']))
        <a href="{{ $featuredOffer['products_url'] }}" class="shop-featured-offer-artwork" aria-label="View {{ $featuredOffer['name'] }} offer products">
            <img src="{{ $featuredOffer['promotional_image_url'] }}" alt="{{ $featuredOffer['name'] }} promotional artwork">
        </a>
    @endif

    <div class="swiper tf-swiper shop-profile-offer-carousel" data-preview="6" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space="16" data-speed="600" aria-label="Shop offers">
        <div class="swiper-wrapper">
            @foreach ($offers as $offer)
                <div class="swiper-slide">
                    <article class="shop-profile-offer-card shop-profile-offer-card--{{ ['mint', 'rose', 'blue', 'amber'][$loop->index % 4] }}">
                        <div class="shop-profile-offer-top">
                            <div>
                                <div class="shop-profile-offer-label">{{ $offer['label'] }}</div>
                                <h3 class="shop-profile-offer-title">{{ $offer['name'] }}</h3>
                            </div>
                            <span class="shop-profile-offer-icon" aria-hidden="true"><i class="icon {{ $offer['icon'] ?: 'icon-Tag' }}"></i></span>
                        </div>
                        @if (!empty($offer['description']))
                            <p class="shop-profile-offer-desc">{{ $offer['description'] }}</p>
                        @endif
                        <div class="shop-profile-offer-bottom">
                            <span class="shop-profile-offer-code">{{ !empty($offer['code']) ? 'Code: '.$offer['code'] : 'Auto applied' }}</span>
                            <a href="{{ $offer['products_url'] }}" class="shop-profile-offer-link">View Products <i class="icon icon-ArrowRight" aria-hidden="true"></i></a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </div>

    @if ($offers->count() > 1)
        <div class="shop-profile-offer-controls">
            <button type="button" class="nav-prev-swiper" aria-label="Previous offers" title="Previous offers"><i class="icon icon-CaretLeft" aria-hidden="true"></i></button>
            <button type="button" class="nav-next-swiper" aria-label="Next offers" title="Next offers"><i class="icon icon-CaretRightThin" aria-hidden="true"></i></button>
        </div>
    @endif
</section>
