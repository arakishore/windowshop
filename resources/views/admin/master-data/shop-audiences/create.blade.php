{{-- Purpose: Creates shop audience master records for admin shop targeting. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Shop Audience"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Shop Audiences' => route('admin.master.shop-audiences.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.shop-audiences.store') }}">
        @csrf
        @include('admin.master-data.shop-audiences._form')
    </form>
@endsection
