{{-- Purpose: Edits a merchant product collection for the active shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Collection"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Collections' => route('merchant.collections.index'), $collection->name => null]"
        :action-url="route('merchant.collections.products', $collection)"
        action-label="Manage Products"
        action-icon="ph-package"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.collections.update', $collection) }}">
        @method('PUT')
        @include('merchant.collections.partials.form')
    </form>
@endsection
