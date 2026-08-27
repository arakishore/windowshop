@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Customer Cancellation Reason"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Customer Cancellation Reasons' => route('admin.master.customer-cancellation-reasons.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.customer-cancellation-reasons.store') }}">
        @csrf
        @include('admin.master-data.customer-cancellation-reasons._form')
    </form>
@endsection
