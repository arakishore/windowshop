{{-- Purpose: Creates product attribute groups for reusable product metadata. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Product Attribute"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Attributes' => route('admin.master.product-attributes.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-attributes.store') }}">
        @csrf
        @include('admin.master-data.product-attributes._form')
    </form>
@endsection
