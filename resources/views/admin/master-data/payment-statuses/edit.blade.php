@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Payment Status"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Payment Statuses' => route('admin.master.payment-statuses.index'), $paymentStatus->name => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.payment-statuses.update', $paymentStatus) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.payment-statuses._form')
    </form>
@endsection
