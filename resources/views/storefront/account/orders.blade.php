@extends('storefront.layouts.app')

@section('title', 'My Orders | WindowShop')
@section('meta_description', 'WindowShop customer order area.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">My Orders</p>
            <h4 class="mb-10">My Orders</h4>
            <p class="cl-text-2 mb-0">My Orders will be available here.</p>
        </div>
    @endcomponent
@endsection
