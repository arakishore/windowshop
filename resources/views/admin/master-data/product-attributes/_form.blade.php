@php
    $isEdit = $group !== null;
    $selectedStatus = old('status', $group?->status ?? 'active');
    $selectedSelectionType = old('selection_type', $group?->selection_type ?? 'single');
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

<div class="border rounded bg-white p-3">
    <div class="row g-3">
        <div class="col-md-5">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name', $group?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input id="code" name="code" type="text" value="{{ old('code', $group?->code) }}" class="form-control @error('code') is-invalid @enderror" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Use a stable slug, such as size or color.</div>
        </div>

        <div class="col-md-2">
            <label for="selection_type" class="form-label">Selection <span class="text-danger">*</span></label>
            <select id="selection_type" name="selection_type" class="form-select @error('selection_type') is-invalid @enderror" required>
                <option value="single" @selected($selectedSelectionType === 'single')>Single</option>
                <option value="multiple" @selected($selectedSelectionType === 'multiple')>Multiple</option>
            </select>
            @error('selection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-2">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $group?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-2">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                <option value="active" @selected($selectedStatus === 'active')>Active</option>
                <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $group?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('admin.master.product-attributes.index') }}" class="btn btn-light border">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Attribute' : 'Create Attribute' }}</button>
</div>
