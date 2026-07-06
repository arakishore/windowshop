{{-- Purpose: Provides a reusable Limitless page header with title, subtitle, and optional breadcrumbs. --}}
@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

<!-- Page header -->
<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <div>
                <h4 class="page-title mb-0">{{ $title ?? trim($__env->yieldContent('page_title')) }}</h4>
                @if($subtitle)
                    <div class="text-muted mt-1">{{ $subtitle }}</div>
                @endif
            </div>

            <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        @if(! empty($breadcrumbs))
            <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
                <div class="breadcrumb mb-0 py-2">
                    @foreach($breadcrumbs as $label => $url)
                        @if($url)
                            <a href="{{ $url }}" class="breadcrumb-item">{{ $label }}</a>
                        @else
                            <span class="breadcrumb-item active">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
<!-- /page header -->
