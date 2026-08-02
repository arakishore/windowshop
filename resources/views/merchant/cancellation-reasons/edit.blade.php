{{-- Purpose: Edits merchant cancellation reason master data. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Cancellation Reason"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Cancellation Reasons' => route('merchant.cancellation-reasons.index'), $reason->name => null]"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-0">{{ $reason->name }}</h5>
                <div class="text-muted small">Code {{ $reason->code }}</div>
            </div>
            <button type="button" class="btn btn-outline-danger js-delete-cancellation-reason" data-form-id="delete-cancellation-reason-form">
                <i class="ph-trash me-2"></i>
                Delete
            </button>
        </div>
        <form method="POST" action="{{ route('merchant.cancellation-reasons.update', $reason) }}">
            @csrf
            @method('PUT')
            @include('merchant.cancellation-reasons._form', ['reason' => $reason, 'isEdit' => true])
        </form>
        <form id="delete-cancellation-reason-form" method="POST" action="{{ route('merchant.cancellation-reasons.destroy', $reason) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
@endsection

@push('scripts')
    @include('merchant.cancellation-reasons.partials.confirm-script')
@endpush
