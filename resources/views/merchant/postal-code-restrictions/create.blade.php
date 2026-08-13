@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header title="Create Postal Code Restriction" :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Postal Code Restrictions' => route('merchant.postal-code-restrictions.index'), 'Create' => null]" />
@endsection

@section('content')
    <div class="alert alert-info"><span class="fw-semibold">Shop:</span> {{ app(\App\Services\Merchant\MerchantShopContextService::class)->label($shop) }}</div>
    <form method="POST" action="{{ route('merchant.postal-code-restrictions.store') }}">
        @csrf
        @include('merchant.postal-code-restrictions._form')
    </form>
@endsection
