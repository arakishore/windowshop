@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Tax Component"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), $taxRate->taxClass->name => route('admin.master.tax-classes.show', $taxRate->taxClass), $taxRate->name => route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate]), 'Create Component' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.tax-rates.components.store', $taxRate) }}">
        @csrf
        @include('admin.master-data.tax-rate-components._form')
    </form>
@endsection
