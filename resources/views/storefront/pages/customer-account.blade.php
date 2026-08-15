@extends('storefront.layouts.app')

@section('title', 'My Account | WindowShop')
@section('meta_description', 'WindowShop customer account overview.')

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">My Account</p>
                </div>
                <h3>My Account</h3>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="text-center">
                <h4 class="mb-12">Welcome, {{ $customer->name }}</h4>
                <p class="cl-text-2 mb-0">Your customer account area will be expanded in a later step.</p>
            </div>
        </div>
    </section>
@endsection
