@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="Product Reviews" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Product Reviews' => null]" />
@endsection

@section('content')
<div class="card">
    <div class="card-header"><h5 class="mb-0">Review Moderation</h5></div>
    <div class="card-body border-bottom">
        <form method="GET" class="row g-3 align-items-end"><div class="col-md-4"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ $option === 'trash' ? 'Deleted / Trash' : ucfirst($option) }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-primary">Filter</button> <a class="btn btn-light" href="{{ route('admin.product-reviews.index') }}">Reset</a></div></form>
    </div>
    @if($reviews->isEmpty())
        <x-empty-state icon="ph-star" title="No reviews found" message="Reviews matching this status will appear here." />
    @else
    <form method="POST" action="{{ route('admin.product-reviews.bulk-action') }}" class="js-bulk-review-form">
        @csrf
        <div class="card-body border-bottom d-flex flex-wrap align-items-center gap-2">
            <div class="input-group" style="max-width:280px;">
                <label class="input-group-text" for="bulk_action">Bulk Actions</label>
                <select id="bulk_action" name="action" class="form-select" required>
                    <option value="">Choose</option>
                    @if($isTrash)
                        <option value="restore">Restore</option>
                        <option value="force_delete">Delete Permanently</option>
                    @else
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                        <option value="delete">Move to Trash</option>
                    @endif
                </select>
            </div>
            <button type="button" class="btn btn-light js-bulk-review-submit"><i class="ph-check-square-offset me-2"></i>Apply</button>
            <span class="text-muted small js-selected-review-count">0 selected</span>
        </div>
        <div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0"><thead class="table-light"><tr><th class="text-center" style="width:44px;"><input type="checkbox" class="form-check-input js-select-all-reviews" aria-label="Select all reviews"></th><th>Product / Order</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @foreach($reviews as $review)<tr><td class="text-center"><input type="checkbox" name="review_ids[]" value="{{ $review->getKey() }}" class="form-check-input js-review-checkbox" aria-label="Select review for {{ $review->product?->product_name ?? 'product' }}"></td><td><strong>{{ $review->product?->product_name ?? 'Deleted product' }}</strong><div class="text-muted fs-sm">{{ $review->order?->order_number }}</div></td><td>{{ $review->customer?->name ?? 'Customer' }}<div class="text-muted fs-sm">{{ $review->customer?->email }}</div></td><td>{{ $review->rating }} / 5</td><td>@if($review->title)<strong>{{ $review->title }}</strong><br>@endif{{ $review->review_text }}@if($review->images->isNotEmpty())<div class="d-flex flex-wrap gap-1 mt-2">@foreach($review->images as $image)<a href="{{ Storage::disk('public')->url($image->image_path) }}" target="_blank" rel="noopener"><img src="{{ Storage::disk('public')->url($image->thumbnail_path) }}" alt="Review image" class="rounded border" style="width:52px;height:52px;object-fit:cover;"></a>@endforeach</div>@endif</td><td>{{ app_datetime($isTrash ? $review->deleted_at : $review->created_at) }}</td><td>@if($isTrash)<span class="badge bg-danger">Deleted</span>@else<span class="badge {{ $review->status === 'approved' ? 'bg-success' : ($review->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ ucfirst($review->status) }}</span>@endif</td><td><div class="d-flex gap-2">@if($isTrash)<button type="submit" form="restore-review-{{ $review->getKey() }}" class="btn btn-sm btn-success">Restore</button><button type="button" class="btn btn-sm btn-danger js-force-delete-review" data-form="force-delete-review-{{ $review->getKey() }}">Delete Permanently</button>@else @if($review->status !== 'approved')<button type="submit" form="approve-review-{{ $review->getKey() }}" class="btn btn-sm btn-success">Approve</button>@endif @if($review->status !== 'rejected')<button type="submit" form="reject-review-{{ $review->getKey() }}" class="btn btn-sm btn-danger">Reject</button>@endif <button type="button" class="btn btn-sm btn-light js-delete-review" data-form="delete-review-{{ $review->getKey() }}">Delete</button>@endif</div></td></tr>@endforeach
        </tbody></table></div><div class="card-body">{{ $reviews->links('pagination::admin-datatable') }}</div>
    </form>
    @foreach($reviews as $review)
        <form id="approve-review-{{ $review->getKey() }}" method="POST" action="{{ route('admin.product-reviews.approve', $review) }}" class="d-none">@csrf @method('PATCH')</form>
        <form id="reject-review-{{ $review->getKey() }}" method="POST" action="{{ route('admin.product-reviews.reject', $review) }}" class="d-none">@csrf @method('PATCH')</form>
        <form id="delete-review-{{ $review->getKey() }}" method="POST" action="{{ route('admin.product-reviews.destroy', $review) }}" class="d-none">@csrf @method('DELETE')</form>
        <form id="restore-review-{{ $review->getKey() }}" method="POST" action="{{ route('admin.product-reviews.restore', $review) }}" class="d-none">@csrf @method('PATCH')</form>
        <form id="force-delete-review-{{ $review->getKey() }}" method="POST" action="{{ route('admin.product-reviews.force-delete', $review) }}" class="d-none">@csrf @method('DELETE')</form>
    @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.js-bulk-review-form');
    if (!form) return;
    const selectAll = form.querySelector('.js-select-all-reviews');
    const count = form.querySelector('.js-selected-review-count');
    const checkboxes = () => Array.from(form.querySelectorAll('.js-review-checkbox'));
    const updateCount = () => {
        const selected = checkboxes().filter((checkbox) => checkbox.checked).length;
        count.textContent = `${selected} selected`;
        selectAll.checked = selected > 0 && selected === checkboxes().length;
        selectAll.indeterminate = selected > 0 && selected < checkboxes().length;
    };
    selectAll.addEventListener('change', () => {
        checkboxes().forEach((checkbox) => { checkbox.checked = selectAll.checked; });
        updateCount();
    });
    checkboxes().forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    form.querySelector('.js-bulk-review-submit').addEventListener('click', () => {
        const action = form.querySelector('[name="action"]').value;
        const selected = checkboxes().filter((checkbox) => checkbox.checked).length;
        if (!action || selected === 0) {
            bootbox.alert('Please choose a bulk action and select at least one review.');
            return;
        }
        const labels = { approve: 'Approve', reject: 'Reject', delete: 'Move to Trash', restore: 'Restore', force_delete: 'Delete Permanently' };
        const permanent = action === 'force_delete';
        bootbox.confirm({
            title: `${labels[action]} Reviews`,
            message: permanent ? `Permanently delete ${selected} selected review(s) and all uploaded images? This cannot be undone.` : `Are you sure you want to ${labels[action].toLowerCase()} ${selected} selected review(s)?`,
            buttons: { cancel: { label: 'Cancel', className: 'btn-link' }, confirm: { label: labels[action], className: ['approve', 'restore'].includes(action) ? 'btn-success' : 'btn-danger' } },
            callback: (confirmed) => { if (confirmed) form.submit(); },
        });
    });
    document.querySelectorAll('.js-force-delete-review').forEach((button) => {
        button.addEventListener('click', () => bootbox.confirm({
            title: 'Delete Review Permanently',
            message: 'Permanently delete this review and all uploaded images? This cannot be undone.',
            buttons: { cancel: { label: 'Cancel', className: 'btn-link' }, confirm: { label: 'Delete Permanently', className: 'btn-danger' } },
            callback: (confirmed) => { if (confirmed) document.getElementById(button.dataset.form)?.submit(); },
        }));
    });
    document.querySelectorAll('.js-delete-review').forEach((button) => {
        button.addEventListener('click', () => bootbox.confirm({
            title: 'Move Review to Trash',
            message: 'Delete this review? It will be hidden publicly and can be restored from Trash.',
            buttons: { cancel: { label: 'Cancel', className: 'btn-link' }, confirm: { label: 'Delete', className: 'btn-danger' } },
            callback: (confirmed) => { if (confirmed) document.getElementById(button.dataset.form)?.submit(); },
        }));
    });
    updateCount();
});
</script>
@endpush
