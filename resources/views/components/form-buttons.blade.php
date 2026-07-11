{{-- Purpose: Standardizes create/edit form action buttons across admin pages. --}}
@props([
    'submit' => 'Save changes',
    'cancel' => null,
    'cancelLabel' => 'Back',
    'cancelClass' => 'btn-light border',
])

<div class="card">
    <div class="card-footer d-flex justify-content-end gap-2">
    @if($cancel)
        <a href="{{ $cancel }}" class="btn {{ $cancelClass }}">{{ $cancelLabel }}</a>
    @endif
    <button type="submit" class="btn btn-primary">{{ $submit }}</button>
    </div>
</div>
