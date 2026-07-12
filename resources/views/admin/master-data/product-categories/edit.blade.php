{{-- Purpose: Edits product category master records used by shop profiles. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Product Category"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Categories' => route('admin.master.product-categories.index'), $category->name => null]"
        :action-url="route('admin.master.product-categories.index')"
        action-label="Back to Categories"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.product-categories.update', $category) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.master-data.product-categories._form')
    </form>
@endsection
