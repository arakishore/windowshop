@extends('storefront.layouts.app')

@section('title', $page->title . ' | ' . $shop->name . ' | ' . $marketplaceName)
@section('meta_description', $page->title . ' from ' . $shop->name . ' on ' . $marketplaceName . '.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-profile.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-cms-page.css') }}">
@endpush

@section('content')
    <main class="shop-profile-page shop-cms-page">
        <div class="container shop-profile-shell">
            <nav class="shop-profile-breadcrumbs" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}">Home</a>
                <span>/</span>
                <a href="{{ route('storefront.stores.show', $shop->slug) }}">{{ $shop->name }}</a>
                <span>/</span>
                <span>{{ $page->title }}</span>
            </nav>

            @include('storefront.partials.shop-hero')
            @include('storefront.partials.shop-navigation')

            <article class="shop-cms-article">
                <h1>{{ $page->title }}</h1>
                <div class="shop-cms-content">{!! app(\App\Services\Merchant\ShopPageContent::class)->render($page->body) !!}</div>
            </article>

            @include('storefront.partials.shop-mini-footer')
        </div>
    </main>
@endsection
