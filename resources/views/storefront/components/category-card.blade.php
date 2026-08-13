<div class="swiper-slide wow fadeInUp">
    <a href="{{ $category['url'] ?? '#;' }}" class="category-v01 category-bag-card hover-img">
        <div class="category-bag-frame" aria-hidden="true">
            <span class="category-bag-handle"></span>
            <span class="category-bag-ring left"></span>
            <span class="category-bag-ring right"></span>
            <span class="category-bag-shine"></span>
            <div class="cate-image category-bag-photo img-style">
                <img loading="lazy" width="210" height="210" src="{{ asset($category['image']) }}" alt="">
            </div>
        </div>
        <p class="cate-name h5 text-center link link-underline">{{ $category['name'] }}</p>
    </a>
</div>
