{{-- Purpose: Standardizes create/edit form action buttons across admin pages. --}}
@props([
    'submit' => 'Save changes',
    'cancel' => null,
])

<div class="d-flex justify-content-end gap-2">
    @if($cancel)
        <a href="{{ $cancel }}" class="btn btn-light">Cancel</a>
    @endif
    <button type="submit" class="btn btn-primary">{{ $submit }}</button>
</div>
