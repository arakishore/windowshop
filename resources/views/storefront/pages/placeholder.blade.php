@extends('storefront.layouts.app')

@section('title', $pageTitle.' | WindowShop')
@section('meta_description', $pageDescription)

@section('content')
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-title text-center">
                <h1 class="s-title mb-8">{{ $pageTitle }}</h1>
                <p class="s-subtitle h6">{{ $pageDescription }}</p>
            </div>
        </div>
    </section>
@endsection
