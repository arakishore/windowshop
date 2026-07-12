{{-- Purpose: Edits reusable values for a product attribute group. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit {{ $group->name }} Value"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Attributes' => route('admin.master.product-attributes.index'), $group->name => route('admin.master.product-attributes.values.index', $group), $value->name => null]"
        :action-url="route('admin.master.product-attributes.values.index', $group)"
        action-label="Back to Values"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-attributes.values.update', [$group, $value]) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.product-attribute-group-values._form')
    </form>
@endsection
