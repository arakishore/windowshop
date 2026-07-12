@php
    $user = $merchant?->user;
    $isEdit = $merchant !== null;
    $selectedVerificationStatus = old('verification_status', $merchant?->verification_status ?? 'pending');
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

<div class="row g-3">
    <div class="col-xl-6 col-lg-6 col-12">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Owner Account</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Owner Name <span class="text-danger">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
                        <input id="mobile" name="mobile" type="text" value="{{ old('mobile', $user?->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" required autocomplete="tel">
                        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">{{ $isEdit ? 'New Password' : 'Password' }} @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" @unless($isEdit) required @endunless autocomplete="new-password">
                        @if($isEdit)<div class="form-text">Leave blank to keep the current password.</div>@endif
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">{{ $isEdit ? 'Confirm New Password' : 'Confirm Password' }} @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" @unless($isEdit) required @endunless autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-lg-6 col-12">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Business Profile</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                        <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $merchant?->business_name) }}" class="form-control @error('business_name') is-invalid @enderror" required>
                        @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="legal_name" class="form-label">Legal Name</label>
                        <input id="legal_name" name="legal_name" type="text" value="{{ old('legal_name', $merchant?->legal_name) }}" class="form-control @error('legal_name') is-invalid @enderror">
                        @error('legal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="business_type" class="form-label">Business Type <span class="text-danger">*</span></label>
                        <select id="business_type" name="business_type" class="form-select @error('business_type') is-invalid @enderror" required>
                            <option value="">Select type</option>
                            @foreach($businessTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('business_type', $merchant?->business_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('business_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="gst_number" class="form-label">GST Number</label>
                        <input id="gst_number" name="gst_number" type="text" value="{{ old('gst_number', $merchant?->gst_number) }}" class="form-control @error('gst_number') is-invalid @enderror">
                        @error('gst_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @php
                        $profileFlags = [
                            'has_shop_license' => 'Do you have Shop Establishment Licence?',
                            'has_fssai' => 'Do you have FSSAI Licence?',
                        ];

                        $profileFlagEmptyLabels = [
                            'has_shop_license' => 'Not answered',
                            'has_fssai' => 'Not Applicable',
                        ];
                    @endphp

                    @foreach($profileFlags as $field => $label)
                        <div class="col-md-6">
                            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                            <select id="{{ $field }}" name="{{ $field }}" class="form-select @error($field) is-invalid @enderror">
                                @php
                                    $selectedFlag = old($field, $merchant?->{$field});
                                    $selectedFlagString = $selectedFlag === null || $selectedFlag === '' ? '' : (string) (int) (bool) $selectedFlag;
                                @endphp
                                <option value="" @selected($selectedFlag === null || $selectedFlag === '')>{{ $profileFlagEmptyLabels[$field] ?? 'Not answered' }}</option>
                                <option value="1" @selected($selectedFlagString === '1')>Yes</option>
                                <option value="0" @selected($selectedFlagString === '0')>No</option>
                            </select>
                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Account Status</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($accountStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $merchant?->status ?? 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="verification_status" class="form-label">Verification Status <span class="text-danger">*</span></label>
                        <select id="verification_status" name="verification_status" class="form-select @error('verification_status') is-invalid @enderror" required>
                            @foreach($verificationStatuses as $value => $label)
                                <option value="{{ $value }}" @selected($selectedVerificationStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('verification_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 {{ $selectedVerificationStatus === 'rejected' ? '' : 'd-none' }}" id="rejection_reason_group">
                        <label for="rejection_reason" class="form-label">Rejection Reason</label>
                        <input id="rejection_reason" name="rejection_reason" type="text" value="{{ old('rejection_reason', $merchant?->rejection_reason) }}" class="form-control @error('rejection_reason') is-invalid @enderror" @disabled($selectedVerificationStatus !== 'rejected')>
                        @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="admin_note" class="form-label">Admin Note</label>
                        <textarea id="admin_note" name="admin_note" rows="4" class="form-control @error('admin_note') is-invalid @enderror">{{ old('admin_note', $merchant?->admin_note) }}</textarea>
                        @error('admin_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Merchant' : 'Create Merchant'"
    :cancel="$isEdit ? route('admin.merchants.show', $merchant) : route('admin.merchants.index')"
    :cancel-label="$isEdit ? 'Back to Overview' : 'Back to Merchants'"
/>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const verificationStatus = document.getElementById('verification_status');
            const rejectionGroup = document.getElementById('rejection_reason_group');
            const rejectionReason = document.getElementById('rejection_reason');

            if (!verificationStatus || !rejectionGroup || !rejectionReason) {
                return;
            }

            const toggleRejectionReason = function () {
                const isRejected = verificationStatus.value === 'rejected';

                rejectionGroup.classList.toggle('d-none', !isRejected);
                rejectionReason.disabled = !isRejected;
            };

            verificationStatus.addEventListener('change', toggleRejectionReason);
            toggleRejectionReason();
        });
    </script>
@endpush
