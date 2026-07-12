{{-- Purpose: Edits category-based product description templates. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Description Template"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Description Templates' => route('admin.master.description-templates.index'), $template->name => null]"
        :action-url="route('admin.master.description-templates.index')"
        action-label="Back to Templates"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.description-templates.update', $template) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.description-templates._form')
    </form>
@endsection
