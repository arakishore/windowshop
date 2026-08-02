{{-- Purpose: Lists soft-deleted merchant cancellation reasons. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Cancellation Reasons Trash"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Cancellation Reasons' => route('merchant.cancellation-reasons.index'), 'Trash' => null]"
    />
@endsection

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h3 class="mb-1">Cancellation Reasons Trash</h3>
            <div class="text-muted">Soft-deleted cancellation reasons can be restored here.</div>
        </div>
        <a href="{{ route('merchant.cancellation-reasons.index') }}" class="btn btn-light">
            <i class="ph-arrow-left me-2"></i>
            Back to reasons
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('merchant.cancellation-reasons.trash') }}" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, code, or description">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="ph-magnifying-glass me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('merchant.cancellation-reasons.trash') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Deleted Reasons ({{ $reasons->count() }})</h5>
        </div>
        @if($reasons->isEmpty())
            <x-empty-state icon="ph-trash" title="Trash is empty" message="Deleted cancellation reasons will appear here." />
        @else
            <div class="table-responsive datatable-wrapper border-top">
                <table id="cancellation-reasons-trash-table" class="table datatable-basic table-bordered table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Sort Order</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reasons as $reason)
                            <tr>
                                <td class="fw-semibold">{{ $reason->name }}</td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $reason->code }}</span></td>
                                <td class="text-muted small">{{ $reason->description ?: '-' }}</td>
                                <td><span class="badge bg-danger">Trash</span></td>
                                <td class="text-end">{{ $reason->sort_order }}</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('merchant.cancellation-reasons.restore', $reason->getRouteKey()) }}" class="d-inline js-restore-cancellation-reason-form">
                                        @csrf
                                        <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-restore-cancellation-reason" data-bs-popup="tooltip" title="Restore reason">
                                            <i class="ph-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('vendor_scripts')
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/extensions/responsive.min.js') }}"></script>
@endpush

@push('scripts')
    @include('merchant.cancellation-reasons.partials.confirm-script', ['tableId' => 'cancellation-reasons-trash-table'])
@endpush
