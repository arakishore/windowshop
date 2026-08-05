{{-- Purpose: Edits one seeded global system setting. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit System Setting"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'System Settings' => route('admin.system-settings.index'), $setting->label => null]"
    />
@endsection

@section('content')
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

    <form method="POST" action="{{ route('admin.system-settings.update', $setting) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">System Setting Information</h5>
                <div class="text-muted small">Key is protected. Edit the seeded value and presentation fields only.</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Key</label>
                        <div class="form-control-plaintext border rounded bg-light px-3">{{ $setting->key }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Group</label>
                        <div class="form-control-plaintext border rounded bg-light px-3">{{ $setting->group?->name ?? 'Ungrouped' }}</div>
                        <input type="hidden" name="group_id" value="{{ $setting->group_id }}">
                        @error('group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Label</label>
                        <div class="form-control-plaintext border rounded bg-light px-3">{{ $setting->label }}</div>
                        <input type="hidden" name="label" value="{{ $setting->label }}">
                        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="value" class="form-label">Value</label>
                        <textarea id="value" name="value" rows="3" class="form-control @error('value') is-invalid @enderror">{{ old('value', $setting->value) }}</textarea>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Value Type</label>
                        <div class="form-control-plaintext border rounded bg-light px-3">{{ Str::headline($setting->value_type) }}</div>
                        <input type="hidden" name="value_type" value="{{ $setting->value_type }}">
                        @error('value_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="sort_order" class="form-label">Sort Order <span class="text-danger">*</span></label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $setting->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $setting->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-4">
                        <label class="form-check mb-2">
                            <input name="is_public" value="1" type="checkbox" class="form-check-input" @checked(old('is_public', $setting->is_public))>
                            <span class="form-check-label">Public</span>
                        </label>
                        <label class="form-check mb-2">
                            <input name="is_encrypted" value="1" type="checkbox" class="form-check-input" @checked(old('is_encrypted', $setting->is_encrypted))>
                            <span class="form-check-label">Encrypted</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $setting->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <x-form-buttons
            submit="Update System Setting"
            :cancel="route('admin.system-settings.index')"
            cancel-label="Cancel"
        />
    </form>
@endsection
