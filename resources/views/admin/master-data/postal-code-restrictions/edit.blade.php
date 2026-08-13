@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="Edit Postal Code Restriction" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Code Restrictions' => route('admin.master.postal-code-restrictions.index'), $restriction->postal_code => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.postal-code-restrictions.update', $restriction) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.postal-code-restrictions._form')
    </form>
@endsection
