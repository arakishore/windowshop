{{-- Purpose: Creates a merchant product collection for the active shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Create Collection"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Collections' => route('merchant.collections.index'), 'Create' => null]"
        :action-url="route('merchant.collections.index')"
        action-label="Back to Collections"
        action-icon="ph-arrow-left"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('merchant.collections.store') }}">
        @include('merchant.collections.partials.form')
    </form>
@endsection
