{{-- Purpose: Edits shop audience master records used by shop profiles. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Shop Audience"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Shop Audiences' => route('admin.master.shop-audiences.index'), $audience->name => null]"
        :action-url="route('admin.master.shop-audiences.index')"
        action-label="Back to Audiences"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.shop-audiences.update', $audience) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.shop-audiences._form')
    </form>
@endsection
