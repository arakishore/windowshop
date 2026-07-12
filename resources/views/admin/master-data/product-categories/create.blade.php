{{-- Purpose: Creates product category master records for admin shop categorization. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Product Category"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Categories' => route('admin.master.product-categories.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-categories.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.master-data.product-categories._form')
    </form>
@endsection
