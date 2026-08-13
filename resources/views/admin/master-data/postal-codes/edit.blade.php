@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Postal Code"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Codes' => route('admin.master.postal-codes.index'), $postalCode->postal_code => route('admin.master.postal-codes.show', $postalCode), 'Edit' => null]"
        :action-url="route('admin.master.postal-codes.index')"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.postal-codes.update', $postalCode) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.postal-codes._form')
    </form>
@endsection
