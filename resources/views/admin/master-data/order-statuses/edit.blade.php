@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Order Status"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Order Statuses' => route('admin.master.order-statuses.index'), $orderStatus->name => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.order-statuses.update', $orderStatus) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.order-statuses._form')
    </form>
@endsection
