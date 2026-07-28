@php
    $isEdit = $taxClass !== null;
    $selectedCountryId = (string) old('country_id', $taxClass?->country_id);
    $selectedStatus = old('status', $taxClass?->status ?? 'active');
    $selectedSortOrder = old('sort_order', $taxClass?->sort_order ?? 0);
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
        <h5 class="mb-0">Tax Class Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="country_id" class="form-label">
                    Country <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Tax classes are country-specific. Example: choose India for GST, United Kingdom for VAT, or United States for sales tax."></i>
                </label>
                <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                    <option value="">Select country</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->getKey() }}" @selected($selectedCountryId === (string) $country->getKey())>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Used later to match a merchant or transaction country with the correct tax master.</div>
                @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="code" class="form-label">
                    Code <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Stable short code within the selected country. Keep it simple and reusable, such as GST, VAT, STANDARD, ZERO, or EXEMPT."></i>
                </label>
                <input id="code" name="code" type="text" value="{{ old('code', $taxClass?->code) }}" class="form-control text-uppercase @error('code') is-invalid @enderror" maxlength="50" required>
                <div class="form-text">Example: <code>GST_5</code> for the India GST 5% slab.</div>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="name" class="form-label">
                    Name <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Admin-facing display name for this tax class. This name helps users identify the tax system."></i>
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $taxClass?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                <div class="form-text">Example: GST 5%.</div>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label for="sort_order" class="form-label">
                    Sort Order
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Lower numbers appear first in tax class lists and dropdowns. Use this to keep slabs in rate order."></i>
                </label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ $selectedSortOrder }}" class="form-control @error('sort_order') is-invalid @enderror">
                <div class="form-text">Example: GST 5% before GST 18%.</div>
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label for="status" class="form-label">
                    Status <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Inactive records are reference data and cannot be selected by future active tax workflows until enabled."></i>
                </label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                <div class="form-text">Keep seeded or draft tax masters inactive until reviewed.</div>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="description" class="form-label">
                    Description
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Internal note explaining when this tax class should be used."></i>
                </label>
                <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $taxClass?->description) }}</textarea>
                <div class="form-text">Example: India GST reference tax class for domestic taxable sales.</div>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Tax Class' : 'Create Tax Class'"
    :cancel="route('admin.master.tax-classes.index')"
    cancel-label="Cancel"
/>
