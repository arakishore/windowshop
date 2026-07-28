@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Tax Class"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.tax-classes.store') }}">
        @csrf
        @include('admin.master-data.tax-classes._form')
    </form>
@endsection
