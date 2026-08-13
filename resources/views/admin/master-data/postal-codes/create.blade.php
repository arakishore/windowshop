@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Postal Code"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Codes' => route('admin.master.postal-codes.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.postal-codes.store') }}">
        @csrf
        @include('admin.master-data.postal-codes._form')
    </form>
@endsection
