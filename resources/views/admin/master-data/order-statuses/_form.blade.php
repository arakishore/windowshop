@php
    $isEdit = $orderStatus !== null;
    $isSystem = (bool) ($orderStatus?->is_system ?? false);
    $selectedCategory = old('category', $orderStatus?->category ?? 'open');
    $selectedBadgeType = old('badge_type', $orderStatus?->badge_type ?? 'secondary');
    $selectedStatus = old('status', $orderStatus?->status ?? 'active');
    $selectedSortOrder = old('sort_order', $orderStatus?->sort_order ?? 0);
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
    <div class="card-header">
        <h5 class="mb-0">Order Status Information</h5>
        @if($isSystem)
            <div class="text-muted small">System status workflow fields are protected. Only presentation fields can be changed.</div>
        @endif
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if($isEdit)
                <div class="col-md-3">
                    <label class="form-label">Code</label>
                    <input type="text" value="{{ $orderStatus->code }}" class="form-control" disabled>
                    <div class="form-text">Code is generated at creation and cannot be changed.</div>
                </div>
            @endif

            <div class="col-md-{{ $isEdit ? '3' : '4' }}">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $orderStatus?->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-{{ $isEdit ? '3' : '4' }}">
                <label for="customer_label" class="form-label">Customer Label</label>
                <input id="customer_label" name="customer_label" type="text" value="{{ old('customer_label', $orderStatus?->customer_label) }}" class="form-control @error('customer_label') is-invalid @enderror" maxlength="150">
                @error('customer_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-{{ $isEdit ? '3' : '4' }}">
                <label for="sort_order" class="form-label">Sort Order <span class="text-danger">*</span></label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ $selectedSortOrder }}" class="form-control @error('sort_order') is-invalid @enderror" required>
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" @disabled($isSystem) required>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ Str::headline($category) }}</option>
                    @endforeach
                </select>
                @if($isSystem)
                    <input type="hidden" name="category" value="{{ $orderStatus->category }}">
                @endif
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="badge_type" class="form-label">Badge Type <span class="text-danger">*</span></label>
                <select id="badge_type" name="badge_type" class="form-select @error('badge_type') is-invalid @enderror" required>
                    @foreach($badgeTypes as $badgeType)
                        <option value="{{ $badgeType }}" @selected($selectedBadgeType === $badgeType)>{{ Str::headline($badgeType) }}</option>
                    @endforeach
                </select>
                @error('badge_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" @disabled($isSystem) required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @if($isSystem)
                    <input type="hidden" name="status" value="{{ $orderStatus->status }}">
                @endif
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label d-block">Badge Preview</label>
                <span class="badge {{ $orderStatus?->safeBadgeClass() ?? 'bg-secondary' }}">{{ Str::headline($selectedBadgeType) }}</span>
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Description @if($isSystem)<span class="text-danger">*</span>@endif</label>
                <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror" maxlength="500" @if($isSystem) required @endif>{{ old('description', $orderStatus?->description) }}</textarea>
                <div class="form-text">{{ $isSystem ? 'Required for system-seeded statuses.' : 'Optional for custom statuses, but recommended.' }}</div>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="internal_notes" class="form-label">Internal Notes</label>
                <textarea id="internal_notes" name="internal_notes" rows="3" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $orderStatus?->internal_notes) }}</textarea>
                <div class="form-text">Admin-only notes for business purpose, implementation details, or future maintenance. Not visible to merchants or customers.</div>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-check">
                    <input name="is_terminal" value="1" type="checkbox" class="form-check-input" @checked(old('is_terminal', $orderStatus?->is_terminal ?? false)) @disabled($isSystem)>
                    <span class="form-check-label">Terminal status</span>
                </label>
            </div>

            <div class="col-md-4">
                <label class="form-check">
                    <input name="customer_visible" value="1" type="checkbox" class="form-check-input" @checked(old('customer_visible', $orderStatus?->customer_visible ?? true))>
                    <span class="form-check-label">Customer visible</span>
                </label>
            </div>

            <div class="col-md-4">
                <label class="form-check">
                    <input name="merchant_visible" value="1" type="checkbox" class="form-check-input" @checked(old('merchant_visible', $orderStatus?->merchant_visible ?? true))>
                    <span class="form-check-label">Merchant visible</span>
                </label>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Order Status' : 'Create Order Status'"
    :cancel="route('admin.master.order-statuses.index')"
    cancel-label="Cancel"
/>
