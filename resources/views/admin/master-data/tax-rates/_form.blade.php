@php
    $isEdit = $taxRate !== null;
    $selectedStatus = old('status', $taxRate?->status ?? 'active');
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
        <h5 class="mb-0">Tax Rate Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="name" class="form-label">
                    Name <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Display name for this percentage slab under the tax class."></i>
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $taxRate?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                <div class="form-text">Example: <code>GST 18%</code> for an 18 percent GST slab.</div>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="total_rate" class="form-label">
                    Total Rate <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Total percentage applied before splitting into components. Component rates must add up to this value when the rate is active."></i>
                </label>
                <input id="total_rate" name="total_rate" type="number" step="0.0001" min="0" value="{{ old('total_rate', $taxRate?->total_rate) }}" class="form-control @error('total_rate') is-invalid @enderror" required>
                <div class="form-text">Example: enter <code>18.0000</code> for 18%.</div>
                @error('total_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="effective_from" class="form-label">
                    Effective From <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="First date this rate is valid. Active rates for the same tax class and same total rate cannot overlap. Different slabs can be active together."></i>
                </label>
                <input id="effective_from" name="effective_from" type="date" value="{{ old('effective_from', $taxRate?->effective_from?->format('Y-m-d')) }}" class="form-control @error('effective_from') is-invalid @enderror" required>
                <div class="form-text">Example: <code>2017-07-01</code> for India GST reference data.</div>
                @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="effective_to" class="form-label">
                    Effective To
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Last date this rate is valid. Leave blank for an open-ended current or reference rate."></i>
                </label>
                <input id="effective_to" name="effective_to" type="date" value="{{ old('effective_to', $taxRate?->effective_to?->format('Y-m-d')) }}" class="form-control @error('effective_to') is-invalid @enderror">
                <div class="form-text">Leave blank until the government publishes a replacement rate.</div>
                @error('effective_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <label for="priority" class="form-label">
                    Priority
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Deterministic ordering for display or resolution. It must not be used to hide overlapping active date ranges for the same total rate."></i>
                </label>
                <input id="priority" name="priority" type="number" min="0" value="{{ old('priority', $taxRate?->priority ?? 0) }}" class="form-control @error('priority') is-invalid @enderror">
                <div class="form-text">Usually <code>0</code>.</div>
                @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <label for="status" class="form-label">
                    Status <span class="text-danger">*</span>
                    <i class="ph-question ms-1 text-muted" data-bs-popup="tooltip" title="Use inactive while staging components. Active rates are checked for date overlaps and matching component totals."></i>
                </label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                <div class="form-text">Activate only after components are complete.</div>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Tax Rate' : 'Create Tax Rate'"
    :cancel="route('admin.master.tax-classes.show', $taxClass)"
    cancel-label="Back"
/>
