{{-- Purpose: Wraps content in the standard Limitless card pattern with optional header, tools, and footer slots. --}}
@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || isset($tools))
        <div class="card-header d-flex align-items-center">
            @if($title)
                <h5 class="mb-0">{{ $title }}</h5>
            @endif

            @isset($tools)
                <div class="ms-auto">
                    {{ $tools }}
                </div>
            @endisset
        </div>
    @endif

    <div class="card-body">
        {{ $body ?? $slot }}
    </div>

    @isset($footer)
        <div class="card-footer d-flex justify-content-end gap-2">
            {{ $footer }}
        </div>
    @endisset
</div>
