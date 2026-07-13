{{-- Purpose: Provides the admin quick-create product flow before detailed tab editing. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Add Product"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Products' => route('admin.products.index'), 'Add Product' => null]"
        :action-url="route('admin.products.index')"
        action-label="Back to Products"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.products.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Minimum Product Details</h5>
            </div>
            @include('admin.products.partials.quick-form', ['submitLabel' => 'Create and Continue'])
        </div>
    </form>
@endsection
