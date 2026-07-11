{{-- Purpose: Provides the shared merchant management workspace shell. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Manage Merchant"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => null]"
        :action-url="route('admin.merchants.index')"
        action-label="Back to Merchants"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @include('admin.merchants.partials.management-header')
    @include('admin.merchants.partials.management-tabs')

    @include("admin.merchants.tabs.$activeTab")
@endsection
