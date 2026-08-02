@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Payment Status"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Payment Statuses' => route('admin.master.payment-statuses.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.payment-statuses.store') }}">
        @csrf
        @include('admin.master-data.payment-statuses._form')
    </form>
@endsection
