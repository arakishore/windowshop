@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Tax Class"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), $taxClass->name => null]"
        :action-url="route('admin.master.tax-classes.show', $taxClass)"
        action-label="View Rates"
        action-icon="ph-eye"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.tax-classes.update', $taxClass) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.tax-classes._form')
    </form>
@endsection
