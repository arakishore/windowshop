{{-- Purpose: Edits shop category master records used by shop profiles. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Shop Category"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Shop Categories' => route('admin.master.shop-categories.index'), $category->name => null]"
        :action-url="route('admin.master.shop-categories.index')"
        action-label="Back to Categories"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.shop-categories.update', $category) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.master-data.shop-categories._form')
    </form>
@endsection
