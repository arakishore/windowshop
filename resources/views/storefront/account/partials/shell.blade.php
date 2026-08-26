@php
    $accountLinks = [
        ['label' => 'Dashboard', 'route' => 'storefront.account', 'icon' => 'icon-HouseLine'],
        ['label' => 'Profile', 'route' => 'storefront.account.profile', 'icon' => 'icon-User'],
        ['label' => 'Addresses', 'route' => 'storefront.account.addresses', 'icon' => 'icon-Truck'],
        ['label' => 'My Orders', 'route' => 'storefront.account.orders', 'icon' => 'icon-Package'],
        ['label' => 'Wishlist', 'route' => 'storefront.account.wishlist', 'icon' => 'icon-HeartStraight'],
    ];
@endphp

@once
    @push('styles')
        <style>
            .account-shell {
                display: grid;
                grid-template-columns: 260px minmax(0, 1fr);
                gap: 28px;
                align-items: start;
            }

            .account-panel {
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                background: #fff;
            }

            .account-sidebar {
                position: sticky;
                top: 24px;
                overflow: hidden;
            }

            .account-sidebar-title,
            .account-content-inner {
                padding: 22px;
            }

            .account-sidebar-title {
                border-bottom: 1px solid #eef0f3;
            }

            .account-nav {
                display: grid;
                gap: 2px;
                padding: 10px;
            }

            .account-nav-link,
            .account-logout-button {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                min-height: 44px;
                padding: 10px 12px;
                border: 0;
                border-radius: 6px;
                background: transparent;
                color: var(--main);
                text-align: left;
                font-weight: 600;
            }

            .account-nav-link:hover,
            .account-nav-link.is-active,
            .account-logout-button:hover {
                background: rgba(18, 18, 18, .06);
            }

            .account-logout {
                margin: 8px 10px 10px;
                padding-top: 10px;
                border-top: 1px solid #eef0f3;
            }

            .account-mobile-nav {
                display: none;
                margin-bottom: 18px;
            }

            .account-mobile-nav select {
                width: 100%;
                height: 46px;
                border: 1px solid #d9dee5;
                border-radius: 6px;
                padding: 0 12px;
                background: #fff;
            }

            .account-card-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px;
            }

            .account-action-card,
            .account-stat-card,
            .account-address-card {
                display: block;
                padding: 18px;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                background: #fff;
                color: inherit;
            }

            .account-action-card:hover {
                border-color: #111;
            }

            .account-card-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                margin-bottom: 12px;
                border-radius: 50%;
                background: rgba(225, 67, 67, .09);
                color: var(--primary);
            }

            .account-profile-list {
                display: grid;
                gap: 14px;
                max-width: 640px;
            }

            .account-profile-row {
                display: grid;
                grid-template-columns: 150px minmax(0, 1fr);
                gap: 16px;
                padding-bottom: 14px;
                border-bottom: 1px solid #eef0f3;
            }

            .account-address-list {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .account-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 12px;
            }

            .account-badge {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                padding: 3px 9px;
                border-radius: 999px;
                background: #f2f4f7;
                color: #4b5563;
                font-size: 12px;
                font-weight: 700;
            }

            .account-empty-panel {
                padding: 18px;
                border: 1px dashed #d8dee6;
                border-radius: 6px;
                color: #64748b;
                background: #f8fafc;
            }

            @media (max-width: 991px) {
                .account-shell {
                    grid-template-columns: 1fr;
                }

                .account-sidebar {
                    display: none;
                }

                .account-mobile-nav {
                    display: block;
                }

                .account-card-grid,
                .account-address-list {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 575px) {
                .account-content-inner {
                    padding: 18px;
                }

                .account-profile-row {
                    grid-template-columns: 1fr;
                    gap: 4px;
                }
            }
        </style>
    @endpush
@endonce

<section class="section-page-title text-center storefront-page-title">
    <div class="container">
        <div class="main-page-title">
            <div class="breadcrumbs">
                <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                <i class="icon icon-CaretRightThin cl-text-3"></i>
                <p class="text-caption-01">My Account</p>
            </div>
            <h3>My Account</h3>
        </div>
    </div>
</section>

<section class="flat-spacing">
    <div class="container">
        <div class="account-mobile-nav">
            <select aria-label="Account navigation" onchange="if (this.value) window.location.href = this.value">
                @foreach ($accountLinks as $link)
                    <option value="{{ route($link['route']) }}" @selected(request()->routeIs($link['route']))>
                        {{ $link['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="account-shell">
            <aside class="account-panel account-sidebar">
                <div class="account-sidebar-title">
                    <p class="text-caption-01 cl-text-3 mb-4">Signed in as</p>
                    <h6 class="mb-0">{{ $customer->name }}</h6>
                </div>
                <nav class="account-nav" aria-label="Account navigation">
                    @foreach ($accountLinks as $link)
                        <a href="{{ route($link['route']) }}"
                            class="account-nav-link {{ request()->routeIs($link['route']) ? 'is-active' : '' }}"
                            @if (request()->routeIs($link['route'])) aria-current="page" @endif>
                            <i class="icon {{ $link['icon'] }}"></i>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
                <div class="account-logout">
                    <form method="POST" action="{{ route('storefront.logout') }}">
                        @csrf
                        <button type="submit" class="account-logout-button">
                            <i class="icon icon-SignOut"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </aside>

            <div class="account-panel">
                <div class="account-content-inner">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</section>
