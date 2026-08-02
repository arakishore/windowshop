{{-- Purpose: Creates merchant cancellation reason master data. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="New Cancellation Reason"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Cancellation Reasons' => route('merchant.cancellation-reasons.index'), 'New' => null]"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">New Cancellation Reason</h5>
        </div>
        <form method="POST" action="{{ route('merchant.cancellation-reasons.store') }}">
            @csrf
            @include('merchant.cancellation-reasons._form', ['reason' => $reason, 'isEdit' => false])
        </form>
    </div>
@endsection
