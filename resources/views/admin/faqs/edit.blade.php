{{-- Purpose: Edits a marketing FAQ. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit FAQ"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'FAQs' => route('admin.faqs.index'), 'Edit' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.faqs.update', $faq) }}">
        @csrf
        @method('PUT')
        @include('admin.faqs._form')
    </form>
@endsection
