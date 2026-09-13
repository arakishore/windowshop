@php
    $sortOptions = [
        'popularity' => 'Popularity',
        'new-arrivals' => 'New Arrivals',
        'top-sellers' => 'Top Sellers',
        'price-high-low' => 'Price High to Low',
        'price-low-high' => 'Price Low to High',
        'discount-high-low' => 'Discount High to Low',
        'rating-high-low' => 'Rating High To Low',
    ];
    $selectedSort = (string) ($selectedFilters['sort'] ?? 'popularity');
    $selectedSort = array_key_exists($selectedSort, $sortOptions) ? $selectedSort : 'popularity';
    $sortBaseQuery = request()->except(['sort', 'page']);
    $filterDrawerId = $filterDrawerId ?? 'filterShop';
@endphp

<div class="tf-shop-control sticky-top no-offset sticky-top no-offset">
    <a href="#{{ $filterDrawerId }}" data-bs-toggle="offcanvas" class="tf-btn-filter">
        <span class="icon icon-filter"></span>
        <span class="text">Filters</span>
    </a>

    <div class="tf-control-sorting">
        <span class="text-caption-01 cl-text-2 me-2">Sort By</span>
        <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
            <div class="btn-select">
                <span class="text-sort-value">{{ $sortOptions[$selectedSort] }}</span>
                <span class="icon icon-CaretDown"></span>
            </div>
            <div class="dropdown-menu">
                @foreach ($sortOptions as $sortValue => $sortLabel)
                    <a
                        class="select-item {{ $selectedSort === $sortValue ? 'active' : '' }}"
                        data-sort-value="{{ $sortValue }}"
                        data-server-sort-link
                        href="{{ url()->current().'?'.http_build_query(array_merge($sortBaseQuery, ['sort' => $sortValue])) }}"
                    >
                        <span class="text-value-item">{{ $sortLabel }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
