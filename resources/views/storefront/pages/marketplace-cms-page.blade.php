@extends('storefront.layouts.app')

@section('title', $page->title . ' | ' . $marketplaceName)
@section('meta_description', $page->title . ' for ' . $marketplaceName . '.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/shop-cms-page.css') }}">
@endpush

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">{{ $page->title }}</p>
                </div>
                <h3>{{ $page->title }}</h3>
            </div>
        </div>
    </section>

    <section class="section-term-user flat-spacing">
        <div class="container">
            <article class="content">
                <div class="shop-cms-content">
                    {!! $pageBody !!}
                </div>
            </article>
        </div>
    </section>
@endsection
