@php
    $accountLinks = [
        ['label' => 'Dashboard', 'route' => 'storefront.account', 'icon' => 'icon-HouseLine'],
        ['label' => 'Profile', 'route' => 'storefront.account.profile', 'icon' => 'icon-User'],
        ['label' => 'Addresses', 'route' => 'storefront.account.addresses', 'icon' => 'icon-Truck'],
        ['label' => 'My Orders', 'route' => 'storefront.account.orders', 'icon' => 'icon-Package'],
        ['label' => 'Wishlist', 'route' => 'storefront.account.wishlist', 'icon' => 'icon-HeartStraight'],
    ];
    $accountPageTitle = $accountPageTitle ?? 'My Account';
    $isAccountLinkActive = function (array $link): bool {
        return $link['route'] === 'storefront.account'
            ? request()->routeIs('storefront.account')
            : request()->routeIs($link['route']) || request()->routeIs($link['route'].'.*');
    };
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

            .account-section-head {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                align-items: flex-start;
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

            .account-address-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: space-between;
                margin-top: 16px;
                padding-top: 14px;
                border-top: 1px solid #eef0f3;
            }

            .account-address-action-group {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .account-address-actions form {
                margin: 0;
            }

            .account-link-button,
            .account-action-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 38px;
                padding: 8px 12px;
                border: 1px solid #d9dee5;
                border-radius: 6px;
                background: #fff;
                color: var(--main);
                font-size: 14px;
                font-weight: 700;
                line-height: 1.2;
            }

            .account-link-button:hover,
            .account-action-button:hover {
                border-color: #111;
            }

            .account-action-button.is-danger {
                color: #b42318;
            }

            .account-primary-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 42px;
                padding: 10px 16px;
                border: 1px solid #111;
                border-radius: 6px;
                background: #111;
                color: #fff;
                font-weight: 700;
            }

            .account-primary-button:hover {
                color: #fff;
            }

            .account-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .account-form-field label {
                display: block;
                margin-bottom: 7px;
                color: var(--main);
                font-size: 13px;
                font-weight: 700;
            }

            .account-form-field input,
            .account-form-field select {
                width: 100%;
                min-height: 44px;
                border: 1px solid #d9dee5;
                border-radius: 6px;
                padding: 9px 12px;
                background: #fff;
            }

            .account-form-field input.is-invalid,
            .account-form-field select.is-invalid {
                border-color: #b42318;
            }

            .account-form-field.is-wide {
                grid-column: 1 / -1;
            }

            .account-form-checks {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                margin-top: 16px;
            }

            .account-form-checks label {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-weight: 700;
            }

            .account-field-error {
                margin-top: 6px;
                color: #b42318;
                font-size: 13px;
            }

            .account-field-help {
                min-height: 18px;
                margin-top: 6px;
                color: #64748b;
                font-size: 13px;
            }

            .account-radio-row {
                display: flex;
                flex-wrap: wrap;
                gap: 18px;
                min-height: 44px;
                align-items: center;
            }

            .account-radio-row label {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                font-weight: 700;
            }

            .account-address-confirm-modal .modal-dialog {
                max-width: 420px;
            }

            .account-address-confirm-modal .modal-content {
                border: 0;
                border-radius: 8px;
                box-shadow: 0 18px 48px rgba(15, 23, 42, .18);
            }

            .account-address-confirm-modal .modal-header {
                align-items: center;
                min-height: 64px;
                padding: 18px 22px;
                border-bottom: 1px solid #eef0f3;
            }

            .account-address-confirm-modal .modal-title {
                margin: 0;
                font-size: 20px;
                line-height: 1.25;
                font-weight: 700;
            }

            .account-address-confirm-modal .modal-body {
                padding: 20px 22px;
                color: #4b5563;
                font-size: 15px;
                line-height: 1.55;
            }

            .account-address-confirm-modal .modal-footer {
                gap: 10px;
                padding: 16px 22px 20px;
                border-top: 1px solid #eef0f3;
            }

            .account-address-confirm-modal .modal-footer .btn {
                min-height: 38px;
                padding: 8px 14px;
                border-radius: 6px;
                font-weight: 700;
            }

            .account-address-confirm-modal .modal-footer .btn-danger {
                border-color: #dc3545;
                background: #dc3545;
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
                .account-address-list,
                .account-form-grid {
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

                .account-section-head {
                    flex-direction: column;
                }

                .account-address-actions,
                .account-address-action-group {
                    width: 100%;
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
                <p class="text-caption-01">{{ $accountPageTitle }}</p>
            </div>
            <h3>{{ $accountPageTitle }}</h3>
        </div>
    </div>
</section>

<section class="flat-spacing">
    <div class="container">
        <div class="account-mobile-nav">
            <select aria-label="Account navigation" onchange="if (this.value) window.location.href = this.value">
                @foreach ($accountLinks as $link)
                    <option value="{{ route($link['route']) }}" @selected($isAccountLinkActive($link))>
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
                            class="account-nav-link {{ $isAccountLinkActive($link) ? 'is-active' : '' }}"
                            @if ($isAccountLinkActive($link)) aria-current="page" @endif>
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
