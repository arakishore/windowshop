{{-- Purpose: Creates a marketing FAQ. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create FAQ"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'FAQs' => route('admin.faqs.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.faqs.store') }}">
        @csrf
        @include('admin.faqs._form')
    </form>
@endsection
