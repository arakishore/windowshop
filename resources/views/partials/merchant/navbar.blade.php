{{-- Purpose: Merchant-specific Limitless top navbar with shop context and account actions. --}}
@php
    $merchantUser = auth()->user();
    $shopContext = $merchantActiveShopContext ?? [
        'shops' => collect(),
        'activeShop' => null,
        'activeShopLabel' => session('active_shop_name', 'No active shop'),
    ];
    $activeShops = $shopContext['shops'];
    $selectedShop = $shopContext['activeShop'];
    $shopLabel = $shopContext['activeShopLabel'];
@endphp

<!-- Main navbar -->
<div class="navbar navbar-dark navbar-expand-lg navbar-static border-bottom border-bottom-white border-opacity-10">
    <div class="container-fluid">
        <div class="d-flex d-lg-none me-2">
            <button type="button" class="navbar-toggler sidebar-mobile-main-toggle rounded-pill">
                <i class="ph-list"></i>
            </button>
        </div>

        <div class="navbar-brand flex-1 flex-lg-0">
            <a href="{{ route('merchant.dashboard') }}" class="d-inline-flex align-items-center text-white text-decoration-none">
                <img src="{{ asset('assets/admin/images/logov2.png') }}" alt="WindowShop">
                <span class="fw-semibold ms-2">Merchant Panel</span>
            </a>
        </div>

        <div class="navbar-collapse justify-content-center flex-lg-1 order-2 order-lg-1 collapse" id="navbar_search">
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0 mx-lg-3">
                <span class="text-white text-opacity-75 d-inline-flex align-items-center">
                    <span class="me-1">&#127970;</span>
                    Active Shop
                </span>

                @if ($activeShops->count() > 1)
                    <form method="POST" action="{{ route('merchant.active-shop.update') }}" class="mb-0">
                        @csrf
                        <select name="shop_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            @foreach ($activeShops as $shop)
                                <option value="{{ $shop->getKey() }}" @selected($selectedShop?->is($shop))>
                                    {{ $shop->name }}{{ $shop->city?->name ? ' - '.$shop->city->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25">
                        {{ $shopLabel }}
                    </span>
                @endif
            </div>
        </div>

        <ul class="nav flex-row justify-content-end order-1 order-lg-2">
            <li class="nav-item ms-lg-2">
                <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill">
                    <i class="ph-bell"></i>
                    <span class="badge bg-yellow text-black position-absolute top-0 end-0 translate-middle-top zindex-1 rounded-pill mt-1 me-1">3</span>
                </a>
            </li>

            <li class="nav-item nav-item-dropdown-lg dropdown ms-lg-2">
                <a href="#" class="navbar-nav-link align-items-center rounded-pill p-1" data-bs-toggle="dropdown">
                    <div class="status-indicator-container">
                        <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-pill w-32px h-32px">
                            {{ Str::upper(Str::substr($merchantUser?->name ?? 'M', 0, 1)) }}
                        </span>
                        <span class="status-indicator bg-success"></span>
                    </div>
                    <span class="d-none d-lg-inline-block mx-lg-2">{{ $merchantUser?->name ?? 'Merchant' }}</span>
                </a>

                <div class="dropdown-menu dropdown-menu-end">
                    <a href="{{ route('merchant.profile.edit') }}" class="dropdown-item">
                        <i class="ph-user-circle me-2"></i>
                        My Profile
                    </a>
                    <a href="{{ route('merchant.details.edit') }}" class="dropdown-item">
                        <i class="ph-buildings me-2"></i>
                        Merchant Details
                    </a>
                    <a href="{{ route('merchant.password.edit') }}" class="dropdown-item">
                        <i class="ph-key me-2"></i>
                        Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('merchant.logout') }}" class="dropdown-item">
                        <i class="ph-sign-out me-2"></i>
                        Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- /main navbar -->
