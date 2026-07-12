{{-- Purpose: Displays reusable session flash messages using Limitless alert styling. --}}
@if(session('success'))
    <div class="px-3 pt-3">
        <div class="alert alert-success alert-dismissible fade show mb-0">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="px-3 pt-3">
        <div class="alert alert-danger alert-dismissible fade show mb-0">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif

@if(session('warning'))
    <div class="px-3 pt-3">
        <div class="alert alert-warning alert-dismissible fade show mb-0">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
