<div class="home-categories__item wow fadeInUp" role="listitem">
    <a href="{{ $category['url'] ?? '#;' }}" class="category-v01 home-category-card hover-img">
        <div class="cate-image home-category-card__image img-style">
            <img class="aspect-ratio-1" loading="lazy" width="250" height="250"
                src="{{ asset($category['image']) }}" alt="{{ $category['name'] }}">
        </div>
        <p class="cate-name h5 text-center link link-underline home-category-card__name">{{ $category['name'] }}</p>
    </a>
</div>
