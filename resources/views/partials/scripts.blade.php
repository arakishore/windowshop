{{-- Purpose: Loads shared Bootstrap, Limitless, and page-level JavaScript assets. --}}
<!-- Core JS files -->
<script src="{{ asset('assets/admin/js/bootstrap/bootstrap.bundle.min.js') }}"></script>
<!-- /core JS files -->

<!-- Theme JS files -->
<script src="{{ asset('assets/admin/js/app.js') }}"></script>
<!-- /theme JS files -->

@stack('scripts')
