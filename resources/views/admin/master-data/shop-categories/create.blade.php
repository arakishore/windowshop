{{-- Purpose: Creates shop category master records for admin shop categorization. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Shop Category"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Shop Categories' => route('admin.master.shop-categories.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.shop-categories.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.master-data.shop-categories._form')
    </form>
@endsection
