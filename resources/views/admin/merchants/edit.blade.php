{{-- Purpose: Edits merchant owner account and profile details from the admin panel. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Merchant"
        subtitle="{{ $merchant->business_name }}"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => route('admin.merchants.show', $merchant), 'Edit' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.merchants.update', $merchant) }}">
        @csrf
        @method('PUT')
        @include('admin.merchants._form')
    </form>
@endsection
