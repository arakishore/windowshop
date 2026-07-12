{{-- Purpose: Creates category-based product description templates. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Description Template"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Description Templates' => route('admin.master.description-templates.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.description-templates.store') }}">
        @csrf
        @include('admin.master-data.description-templates._form')
    </form>
@endsection
