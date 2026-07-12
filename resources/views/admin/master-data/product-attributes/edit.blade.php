{{-- Purpose: Edits product attribute groups for reusable product metadata. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Product Attribute"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Attributes' => route('admin.master.product-attributes.index'), $group->name => null]"
        :action-url="route('admin.master.product-attributes.index')"
        action-label="Back to Attributes"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-attributes.update', $group) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.product-attributes._form')
    </form>
@endsection
