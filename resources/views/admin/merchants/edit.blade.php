{{-- Purpose: Edits merchant owner account and profile details from the admin panel. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Merchant"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => route('admin.merchants.show', $merchant), 'Edit' => null]"
        :action-url="route('admin.merchants.show', $merchant)"
        action-label="Back to Overview"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @php($activeTab = 'profile')

    @include('admin.merchants.partials.management-header')
    @include('admin.merchants.partials.management-tabs')

    <form method="POST" action="{{ route('admin.merchants.update', $merchant) }}">
        @csrf
        @method('PUT')
        @include('admin.merchants._form')
    </form>
@endsection
