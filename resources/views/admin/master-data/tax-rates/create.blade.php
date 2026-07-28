@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Tax Rate"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), $taxClass->name => route('admin.master.tax-classes.show', $taxClass), 'Create Rate' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.tax-classes.rates.store', $taxClass) }}">
        @csrf
        @include('admin.master-data.tax-rates._form')
    </form>
@endsection
