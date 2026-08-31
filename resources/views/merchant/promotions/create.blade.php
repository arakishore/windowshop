{{-- Purpose: Creates a merchant offer from a global promotion template. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Create Offer"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Offers' => route('merchant.promotions.index'), 'Create Offer' => null]"
        :action-url="route('merchant.promotions.index')"
        action-label="Back to Offers"
        action-icon="ph-arrow-left"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.promotions.store') }}">
        @csrf
        @include('merchant.promotions.partials.form')
    </form>
@endsection
