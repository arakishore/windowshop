{{-- Purpose: Edits brand master records used by future products. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Brand"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Brands' => route('admin.master.brands.index'), $brand->name => null]"
        :action-url="route('admin.master.brands.index')"
        action-label="Back to Brands"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.brands.update', $brand) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.master-data.brands._form')
    </form>
@endsection
