{{-- Purpose: Edits a merchant offer configuration. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Offer"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Offers' => route('merchant.promotions.index'), $promotion->name => null]"
        :action-url="route('merchant.promotions.index')"
        action-label="Back to Offers"
        action-icon="ph-arrow-left"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.promotions.update', $promotion) }}">
        @csrf
        @method('PUT')
        @include('merchant.promotions.partials.form')
    </form>
@endsection
