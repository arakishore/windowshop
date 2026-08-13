@php
    $isEdit = $postalCode !== null;
    $selectedStatus = old('status', $postalCode?->status ?? 'active');
    $selectedShipping = old('shipping_enabled', $postalCode?->shipping_enabled === false ? '0' : '1');
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
        <h5 class="mb-0">Postal Code Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $postalCode?->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror" required>
                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label for="office_name" class="form-label">Office / Place Name <span class="text-danger">*</span></label>
                <input id="office_name" name="office_name" type="text" value="{{ old('office_name', $postalCode?->office_name) }}" class="form-control @error('office_name') is-invalid @enderror" required>
                @error('office_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="office_type" class="form-label">Office Type</label>
                <input id="office_type" name="office_type" type="text" value="{{ old('office_type', $postalCode?->office_type) }}" class="form-control @error('office_type') is-invalid @enderror">
                @error('office_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="delivery_status" class="form-label">Delivery</label>
                <input id="delivery_status" name="delivery_status" type="text" value="{{ old('delivery_status', $postalCode?->delivery_status) }}" class="form-control @error('delivery_status') is-invalid @enderror">
                @error('delivery_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="district" class="form-label">District</label>
                <input id="district" name="district" type="text" value="{{ old('district', $postalCode?->district) }}" class="form-control @error('district') is-invalid @enderror">
                @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="state" class="form-label">State</label>
                <input id="state" name="state" type="text" value="{{ old('state', $postalCode?->state) }}" class="form-control @error('state') is-invalid @enderror">
                @error('state')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="latitude" class="form-label">Latitude</label>
                <input id="latitude" name="latitude" type="number" step="0.0000001" value="{{ old('latitude', $postalCode?->latitude) }}" class="form-control @error('latitude') is-invalid @enderror">
                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="longitude" class="form-label">Longitude</label>
                <input id="longitude" name="longitude" type="number" step="0.0000001" value="{{ old('longitude', $postalCode?->longitude) }}" class="form-control @error('longitude') is-invalid @enderror">
                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="circle_name" class="form-label">Circle</label>
                <input id="circle_name" name="circle_name" type="text" value="{{ old('circle_name', $postalCode?->circle_name) }}" class="form-control @error('circle_name') is-invalid @enderror">
                @error('circle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="region_name" class="form-label">Region</label>
                <input id="region_name" name="region_name" type="text" value="{{ old('region_name', $postalCode?->region_name) }}" class="form-control @error('region_name') is-invalid @enderror">
                @error('region_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="division_name" class="form-label">Division</label>
                <input id="division_name" name="division_name" type="text" value="{{ old('division_name', $postalCode?->division_name) }}" class="form-control @error('division_name') is-invalid @enderror">
                @error('division_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label for="shipping_enabled" class="form-label">Shipping <span class="text-danger">*</span></label>
                <select id="shipping_enabled" name="shipping_enabled" class="form-select @error('shipping_enabled') is-invalid @enderror" required>
                    <option value="1" @selected((string) $selectedShipping === '1')>Yes</option>
                    <option value="0" @selected((string) $selectedShipping === '0')>No</option>
                </select>
                @error('shipping_enabled')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Postal Code' : 'Create Postal Code'"
    :cancel="route('admin.master.postal-codes.index')"
    cancel-label="Cancel"
/>
