{{-- Purpose: Renders optional Bootstrap/Limitless breadcrumbs for admin pages. --}}
@if(! empty($breadcrumbs))
    <div class="page-header page-header-light shadow">
        <div class="page-header-content d-lg-flex border-top">
            <div class="d-flex">
                <div class="breadcrumb py-2">
                    @foreach($breadcrumbs as $label => $url)
                        @if($url)
                            <a href="{{ $url }}" class="breadcrumb-item">{{ $label }}</a>
                        @else
                            <span class="breadcrumb-item active">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
