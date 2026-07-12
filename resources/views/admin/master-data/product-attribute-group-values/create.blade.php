{{-- Purpose: Creates reusable values for a product attribute group. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create {{ $group->name }} Value"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Attributes' => route('admin.master.product-attributes.index'), $group->name => route('admin.master.product-attributes.values.index', $group), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-attributes.values.store', $group) }}">
        @csrf
        @include('admin.master-data.product-attribute-group-values._form')
    </form>
@endsection
