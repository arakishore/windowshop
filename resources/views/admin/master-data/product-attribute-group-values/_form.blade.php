@php
    $isEdit = $value !== null;
    $selectedStatus = old('status', $value?->status ?? 'active');
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
            <input id="name" name="name" type="text" value="{{ old('name', $value?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input id="code" name="code" type="text" value="{{ old('code', $value?->code) }}" class="form-control @error('code') is-invalid @enderror" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Unique within {{ $group->name }}.</div>
        </div>

        <div class="col-md-2">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $value?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
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
            <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $value?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('admin.master.product-attributes.values.index', $group) }}" class="btn btn-light border">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Value' : 'Create Value' }}</button>
</div>
