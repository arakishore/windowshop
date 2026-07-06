{{-- Purpose: Shows a consistent empty-state message for admin modules before records exist. --}}
@props([
    'icon' => 'ph-folder-open',
    'title' => 'Nothing here yet',
    'message' => 'Records will appear here once they are created.',
])

<div {{ $attributes->merge(['class' => 'text-center py-5']) }}>
    <i class="{{ $icon }} display-6 text-muted mb-3"></i>
    <h6 class="mb-1">{{ $title }}</h6>
    <p class="text-muted mb-0">{{ $message }}</p>
</div>
