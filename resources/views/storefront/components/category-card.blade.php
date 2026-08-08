<div class="swiper-slide wow fadeInUp">
    <a href="{{ $category['url'] ?? '#;' }}" class="category-v01 hover-img">
        <div class="cate-image img-style">
            <img loading="lazy" width="210" height="210" src="{{ asset($category['image']) }}" alt="{{ $category['name'] }}">
        </div>
        <p class="cate-name h5 text-center link link-underline">{{ $category['name'] }}</p>
    </a>
</div>
