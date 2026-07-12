{{-- Purpose: Merchant panel layout using the same Limitless shell as admin pages with merchant-specific navigation. --}}
@include('partials.head')

<body>

@include('partials.merchant.navbar')

<!-- Page content -->
<div class="page-content">

    @include('partials.merchant.sidebar')

    <!-- Main content -->
    <div class="content-wrapper">

        <!-- Inner content -->
        <div class="content-inner">
            @yield('breadcrumb')

            @hasSection('page_title')
                <x-page-header :title="trim($__env->yieldContent('page_title'))" />
            @endif

            @include('partials.flash-message')

            <!-- Content area -->
            <div class="content">
                @yield('content')
            </div>
            <!-- /content area -->

            @include('partials.footer')
        </div>
        <!-- /inner content -->

    </div>
    <!-- /main content -->

</div>
<!-- /page content -->

@include('partials.scripts')

</body>
</html>
