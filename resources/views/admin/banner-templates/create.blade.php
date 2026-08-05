{{-- Purpose: Creates reusable banner templates for the admin banner library. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="Create Banner Template" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Banner Templates' => route('admin.banner-templates.index'), 'Create' => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.banner-templates.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.banner-templates.partials.form')
    </form>
@endsection
