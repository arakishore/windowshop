@php
    $isEdit = $taxRateComponent !== null;
    $selectedJurisdiction = old('jurisdiction_type', $taxRateComponent?->jurisdiction_type);
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
        <h5 class="mb-0">Tax Component Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="code" class="form-label">
                    Code <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Short component code unique within this tax rate. Examples: CGST, SGST, VAT, STATE, CITY."></i>
                </label>
                <input id="code" name="code" type="text" value="{{ old('code', $taxRateComponent?->code) }}" class="form-control text-uppercase @error('code') is-invalid @enderror" maxlength="50" required>
                <div class="form-text">Example: <code>CGST</code> or <code>SGST</code>.</div>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="name" class="form-label">
                    Name <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Readable name shown to admins and future receipts/reports."></i>
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $taxRateComponent?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                <div class="form-text">Example: Central GST, State GST, or VAT.</div>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="rate" class="form-label">
                    Rate <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Component percentage. For active rates, all active components must total the tax rate total."></i>
                </label>
                <input id="rate" name="rate" type="number" step="0.0001" min="0" value="{{ old('rate', $taxRateComponent?->rate) }}" class="form-control @error('rate') is-invalid @enderror" required>
                <div class="form-text">Example: GST 18% can use <code>CGST 9.0000</code> + <code>SGST 9.0000</code>.</div>
                @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="jurisdiction_type" class="form-label">
                    Jurisdiction Type
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Classifies who receives the component. Current values are central, state, integrated, cess, and local."></i>
                </label>
                <select id="jurisdiction_type" name="jurisdiction_type" class="form-select @error('jurisdiction_type') is-invalid @enderror">
                    <option value="">None</option>
                    @foreach($jurisdictionTypes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedJurisdiction === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Example: CGST is central, SGST is state.</div>
                @error('jurisdiction_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <label for="priority" class="form-label">
                    Priority
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Controls component display order. It does not change the percentage calculation."></i>
                </label>
                <input id="priority" name="priority" type="number" min="0" value="{{ old('priority', $taxRateComponent?->priority ?? 0) }}" class="form-control @error('priority') is-invalid @enderror">
                <div class="form-text">Example: CGST <code>1</code>, SGST <code>2</code>.</div>
                @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Component' : 'Create Component'"
    :cancel="route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate])"
    cancel-label="Cancel"
/>
