{{-- Purpose: Edit a merchant-scoped customer. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Customer"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Customers' => route('merchant.customers.index'), $customer->name => route('merchant.customers.show', $customer), 'Edit' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.customers.update', $customer) }}">
        @csrf
        @method('PUT')
        @include('merchant.customers.partials.form', ['submitLabel' => 'Save Customer'])
    </form>
@endsection
