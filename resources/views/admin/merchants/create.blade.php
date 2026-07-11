{{-- Purpose: Creates a merchant owner account and merchant profile from the admin panel. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Merchant"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.merchants.store') }}">
        @csrf
        @include('admin.merchants._form')
    </form>
@endsection
