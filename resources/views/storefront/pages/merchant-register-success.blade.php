@extends('storefront.layouts.app')

@section('title', 'Merchant Account Created | '.$marketplaceName)

@section('content')
    <section class="flat-spacing bg-light">
        <div class="container">
            <div class="mx-auto bg-white rounded-3 shadow-sm p-4 p-md-5 text-center" style="max-width:720px">
                <div class="mb-3 text-success"><i class="icon icon-checkCircle" style="font-size:52px"></i></div>
                <h1>Your merchant account has been created</h1>
                <p class="text-muted mt-3 mb-4">You can now prepare your shop while merchant verification is pending. Your shop and products will remain private until the account is approved and normal publication requirements are met.</p>
                <a href="{{ route('merchant.profile.edit') }}" class="tf-btn animate-btn">Go to Merchant Panel</a>
            </div>
        </div>
    </section>
@endsection
