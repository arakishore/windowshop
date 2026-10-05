{{-- Purpose: Edits a marketing testimonial. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Testimonial"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Testimonials' => route('admin.testimonials.index'), 'Edit' => null]"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.testimonials.update', $testimonial) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.testimonials._form')
    </form>
@endsection
