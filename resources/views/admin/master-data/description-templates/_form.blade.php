@php
    $isEdit = $template !== null;
    $selectedCategoryId = old('shop_category_id', $template?->shop_category_id);
    $selectedStatus = old('status', $template?->status ?? 'active');
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
            <label for="shop_category_id" class="form-label">Category <span class="text-danger">*</span></label>
            <select id="shop_category_id" name="shop_category_id" class="form-select @error('shop_category_id') is-invalid @enderror" required>
                <option value="">Select category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $selectedCategoryId === (int) $category->id)>
                        {{ $category->name }}{{ $category->status !== 'active' ? ' (Inactive)' : '' }}
                    </option>
                @endforeach
            </select>
            @error('shop_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label for="name" class="form-label">Template Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name', $template?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-2">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $template?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
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
            <label for="short_description_template" class="form-label">Short Description Template <span class="text-danger">*</span></label>
            <textarea id="short_description_template" name="short_description_template" rows="3" class="form-control @error('short_description_template') is-invalid @enderror" required>{{ old('short_description_template', $template?->short_description_template) }}</textarea>
            @error('short_description_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label for="description_template" class="form-label">Description Template <span class="text-danger">*</span></label>
            <textarea id="description_template" name="description_template" rows="12" class="form-control @error('description_template') is-invalid @enderror" required>{{ old('description_template', $template?->description_template) }}</textarea>
            @error('description_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <div class="alert alert-info mb-0">
                <div class="fw-semibold mb-2">Supported placeholders</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($placeholders as $placeholder)
                        <code>{{ '{'.$placeholder.'}' }}</code>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('admin.master.description-templates.index') }}" class="btn btn-light border">Cancel</a>
    @if($isEdit)
        <a href="{{ route('admin.master.description-templates.preview', $template) }}" class="btn btn-light border">
            <i class="ph-eye me-2"></i>
            Preview
        </a>
    @endif
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Template' : 'Create Template' }}</button>
</div>
