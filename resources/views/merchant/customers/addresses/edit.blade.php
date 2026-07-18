{{-- Purpose: Edit a customer address. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Customer Address"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => route('merchant.customers.index'), $customer->name => route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']), 'Edit Address' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.customers.addresses.update', [$customer, $address]) }}">
        @csrf
        @method('PUT')
        @include('merchant.customers.addresses.partials.form', ['submitLabel' => 'Save Address'])
    </form>
@endsection
