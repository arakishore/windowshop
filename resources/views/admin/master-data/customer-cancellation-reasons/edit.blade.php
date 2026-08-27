@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Customer Cancellation Reason"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Customer Cancellation Reasons' => route('admin.master.customer-cancellation-reasons.index'), $reason->name => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.customer-cancellation-reasons.update', $reason) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.customer-cancellation-reasons._form')
    </form>
@endsection
