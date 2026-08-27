@extends('storefront.layouts.app')

@section('title', 'My Account | WindowShop')
@section('meta_description', 'WindowShop customer account overview.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'My Account'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Dashboard</p>
            <h4 class="mb-10">Welcome back, {{ $customer->name }}</h4>
            <p class="cl-text-2 mb-0">Manage your orders, profile and saved addresses.</p>
        </div>

        <div class="account-card-grid mb-24">
            <div class="account-stat-card">
                <p class="text-caption-01 cl-text-3 mb-6">Total Orders</p>
                <h4 class="mb-0">{{ $orderCount }}</h4>
            </div>
            <div class="account-stat-card">
                <p class="text-caption-01 cl-text-3 mb-6">Saved Addresses</p>
                <h4 class="mb-0">{{ $addressCount }}</h4>
            </div>
        </div>

        <div class="account-card-grid">
            <a href="{{ route('storefront.account.orders') }}" class="account-action-card">
                <span class="account-card-icon"><i class="icon icon-Package"></i></span>
                <h6 class="mb-6">My Orders</h6>
                <p class="cl-text-2 mb-0">Track and view orders.</p>
            </a>
            <a href="{{ route('storefront.account.addresses') }}" class="account-action-card">
                <span class="account-card-icon"><i class="icon icon-Truck"></i></span>
                <h6 class="mb-6">Addresses</h6>
                <p class="cl-text-2 mb-0">Manage saved delivery and billing addresses.</p>
            </a>
            <a href="{{ route('storefront.account.profile') }}" class="account-action-card">
                <span class="account-card-icon"><i class="icon icon-User"></i></span>
                <h6 class="mb-6">Profile</h6>
                <p class="cl-text-2 mb-0">Review your account details.</p>
            </a>
            <a href="{{ route('storefront.account.wishlist') }}" class="account-action-card">
                <span class="account-card-icon"><i class="icon icon-HeartStraight"></i></span>
                <h6 class="mb-6">Wishlist</h6>
                <p class="cl-text-2 mb-0">Saved favourites will appear here.</p>
            </a>
        </div>
    @endcomponent
@endsection
