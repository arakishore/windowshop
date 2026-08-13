@php
    $isEdit = $restriction !== null;
    $selectedStatus = old('status', $restriction?->status ?? 'active');
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
    <div class="card-header"><h5 class="mb-0">Shop Restriction</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $restriction?->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror" required>
                <div class="form-text">Must exist in Postal Codes master.</div>
                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="starts_at" class="form-label">Starts At</label>
                <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $restriction?->starts_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('starts_at') is-invalid @enderror">
                @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="ends_at" class="form-label">Ends At</label>
                <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $restriction?->ends_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('ends_at') is-invalid @enderror">
                @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="reason" class="form-label">Reason</label>
                <textarea id="reason" name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror">{{ old('reason', $restriction?->reason) }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@include('shared.postal-code-restrictions.reason-help')

<x-form-buttons :submit="$isEdit ? 'Update Restriction' : 'Create Restriction'" :cancel="route('merchant.postal-code-restrictions.index')" cancel-label="Cancel" />
