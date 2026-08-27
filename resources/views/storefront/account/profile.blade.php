@extends('storefront.layouts.app')

@section('title', 'Profile | WindowShop')
@section('meta_description', 'Review your WindowShop customer profile.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Profile'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Profile</p>
            <h4 class="mb-10">Your Details</h4>
            <p class="cl-text-2 mb-0">Profile editing will be added separately after the account foundation is in place.</p>
        </div>

        <div class="account-profile-list">
            <div class="account-profile-row">
                <strong>Name</strong>
                <span>{{ $customer->name ?: '-' }}</span>
            </div>
            <div class="account-profile-row">
                <strong>Mobile</strong>
                <span>{{ $customer->mobile ?: '-' }}</span>
            </div>
            <div class="account-profile-row">
                <strong>Email</strong>
                <span>{{ $customer->email ?: '-' }}</span>
            </div>
        </div>
    @endcomponent
@endsection
