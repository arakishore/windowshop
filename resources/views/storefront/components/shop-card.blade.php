<div class="shop-card">
    <a href="{{ $store['store_url'] }}" class="shop-image-wrapper d-block">
        <img class="shop-image" loading="lazy" width="450" height="338"
            src="{{ asset($store['image']) }}" alt="{{ $store['name'] }}"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <div class="no-image" style="display:none;">No Image</div>

        @if (!empty($store['shop_type']))
            <span class="shop-type-badge">{{ $store['shop_type'] }}</span>
        @endif

        <div class="shop-logo {{ empty($store['logo']) ? 'shop-logo-initial' : '' }}">
            @if (!empty($store['logo']))
                <img loading="lazy" width="56" height="56" src="{{ asset($store['logo']) }}"
                    alt="{{ $store['name'] }} logo">
            @else
                <span class="shop-initial">{{ $store['initials'] }}</span>
            @endif
        </div>
    </a>

    <div class="shop-content">
        <h3 class="shop-name"><a href="{{ $store['store_url'] }}">{{ $store['name'] }}</a></h3>

        <div class="shop-meta">
            <div class="meta-row">
                <i class="icon icon-MapPin"></i>
                <span>
                    <span class="meta-value">
                        @if (!empty($store['maps_url']))
                            <a href="{{ $store['maps_url'] }}" target="_blank"
                                rel="noopener noreferrer" class="link"
                                title="Open address in Google Maps">
                                {{ $store['location_label'] ?: ($store['address'] ?: 'Address unavailable') }}
                                <i class="icon icon-ArrowUpRight1"></i>
                            </a>
                        @else
                            <span>{{ $store['location_label'] ?: ($store['address'] ?: 'Address unavailable') }}</span>
                        @endif
                    </span>
                </span>
            </div>
        </div>

        <a href="{{ $store['store_url'] }}" class="tf-btn animate-btn small">
            Shop the Collection <i class="icon icon-ArrowUpRight1"></i>
        </a>
    </div>
</div>
