{{-- Purpose: Create a customer address. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Add Customer Address"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => route('merchant.customers.index'), $customer->name => route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']), 'Add Address' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.customers.addresses.store', $customer) }}">
        @csrf
        @include('merchant.customers.addresses.partials.form', ['submitLabel' => 'Save Address'])
    </form>
@endsection
