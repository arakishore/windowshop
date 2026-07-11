{{-- Purpose: Creates brand master records for future product assignment. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Brand"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Brands' => route('admin.master.brands.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.brands.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.master-data.brands._form')
    </form>
@endsection
