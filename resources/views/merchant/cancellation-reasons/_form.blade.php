@php
    $status = old('status', $reason->status ?: 'active');
@endphp

<div class="card-body">
    <div class="row g-3">
        <div class="col-lg-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" value="{{ old('name', $reason->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="120" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-6">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input id="code" name="code" value="{{ old('code', $reason->code) }}" class="form-control @error('code') is-invalid @enderror" maxlength="80" @readonly($isEdit) required>
            <div class="form-text">Stable internal key. Use lowercase snake_case.</div>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="500" class="form-control @error('description') is-invalid @enderror">{{ old('description', $reason->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="internal_notes" class="form-label">Internal Notes</label>
            <textarea id="internal_notes" name="internal_notes" rows="3" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $reason->internal_notes) }}</textarea>
            @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-6">
            <label for="sort_order" class="form-label">Sort Order <span class="text-danger">*</span></label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $reason->sort_order ?? 99) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-6">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <label class="form-check">
                <input name="customer_selectable" value="1" type="checkbox" class="form-check-input @error('customer_selectable') is-invalid @enderror" @checked(old('customer_selectable', $reason->customer_selectable))>
                <span class="form-check-label">Customer selectable</span>
                @error('customer_selectable')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </label>
        </div>
        <div class="col-lg-4">
            <label class="form-check">
                <input name="merchant_selectable" value="1" type="checkbox" class="form-check-input @error('merchant_selectable') is-invalid @enderror" @checked(old('merchant_selectable', $reason->merchant_selectable ?? true))>
                <span class="form-check-label">Merchant selectable</span>
                @error('merchant_selectable')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </label>
        </div>
        <div class="col-lg-4">
            <label class="form-check">
                <input name="requires_comment" value="1" type="checkbox" class="form-check-input @error('requires_comment') is-invalid @enderror" @checked(old('requires_comment', $reason->requires_comment))>
                <span class="form-check-label">Requires comment</span>
                @error('requires_comment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </label>
        </div>
    </div>
</div>

<div class="card-footer d-flex justify-content-between gap-2">
    <a href="{{ route('merchant.cancellation-reasons.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="ph-floppy-disk me-2"></i>
        Save
    </button>
</div>
