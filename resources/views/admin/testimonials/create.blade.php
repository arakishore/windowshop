{{-- Purpose: Creates a marketing testimonial. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Create Testimonial"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Testimonials' => route('admin.testimonials.index'), 'Create' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.testimonials.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.testimonials._form')
    </form>
@endsection
