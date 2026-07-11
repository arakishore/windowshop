{{-- Purpose: Edits a merchant-scoped shop. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Shop"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => route('admin.merchants.show', $merchant), 'Shops' => route('admin.merchants.shops.index', $merchant), $shop->name => null]"
        :action-url="route('admin.merchants.shops.index', $merchant)"
        action-label="Back to Shops"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @include('admin.merchants.partials.management-header')
    @include('admin.merchants.partials.management-tabs')

    <form method="POST" action="{{ route('admin.merchants.shops.update', [$merchant, $shop]) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.merchants.shops._form')
    </form>
@endsection
