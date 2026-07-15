{{-- Purpose: Merchant panel sidebar navigation using Limitless sidebar markup. --}}
@php
    $disabled = 'disabled opacity-50';
@endphp

<!-- Main sidebar -->
<div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">

    <!-- Sidebar content -->
    <div class="sidebar-content">

        <!-- Sidebar header -->
        <div class="sidebar-section">
            <div class="sidebar-section-body d-flex justify-content-center">
                <h5 class="sidebar-resize-hide flex-grow-1 my-auto">Merchant Menu</h5>

                <div>
                    <button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-control sidebar-main-resize d-none d-lg-inline-flex">
                        <i class="ph-arrows-left-right"></i>
                    </button>

                    <button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-mobile-main-toggle d-lg-none">
                        <i class="ph-x"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- /sidebar header -->

        <!-- Main navigation -->
        <div class="sidebar-section">
            <ul class="nav nav-sidebar py-2" data-nav-type="accordion">

                <li class="nav-item-header pt-0">
                    <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Main</div>
                    <i class="ph-dots-three sidebar-resize-show"></i>
                </li>

                <li class="nav-item">
                    <a href="{{ route('merchant.dashboard') }}" class="nav-link {{ request()->routeIs('merchant.dashboard') ? 'active' : '' }}">
                        <i class="ph-house"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.shops.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.shops.*') ? 'active' : '' }}">
                        <i class="ph-storefront"></i>
                        <span>Shop Management</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.shops.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.shops.index') }}" class="nav-link {{ request()->routeIs('merchant.shops.*') ? 'active' : '' }}">My Shops</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.products.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.products.*') ? 'active' : '' }}">
                        <i class="ph-package"></i>
                        <span>Catalog</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.products.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.products.index') }}" class="nav-link {{ request()->routeIs('merchant.products.*') ? 'active' : '' }}">Products</a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link {{ $disabled }}">Categories</a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link {{ $disabled }}">Brands</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-stack"></i>
                        <span>Inventory</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-tag"></i>
                        <span>Offers</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-receipt"></i>
                        <span>Orders</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-users-three"></i>
                        <span>Customers</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-chart-line-up"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.password.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.password.*') ? 'active' : '' }}">
                        <i class="ph-user-gear"></i>
                        <span>Account</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.password.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.profile.edit') }}" class="nav-link {{ request()->routeIs('merchant.profile.*') ? 'active' : '' }}">My Profile</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.details.edit') }}" class="nav-link {{ request()->routeIs('merchant.details.*') ? 'active' : '' }}">Merchant Details</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.password.edit') }}" class="nav-link {{ request()->routeIs('merchant.password.*') ? 'active' : '' }}">Change Password</a>
                        </li>
                    </ul>
                </li>

            </ul>
        </div>
        <!-- /main navigation -->

    </div>
    <!-- /sidebar content -->

</div>
<!-- /main sidebar -->
