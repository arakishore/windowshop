{{-- Purpose: Merchant change-password form for the authenticated user's account. --}}
@extends('layouts.merchant')

@section('title', 'Change Password | WindowShop')

@section('page_title', 'Change Password')

@section('content')
    <div class="row">
        <div class="col-xl-6">
            <form method="POST" action="{{ route('merchant.password.update') }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Password Security</h5>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password <span class="text-danger">*</span></label>
                            <input id="current_password" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                            <div class="form-text">Enter the password you currently use to sign in.</div>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New password <span class="text-danger">*</span></label>
                            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>
                            <div class="form-text">Use at least 8 characters. Choose a password different from your current one.</div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm new password <span class="text-danger">*</span></label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                            <div class="form-text">Re-enter the new password exactly as typed above.</div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph-key me-2"></i>
                                Change Password
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
