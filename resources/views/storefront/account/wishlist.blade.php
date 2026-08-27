@extends('storefront.layouts.app')

@section('title', 'Wishlist | WindowShop')
@section('meta_description', 'WindowShop customer wishlist area.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Wishlist'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Wishlist</p>
            <h4 class="mb-10">Wishlist</h4>
            <p class="cl-text-2 mb-0">Wishlist will be available here.</p>
        </div>

        <div class="account-empty-panel">
            Saved favourite products will appear here after wishlist functionality is added.
        </div>
    @endcomponent
@endsection
