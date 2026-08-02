@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Order Status"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Order Statuses' => route('admin.master.order-statuses.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.order-statuses.store') }}">
        @csrf
        @include('admin.master-data.order-statuses._form')
    </form>
@endsection
