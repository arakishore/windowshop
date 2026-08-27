@php
    $isEdit = $reason !== null;
    $selectedStatus = old('status', $reason?->status ?? 'active');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header"><h5 class="mb-0">Reason Information</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $reason?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                <input id="code" name="code" type="text" value="{{ old('code', $reason?->code) }}" class="form-control @error('code') is-invalid @enderror" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $reason?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input id="requires_comment" name="requires_comment" type="checkbox" value="1" class="form-check-input" @checked(old('requires_comment', $reason?->requires_comment ?? false))>
                    <label for="requires_comment" class="form-check-label">Requires Comment</label>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Reason' : 'Create Reason'"
    :cancel="route('admin.master.customer-cancellation-reasons.index')"
    cancel-label="Cancel"
/>
