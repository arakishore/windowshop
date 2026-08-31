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

                <li class="nav-item">
                    <a href="{{ route('merchant.pos.index') }}" class="nav-link {{ request()->routeIs('merchant.pos.*') ? 'active' : '' }}">
                        <i class="ph-desktop"></i>
                        <span>POS</span>
                    </a>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.orders.*') || request()->routeIs('merchant.sales.*') || request()->routeIs('merchant.return-reasons.*') || request()->routeIs('merchant.cancellation-reasons.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.orders.*') || request()->routeIs('merchant.sales.*') || request()->routeIs('merchant.return-reasons.*') || request()->routeIs('merchant.cancellation-reasons.*') ? 'active' : '' }}">
                        <i class="ph-receipt"></i>
                        <span>Sales</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.orders.*') || request()->routeIs('merchant.sales.*') || request()->routeIs('merchant.return-reasons.*') || request()->routeIs('merchant.cancellation-reasons.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.orders.index') }}" class="nav-link {{ request()->routeIs('merchant.orders.*') ? 'active' : '' }}">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.sales.index') }}" class="nav-link {{ request()->routeIs('merchant.sales.*') ? 'active' : '' }}">Sales History</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.return-reasons.index') }}" class="nav-link {{ request()->routeIs('merchant.return-reasons.*') ? 'active' : '' }}">Return reasons</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.cancellation-reasons.index') }}" class="nav-link {{ request()->routeIs('merchant.cancellation-reasons.*') ? 'active' : '' }}">Cancellation reasons</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.shops.*') || request()->routeIs('merchant.postal-code-restrictions.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.shops.*') || request()->routeIs('merchant.postal-code-restrictions.*') ? 'active' : '' }}">
                        <i class="ph-storefront"></i>
                        <span>Shop Management</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.shops.*') || request()->routeIs('merchant.postal-code-restrictions.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.shops.index') }}" class="nav-link {{ request()->routeIs('merchant.shops.*') ? 'active' : '' }}">My Shops</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.postal-code-restrictions.index') }}" class="nav-link {{ request()->routeIs('merchant.postal-code-restrictions.*') ? 'active' : '' }}">Postal Code Restrictions</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.products.*') || request()->routeIs('merchant.collections.*') || request()->routeIs('merchant.barcodes.*') || request()->routeIs('merchant.catalogue-masters.*') || request()->routeIs('merchant.availability-statuses.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.products.*') || request()->routeIs('merchant.collections.*') || request()->routeIs('merchant.barcodes.*') || request()->routeIs('merchant.catalogue-masters.*') || request()->routeIs('merchant.availability-statuses.*') ? 'active' : '' }}">
                        <i class="ph-package"></i>
                        <span>Catalog</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.products.*') || request()->routeIs('merchant.collections.*') || request()->routeIs('merchant.barcodes.*') || request()->routeIs('merchant.catalogue-masters.*') || request()->routeIs('merchant.availability-statuses.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.products.index') }}" class="nav-link {{ request()->routeIs('merchant.products.*') ? 'active' : '' }}">Products</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.collections.index') }}" class="nav-link {{ request()->routeIs('merchant.collections.*') ? 'active' : '' }}">Collections</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.barcodes.labels.index') }}" class="nav-link {{ request()->routeIs('merchant.barcodes.*') ? 'active' : '' }}">Barcode Labels</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.catalogue-masters.index') }}" class="nav-link {{ request()->routeIs('merchant.catalogue-masters.*') ? 'active' : '' }}">Categories & Attributes</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.availability-statuses.index') }}" class="nav-link {{ request()->routeIs('merchant.availability-statuses.*') ? 'active' : '' }}">Availability Statuses</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link {{ $disabled }}">
                        <i class="ph-stack"></i>
                        <span>Inventory</span>
                    </a>
                </li>

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.banners.*') || request()->routeIs('merchant.banner-library.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.banners.*') || request()->routeIs('merchant.banner-library.*') ? 'active' : '' }}">
                        <i class="ph-browser"></i>
                        <span>Storefront</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.banners.*') || request()->routeIs('merchant.banner-library.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.banner-library.index') }}" class="nav-link {{ request()->routeIs('merchant.banner-library.*') ? 'active' : '' }}">Banner Library</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.banners.index') }}" class="nav-link {{ request()->routeIs('merchant.banners.*') ? 'active' : '' }}">My Banners</a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('merchant.customers.index') }}" class="nav-link {{ request()->routeIs('merchant.customers.*') ? 'active' : '' }}">
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

                <li class="nav-item nav-item-submenu {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.settings.*') || request()->routeIs('merchant.tax-settings.*') || request()->routeIs('merchant.tax-slabs.*') || request()->routeIs('merchant.password.*') ? 'nav-item-expanded nav-item-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.settings.*') || request()->routeIs('merchant.tax-settings.*') || request()->routeIs('merchant.tax-slabs.*') || request()->routeIs('merchant.password.*') ? 'active' : '' }}">
                        <i class="ph-user-gear"></i>
                        <span>Account</span>
                    </a>
                    <ul class="nav-group-sub collapse {{ request()->routeIs('merchant.profile.*') || request()->routeIs('merchant.details.*') || request()->routeIs('merchant.settings.*') || request()->routeIs('merchant.tax-settings.*') || request()->routeIs('merchant.tax-slabs.*') || request()->routeIs('merchant.password.*') ? 'show' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('merchant.profile.edit') }}" class="nav-link {{ request()->routeIs('merchant.profile.*') ? 'active' : '' }}">My Profile</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.details.edit') }}" class="nav-link {{ request()->routeIs('merchant.details.*') ? 'active' : '' }}">Merchant Details</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.settings.edit') }}" class="nav-link {{ request()->routeIs('merchant.settings.*') ? 'active' : '' }}">Settings</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.tax-settings.edit') }}" class="nav-link {{ request()->routeIs('merchant.tax-settings.*') ? 'active' : '' }}">Tax Settings</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('merchant.tax-slabs.index') }}" class="nav-link {{ request()->routeIs('merchant.tax-slabs.*') ? 'active' : '' }}">Tax Slabs</a>
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
