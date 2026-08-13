@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header title="Edit Postal Code Restriction" :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Postal Code Restrictions' => route('merchant.postal-code-restrictions.index'), $restriction->postal_code => null]" />
@endsection

@section('content')
    <div class="alert alert-info"><span class="fw-semibold">Shop:</span> {{ app(\App\Services\Merchant\MerchantShopContextService::class)->label($shop) }}</div>
    @if($globalWarning)
        <div class="alert alert-warning">{{ $globalWarning }}</div>
    @endif
    <form method="POST" action="{{ route('merchant.postal-code-restrictions.update', $restriction) }}">
        @csrf
        @method('PUT')
        @include('merchant.postal-code-restrictions._form')
    </form>
@endsection
