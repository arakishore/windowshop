@php
    $user = $merchant?->user;
    $isEdit = $merchant !== null;
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

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Owner Account</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">User name <span class="text-danger">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="mobile" class="form-label">Mobile</label>
                    <input id="mobile" name="mobile" type="text" value="{{ old('mobile', $user?->mobile) }}" class="form-control @error('mobile') is-invalid @enderror">
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                    <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" @unless($isEdit) required @endunless autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label for="password_confirmation" class="form-label">Confirm password @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" @unless($isEdit) required @endunless autocomplete="new-password">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Business Profile</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="business_name" class="form-label">Business name <span class="text-danger">*</span></label>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $merchant?->business_name) }}" class="form-control @error('business_name') is-invalid @enderror" required>
                    @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="legal_name" class="form-label">Legal name</label>
                    <input id="legal_name" name="legal_name" type="text" value="{{ old('legal_name', $merchant?->legal_name) }}" class="form-control @error('legal_name') is-invalid @enderror">
                    @error('legal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="business_type" class="form-label">Business type</label>
                    <select id="business_type" name="business_type" class="form-select @error('business_type') is-invalid @enderror">
                        <option value="">Select type</option>
                        @foreach($businessTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('business_type', $merchant?->business_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('business_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gst_number" class="form-label">GST number</label>
                        <input id="gst_number" name="gst_number" type="text" value="{{ old('gst_number', $merchant?->gst_number) }}" class="form-control @error('gst_number') is-invalid @enderror">
                        @error('gst_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="pan_number" class="form-label">PAN number</label>
                        <input id="pan_number" name="pan_number" type="text" value="{{ old('pan_number', $merchant?->pan_number) }}" class="form-control @error('pan_number') is-invalid @enderror">
                        @error('pan_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Contact and Status</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-4 mb-3">
                <label for="contact_person_name" class="form-label">Contact person</label>
                <input id="contact_person_name" name="contact_person_name" type="text" value="{{ old('contact_person_name', $merchant?->contact_person_name) }}" class="form-control @error('contact_person_name') is-invalid @enderror">
                @error('contact_person_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="contact_email" class="form-label">Contact email</label>
                <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $merchant?->contact_email) }}" class="form-control @error('contact_email') is-invalid @enderror">
                @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="website_url" class="form-label">Website URL</label>
                <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $merchant?->website_url) }}" class="form-control @error('website_url') is-invalid @enderror">
                @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="contact_mobile" class="form-label">Contact mobile</label>
                <input id="contact_mobile" name="contact_mobile" type="text" value="{{ old('contact_mobile', $merchant?->contact_mobile) }}" class="form-control @error('contact_mobile') is-invalid @enderror">
                @error('contact_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="alternate_mobile" class="form-label">Alternate mobile</label>
                <input id="alternate_mobile" name="alternate_mobile" type="text" value="{{ old('alternate_mobile', $merchant?->alternate_mobile) }}" class="form-control @error('alternate_mobile') is-invalid @enderror">
                @error('alternate_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="status" class="form-label">Account status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach($accountStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $merchant?->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-4 mb-3">
                <label for="verification_status" class="form-label">Verification status <span class="text-danger">*</span></label>
                <select id="verification_status" name="verification_status" class="form-select @error('verification_status') is-invalid @enderror" required>
                    @foreach($verificationStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('verification_status', $merchant?->verification_status ?? 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('verification_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-8 mb-3">
                <label for="rejection_reason" class="form-label">Rejection reason</label>
                <input id="rejection_reason" name="rejection_reason" type="text" value="{{ old('rejection_reason', $merchant?->rejection_reason) }}" class="form-control @error('rejection_reason') is-invalid @enderror">
                @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if($isEdit)
                <div class="col-12 mb-3">
                    <label for="admin_comment" class="form-label">Verification internal comment</label>
                    <textarea id="admin_comment" name="admin_comment" rows="3" class="form-control @error('admin_comment') is-invalid @enderror">{{ old('admin_comment') }}</textarea>
                    @error('admin_comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="col-12 mb-0">
                <label for="admin_note" class="form-label">Admin note</label>
                <textarea id="admin_note" name="admin_note" rows="4" class="form-control @error('admin_note') is-invalid @enderror">{{ old('admin_note', $merchant?->admin_note) }}</textarea>
                @error('admin_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons :submit="$isEdit ? 'Update merchant' : 'Create merchant'" :cancel="route('admin.merchants.index')" />
