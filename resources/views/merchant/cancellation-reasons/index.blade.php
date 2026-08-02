{{-- Purpose: Lists merchant cancellation reason master data. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Cancellation Reasons"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Cancellation Reasons' => null]"
    />
@endsection

@section('content')
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h3 class="mb-1">Cancellation Reasons</h3>
            <div class="text-muted">Merchant-level reasons available across all shops for future cancellation workflows.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('merchant.cancellation-reasons.trash') }}" class="btn btn-light">
                <i class="ph-trash me-2"></i>
                Trash
            </a>
            <a href="{{ route('merchant.cancellation-reasons.create') }}" class="btn btn-primary">
                <i class="ph-plus me-2"></i>
                New reason
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('merchant.cancellation-reasons.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, code, or description">
                </div>
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Status</label>
                    <select id="status_filter" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="ph-magnifying-glass me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('merchant.cancellation-reasons.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Reasons ({{ $reasons->count() }})</h5>
        </div>
        @if($reasons->isEmpty())
            <x-empty-state icon="ph-x-circle" title="No cancellation reasons found" message="Create a reason or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper border-top">
                <table id="cancellation-reasons-table" class="table datatable-basic table-bordered table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th class="text-center">Customer</th>
                            <th class="text-center">Merchant</th>
                            <th class="text-center">Comment</th>
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
                                <td class="text-center">
                                    <span class="badge {{ $reason->customer_selectable ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ $reason->customer_selectable ? 'Yes' : 'No' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $reason->merchant_selectable ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ $reason->merchant_selectable ? 'Yes' : 'No' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $reason->requires_comment ? 'bg-warning bg-opacity-10 text-warning' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ $reason->requires_comment ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $reason->status === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ Str::headline($reason->status) }}</span>
                                </td>
                                <td class="text-end">{{ $reason->sort_order }}</td>
                                <td class="text-center">
                                    <a href="{{ route('merchant.cancellation-reasons.edit', $reason) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit reason">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                    <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 ms-2 js-delete-cancellation-reason" data-form-id="delete-cancellation-reason-{{ $reason->getKey() }}" data-bs-popup="tooltip" title="Delete reason">
                                        <i class="ph-trash"></i>
                                    </button>
                                    <form id="delete-cancellation-reason-{{ $reason->getKey() }}" method="POST" action="{{ route('merchant.cancellation-reasons.destroy', $reason) }}" class="d-none">
                                        @csrf
                                        @method('DELETE')
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
    @include('merchant.cancellation-reasons.partials.confirm-script', ['tableId' => 'cancellation-reasons-table'])
@endpush
