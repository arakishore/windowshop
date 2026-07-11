{{-- Purpose: Provides a reusable Limitless breadcrumb header with optional right-side actions. --}}
@props([
    'title' => null,
    'breadcrumbs' => [],
    'actionUrl' => null,
    'actionLabel' => null,
    'actionIcon' => 'ph-plus',
    'actionClass' => 'btn-primary',
])

<!-- Page header -->
<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex border-top">
        <div class="d-flex">
            <div class="breadcrumb py-2">
                @forelse($breadcrumbs as $label => $url)
                    @if($url)
                        <a href="{{ $url }}" class="breadcrumb-item">
                            @if($loop->first)
                                <i class="ph-house"></i>
                            @else
                                {{ $label }}
                            @endif
                        </a>
                    @else
                        <span class="breadcrumb-item active">{{ $label }}</span>
                    @endif
                @empty
                    <span class="breadcrumb-item active">{{ $title ?? trim($__env->yieldContent('page_title')) }}</span>
                @endforelse
            </div>

            @if($actionUrl && $actionLabel)
                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            @endif
        </div>

        @if($actionUrl && $actionLabel)
            <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
                <div class="d-lg-flex mb-2 mb-lg-0">
                    <a href="{{ $actionUrl }}" class="btn {{ $actionClass }} btn-icon btn-sm pt-1 pb-1">
                        <i class="{{ $actionIcon }} me-2"></i>
                        {{ $actionLabel }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
<!-- /page header -->
