{{-- Purpose: Merchant/company details form without shop or branch information. --}}
@extends('layouts.merchant')

@section('title', 'Merchant Details | WindowShop')

@section('page_title', 'Merchant Details')

@section('content')
    @php
        $selectedBusinessType = old('business_type', $merchant->business_type);
        $shopLicenseValue = old('has_shop_license', $merchant->has_shop_license);
        $fssaiValue = old('has_fssai', $merchant->has_fssai);
        $shopLicenseString = $shopLicenseValue === null || $shopLicenseValue === '' ? '' : (string) (int) (bool) $shopLicenseValue;
        $fssaiString = $fssaiValue === null || $fssaiValue === '' ? '' : (string) (int) (bool) $fssaiValue;
        $businessTypeLabel = $merchant->business_type ? ($businessTypes[$merchant->business_type] ?? Str::headline($merchant->business_type)) : 'Not provided';
        $shopLicenseLabel = $merchant->has_shop_license === null ? 'Not answered' : ($merchant->has_shop_license ? 'Yes' : 'No');
        $fssaiLabel = $merchant->has_fssai === null ? 'Not Applicable' : ($merchant->has_fssai ? 'Yes' : 'No');
    @endphp

    @if ($lockedAfterVerification)
        <div class="alert alert-info">
            Legal and registration details are locked after verification. Contact support if these details need correction.
        </div>
    @endif

    <form method="POST" action="{{ route('merchant.details.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Merchant Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                            <input id="business_name" type="text" name="business_name" value="{{ old('business_name', $merchant->business_name) }}" class="form-control @error('business_name') is-invalid @enderror" required>
                            @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" @unless($lockedAfterVerification) for="legal_name" @endunless>Legal Name</label>
                            @if ($lockedAfterVerification)
                                <div class="fw-semibold">{{ $merchant->legal_name ?: 'Not provided' }}</div>
                            @else
                                <input id="legal_name" type="text" name="legal_name" value="{{ old('legal_name', $merchant->legal_name) }}" class="form-control @error('legal_name') is-invalid @enderror">
                                @error('legal_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <div class="mb-0">
                            <label class="form-label" @unless($lockedAfterVerification) for="business_type" @endunless>Business Type</label>
                            @if ($lockedAfterVerification)
                                <div class="fw-semibold">{{ $businessTypeLabel }}</div>
                            @else
                                <select id="business_type" name="business_type" class="form-select @error('business_type') is-invalid @enderror">
                                    <option value="">Select type</option>
                                    @foreach ($businessTypes as $value => $label)
                                        <option value="{{ $value }}" @selected($selectedBusinessType === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('business_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Business Contact</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="contact_person_name" class="form-label">Contact Person</label>
                            <input id="contact_person_name" type="text" name="contact_person_name" value="{{ old('contact_person_name', $merchant->contact_person_name) }}" class="form-control @error('contact_person_name') is-invalid @enderror">
                            @error('contact_person_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $merchant->contact_email) }}" class="form-control @error('contact_email') is-invalid @enderror">
                            @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="contact_mobile" class="form-label">Contact Mobile</label>
                            <input id="contact_mobile" type="text" name="contact_mobile" value="{{ old('contact_mobile', $merchant->contact_mobile) }}" class="form-control @error('contact_mobile') is-invalid @enderror">
                            @error('contact_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Business Registration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" @unless($lockedAfterVerification) for="gst_number" @endunless>GST Number</label>
                            @if ($lockedAfterVerification)
                                <div class="fw-semibold">{{ $merchant->gst_number ?: 'Not provided' }}</div>
                            @else
                                <input id="gst_number" type="text" name="gst_number" value="{{ old('gst_number', $merchant->gst_number) }}" class="form-control text-uppercase @error('gst_number') is-invalid @enderror">
                                @error('gst_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" @unless($lockedAfterVerification) for="has_shop_license" @endunless>Shop Licence</label>
                            @if ($lockedAfterVerification)
                                <div class="fw-semibold">{{ $shopLicenseLabel }}</div>
                            @else
                                <select id="has_shop_license" name="has_shop_license" class="form-select @error('has_shop_license') is-invalid @enderror">
                                    <option value="" @selected($shopLicenseString === '')>Not answered</option>
                                    <option value="1" @selected($shopLicenseString === '1')>Yes</option>
                                    <option value="0" @selected($shopLicenseString === '0')>No</option>
                                </select>
                                @error('has_shop_license')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <div class="mb-0">
                            <label class="form-label" @unless($lockedAfterVerification) for="has_fssai" @endunless>FSSAI</label>
                            @if ($lockedAfterVerification)
                                <div class="fw-semibold">{{ $fssaiLabel }}</div>
                            @else
                                <select id="has_fssai" name="has_fssai" class="form-select @error('has_fssai') is-invalid @enderror">
                                    <option value="" @selected($fssaiString === '')>Not Applicable</option>
                                    <option value="1" @selected($fssaiString === '1')>Yes</option>
                                    <option value="0" @selected($fssaiString === '0')>No</option>
                                </select>
                                @error('has_fssai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Merchant ID</label>
                                <div class="fw-semibold text-break">{{ $merchant->uuid }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Merchant Status</label>
                                <div><span class="badge bg-success bg-opacity-10 text-success">{{ Str::headline($merchant->status) }}</span></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Verification Status</label>
                                <div><span class="badge bg-info bg-opacity-10 text-info">{{ \App\Enums\MerchantVerificationStatus::badgeLabelFor($merchant->verification_status) }}</span></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Created Date</label>
                                <div class="fw-semibold">{{ $merchant->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Approved Date</label>
                                <div class="fw-semibold">{{ $merchant->verified_at?->format('d M Y, h:i A') ?? 'Not approved' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">
                <i class="ph-floppy-disk me-2"></i>
                Save Merchant Details
            </button>
        </div>
    </form>
@endsection
