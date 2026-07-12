{{-- Purpose: Merchant account profile form limited to user-owned editable fields. --}}
@extends('layouts.merchant')

@section('title', 'My Profile | WindowShop')

@section('page_title', 'My Profile')

@section('content')
    <form method="POST" action="{{ route('merchant.profile.update') }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Account Details</h5>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
                            <input id="mobile" type="text" name="mobile" value="{{ old('mobile', $user->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" required>
                            @error('mobile')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph-floppy-disk me-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Merchant Details</h5>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" value="{{ $merchant->business_name }}" class="form-control" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <div>
                                <span class="badge bg-success bg-opacity-10 text-success">{{ Str::headline($merchant->status) }}</span>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Verification Status</label>
                            <div>
                                <span class="badge bg-info bg-opacity-10 text-info">{{ \App\Enums\MerchantVerificationStatus::badgeLabelFor($merchant->verification_status) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
