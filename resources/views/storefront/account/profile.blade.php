@extends('storefront.layouts.app')

@section('title', 'Profile | WindowShop')
@section('meta_description', 'Review your WindowShop customer profile.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Profile'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Profile</p>
            <h4 class="mb-10">Your Details</h4>
            <p class="cl-text-2 mb-0">Update your account name. Mobile and email changes will be handled through verification later.</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-24">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('storefront.account.profile.update') }}" class="account-profile-form">
            @csrf
            @method('PUT')

            <div class="account-form-grid">
                <div class="account-form-field is-wide">
                    <label for="name">Name <span class="text-primary">*</span></label>
                    <input id="name" name="name" type="text" maxlength="150"
                        value="{{ old('name', $globalCustomer?->name ?? $customer->name) }}" required>
                    @error('name')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="mobile">Mobile</label>
                    <input id="mobile" type="text" value="{{ $globalCustomer?->mobile ?? $customer->mobile ?? '-' }}" readonly>
                </div>

                <div class="account-form-field">
                    <label for="email">Email</label>
                    <input id="email" type="text" value="{{ $globalCustomer?->email ?? $customer->email ?? '-' }}" readonly>
                </div>
            </div>

            <div class="account-address-actions">
                <button type="submit" class="account-primary-button">Save Profile</button>
            </div>
        </form>
    @endcomponent
@endsection
