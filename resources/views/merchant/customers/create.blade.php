{{-- Purpose: Create a merchant-scoped customer. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Add Customer"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => route('merchant.customers.index'), 'Add Customer' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.customers.store') }}">
        @csrf
        @include('merchant.customers.partials.form', ['submitLabel' => 'Save Customer'])
    </form>
@endsection
