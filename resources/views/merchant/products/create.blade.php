{{-- Purpose: Provides merchant product quick-create flow. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Add Product"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Products' => route('merchant.products.index'), 'Add Product' => null]"
        :action-url="route('merchant.products.index')"
        action-label="Back to Products"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.products.store') }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Minimum Product Details</h5>
            </div>
            @include('admin.products.partials.quick-form', ['submitLabel' => 'Create and Continue', 'productRoutePrefix' => 'merchant', 'allowCreateStatusSelection' => true])
        </div>
    </form>
@endsection
