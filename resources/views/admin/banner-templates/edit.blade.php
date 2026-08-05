{{-- Purpose: Edits reusable banner templates for the admin banner library. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="Edit Banner Template" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Banner Templates' => route('admin.banner-templates.index'), $template->name => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.banner-templates.update', $template) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.banner-templates.partials.form')
    </form>
@endsection
