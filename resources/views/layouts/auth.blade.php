{{-- Purpose: Minimal authentication layout for login-style pages without admin navigation chrome. --}}
@include('partials.head')

<body>

<!-- Page content -->
<div class="page-content">

    <!-- Main content -->
    <div class="content-wrapper">

        <!-- Inner content -->
        <div class="content-inner">
            <!-- Content area -->
            <div class="content d-flex justify-content-center align-items-center">
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
