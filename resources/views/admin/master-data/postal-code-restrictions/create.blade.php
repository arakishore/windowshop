@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="Create Postal Code Restriction" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Code Restrictions' => route('admin.master.postal-code-restrictions.index'), 'Create' => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.postal-code-restrictions.store') }}">
        @csrf
        @include('admin.master-data.postal-code-restrictions._form')
    </form>
@endsection
