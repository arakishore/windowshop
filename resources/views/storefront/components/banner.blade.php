<div class="{{ $banner['class'] ?? 'collection-position-2 hover-img' }}">
    <a href="{{ $banner['url'] ?? '#;' }}" class="box-image_img img-style">
        <img loading="lazy" width="{{ $banner['width'] ?? 700 }}" height="{{ $banner['height'] ?? 461 }}" src="{{ asset($banner['image']) }}" alt="{{ $banner['title'] }}">
    </a>
    <div class="cls-content text-center">
        <a href="{{ $banner['url'] ?? '#;' }}" class="tf-btn btn-white">{{ $banner['title'] }}</a>
    </div>
</div>
