{{-- Purpose: Provides the shared Limitless sidebar navigation for admin pages. --}}
@php
	$isMasterDataActive = request()->routeIs('admin.master.*');
@endphp
<!-- Main sidebar -->
		<div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">

			<!-- Sidebar content -->
			<div class="sidebar-content">

				<!-- Sidebar header -->
				<div class="sidebar-section">
					<div class="sidebar-section-body d-flex justify-content-center">
						<h5 class="sidebar-resize-hide flex-grow-1 my-auto">Navigation</h5>

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

						<!-- Main -->
						<li class="nav-item-header pt-0">
							<div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Main</div>
							<i class="ph-dots-three sidebar-resize-show"></i>
						</li>
						<li class="nav-item">
							<a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
								<i class="ph-house"></i>
								<span>
									Dashboard
									<span class="d-block fw-normal opacity-50">No pending orders</span>
								</span>
							</a>
						</li>
						<li class="nav-item">
							<a href="{{ route('admin.merchants.index') }}" class="nav-link {{ request()->routeIs('admin.merchants.*') ? 'active' : '' }}">
								<i class="ph-storefront"></i>
								<span>
									Merchants
								</span>
							</a>
						</li>

						<li class="nav-item nav-item-submenu {{ $isMasterDataActive ? 'nav-item-expanded nav-item-open' : '' }}">
							<a href="#" class="nav-link {{ $isMasterDataActive ? 'active' : '' }}">
								<i class="ph-database"></i>
								<span>Master Data</span>
							</a>
							<ul class="nav-group-sub collapse {{ $isMasterDataActive ? 'show' : '' }}">
								<li class="nav-item">
									<a href="{{ route('admin.master.shop-categories.index') }}" class="nav-link {{ request()->routeIs('admin.master.shop-categories.*') ? 'active' : '' }}">
										Shop Categories
									</a>
								</li>
								<li class="nav-item">
									<a href="{{ route('admin.master.shop-audiences.index') }}" class="nav-link {{ request()->routeIs('admin.master.shop-audiences.*') ? 'active' : '' }}">
										Shop Audiences
									</a>
								</li>
								<li class="nav-item">
									<a href="{{ route('admin.master.brands.index') }}" class="nav-link {{ request()->routeIs('admin.master.brands.*') ? 'active' : '' }}">
										Brands
									</a>
								</li>
							</ul>
						</li>
						 
      
						<!-- /page kits -->

					</ul>
				</div>
				<!-- /main navigation -->

			</div>
			<!-- /sidebar content -->
			
		</div>
		<!-- /main sidebar -->
