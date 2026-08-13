@php
    $storefrontNavigationCategories = $storefrontNavigationCategories ?? collect();
    $storefrontShop = $storefrontShop ?? null;

    $categoryUrl = function ($category, $parentCategory = null) use ($storefrontShop): string {
        if ($storefrontShop) {
            return route('storefront.store.category.show', [$storefrontShop->slug, $category->slug]);
        }

        if ($parentCategory) {
            return route('storefront.category.child.show', [$parentCategory->slug, $category->slug]);
        }

        return route('storefront.category.show', $category->slug);
    };
@endphp

@once
    <style>
        .storefront-category-mega.is-drilling .storefront-mega-root {
            display: none;
        }

        .storefront-mega-drill {
            display: none;
        }

        .storefront-mega-drill.is-active {
            display: block;
            width: 340px;
            max-width: 100%;
        }

        .mega-menu-drill-trigger,
        .mega-menu-back {
            border: 0;
            background: transparent;
            padding: 0;
            font: inherit;
            color: var(--text-2);
            cursor: pointer;
            text-align: left;
        }

        .mega-menu-drill-trigger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            white-space: nowrap;
        }

        .mega-menu-drill-trigger:hover,
        .mega-menu-back:hover {
            color: var(--text);
        }

        .mega-menu-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            color: var(--primary);
            font-weight: 600;
        }

        .mega-menu-drill-heading {
            margin-bottom: 14px;
            font-weight: 700;
            line-height: 1.3;
        }

        .mega-menu-subcategory-grid {
            display: grid;
            grid-template-columns: repeat(2, 140px);
            column-gap: 30px;
            width: max-content;
        }

        .mega-menu-subcategory-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mega-menu-subcategory-column .sub-menu_link {
            display: inline-flex;
            width: 140px;
            padding: 0;
            line-height: 1.25;
        }

    </style>
@endonce

<nav class="box-navigation">
    <ul class="box-nav-menu">
        <li class="menu-item position-relative">
            <a href="{{ route('storefront.home') }}" class="item-link">
                <span class="text cus-text">Home</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="#;" class="item-link">
                <span class="text cus-text">Categories</span>
                <i class="icon icon-CaretDown"></i>
            </a>
            <div class="sub-menu mega-menu storefront-category-mega">
                <div class="container-full">
                    <div class="row storefront-mega-root">
                        {{-- TODO: Root category icon support. --}}
                        @forelse($storefrontNavigationCategories as $rootCategory)
                            <div class="col-2">
                                <div class="mega-menu-item menu-lv-2">
                                    <p class="menu-heading">{{ $rootCategory->name }}</p>
                                    <ul class="sub-menu_list">
                                        @foreach($rootCategory->children as $childCategory)
                                            <li>
                                                @if($childCategory->children->isNotEmpty())
                                                    <a href="{{ $categoryUrl($childCategory, $rootCategory) }}" class="sub-menu_link has-text mega-menu-drill-trigger"
                                                        data-drill-target="mega-category-{{ $childCategory->getKey() }}">
                                                        <span class="cus-text">{{ $childCategory->name }}</span>
                                                        <i class="icon icon-CaretRightThin"></i>
                                                    </a>
                                                    <div class="mega-menu-mobile-children d-none">
                                                        @foreach($childCategory->children as $grandchildCategory)
                                                            <a href="{{ $categoryUrl($grandchildCategory, $childCategory) }}" class="sub-menu_link has-text">
                                                                <span class="cus-text">{{ $grandchildCategory->name }}</span>
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <a href="{{ $categoryUrl($childCategory, $rootCategory) }}" class="sub-menu_link has-text">
                                                        <span class="cus-text">{{ $childCategory->name }}</span>
                                                    </a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="mega-menu-item menu-lv-2">
                                    <p class="menu-heading">Categories</p>
                                    <ul class="sub-menu_list">
                                        <li><span class="sub-menu_link has-text"><span class="cus-text">No categories available</span></span></li>
                                    </ul>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    @foreach($storefrontNavigationCategories as $rootCategory)
                        @foreach($rootCategory->children as $childCategory)
                            @if($childCategory->children->isNotEmpty())
                                <div class="storefront-mega-drill" id="mega-category-{{ $childCategory->getKey() }}">
                                    <button type="button" class="mega-menu-back">
                                        <i class="icon icon-CaretLeftThin"></i>
                                        <span>Back</span>
                                    </button>

                                    <p class="mega-menu-drill-heading">{{ $childCategory->name }}</p>
                                    @php
                                        $subcategoryColumnSize = max(1, (int) ceil($childCategory->children->count() / 2));
                                    @endphp
                                    <div class="mega-menu-subcategory-grid">
                                        @foreach($childCategory->children->chunk($subcategoryColumnSize) as $grandchildColumn)
                                            <div class="mega-menu-subcategory-column">
                                                @foreach($grandchildColumn as $grandchildCategory)
                                                    <a href="{{ $categoryUrl($grandchildCategory, $childCategory) }}" class="sub-menu_link has-text">
                                                        <span class="cus-text">{{ $grandchildCategory->name }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>
        </li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.stores') }}" class="item-link"><span class="text cus-text">Shops</span></a></li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.products') }}" class="item-link"><span class="text cus-text">Products</span></a></li>
        <li class="menu-item position-relative"><a href="#;" class="item-link"><span class="text cus-text">Brands</span></a></li>
        <li class="menu-item position-relative"><a href="#;" class="item-link"><span class="text cus-text">Offers</span></a></li>
        <li class="menu-item position-relative"><a href="#new-arrivals" class="item-link"><span class="text cus-text">New Arrivals</span></a></li>
        <li class="menu-item position-relative"><a href="{{ route('storefront.contact') }}" class="item-link"><span class="text cus-text">Contact</span></a></li>
         
    </ul>
</nav>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.storefront-category-mega').forEach((megaMenu) => {
                megaMenu.querySelectorAll('.mega-menu-drill-trigger').forEach((trigger) => {
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        const targetId = trigger.getAttribute('data-drill-target');
                        const target = targetId ? megaMenu.querySelector(`#${targetId}`) : null;

                        if (!target) {
                            return;
                        }

                        megaMenu.classList.add('is-drilling');
                        megaMenu.querySelectorAll('.storefront-mega-drill').forEach((panel) => {
                            panel.classList.toggle('is-active', panel === target);
                        });
                    });
                });

                megaMenu.querySelectorAll('.mega-menu-back').forEach((backButton) => {
                    backButton.addEventListener('click', () => {
                        megaMenu.classList.remove('is-drilling');
                        megaMenu.querySelectorAll('.storefront-mega-drill').forEach((panel) => {
                            panel.classList.remove('is-active');
                        });
                    });
                });
            });
        });
    </script>
@endpush
