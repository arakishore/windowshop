{{-- Purpose: Manages merchant return reason master data. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Return reasons"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Return reasons' => null]"
    />
@endsection

@section('content')
    @php
        $editing = $selectedReason !== null;
    @endphp

    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h3 class="mb-1">Return reasons</h3>
            <div class="text-muted">The picklist cashiers choose from when ringing up a refund.</div>
        </div>
        <a href="{{ route('merchant.return-reasons.index', ['new' => 1]) }}" class="btn btn-primary">
            <i class="ph-plus me-2"></i>
            New reason
        </a>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="mb-0">Reasons ({{ $reasons->count() }})</h5>
                </div>
                @if($reasons->isEmpty())
                    <x-empty-state icon="ph-arrow-u-down-left" title="No return reasons found" message="Create a reason to make it available during refunds." />
                @else
                    <div class="table-responsive datatable-wrapper border-top">
                        <table id="return-reasons-table" class="table datatable-basic table-bordered table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-end">Sort Order</th>
                                    <th>Status</th>
                                    <th class="text-center">Restock</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reasons as $reason)
                                    <tr class="{{ $selectedReason?->is($reason) ? 'table-active' : '' }}">
                                        <td>
                                            <a href="{{ route('merchant.return-reasons.index', ['reason' => $reason->getRouteKey()]) }}" class="text-body fw-semibold">{{ $reason->name }}</a>
                                            @if($reason->restock_by_default)
                                                <span class="badge bg-light text-body ms-2">Restocks by default</span>
                                            @else
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2">Does NOT restock by default</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $reason->sort_order }}</td>
                                        <td>
                                            <span class="badge {{ $reason->status === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ Str::headline($reason->status) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $reason->restock_by_default ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">
                                                {{ $reason->restock_by_default ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('merchant.return-reasons.index', ['reason' => $reason->getRouteKey()]) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit reason">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ request()->boolean('new') || ! $editing ? 'New reason' : $selectedReason->name }}</h5>
                    @if($editing && ! request()->boolean('new'))
                        <div class="text-muted small">ID {{ $selectedReason->getKey() }}</div>
                    @endif
                </div>
                <form method="POST" action="{{ $editing && ! request()->boolean('new') ? route('merchant.return-reasons.update', $selectedReason) : route('merchant.return-reasons.store') }}">
                    @csrf
                    @if($editing && ! request()->boolean('new'))
                        @method('PUT')
                    @endif
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input id="name" name="name" value="{{ old('name', request()->boolean('new') ? '' : $selectedReason?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Sort order</label>
                            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', request()->boolean('new') ? 99 : $selectedReason?->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
                            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <label class="form-check mb-3">
                            <input name="restock_by_default" value="1" type="checkbox" class="form-check-input" @checked(old('restock_by_default', request()->boolean('new') ? true : $selectedReason?->restock_by_default))>
                            <span class="form-check-label">Restock items by default</span>
                        </label>
                        <label class="form-check">
                            <input name="status" value="active" type="checkbox" class="form-check-input" @checked(old('status', request()->boolean('new') ? 'active' : $selectedReason?->status) === 'active')>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>
                    <div class="card-footer d-flex justify-content-between gap-2">
                        @if($editing && ! request()->boolean('new'))
                            <button type="button" class="btn btn-outline-danger js-delete-return-reason">
                                <i class="ph-trash me-2"></i>
                                Delete reason
                            </button>
                        @else
                            <span></span>
                        @endif
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>

                @if($editing && ! request()->boolean('new'))
                    <form id="delete-reason-form" method="POST" action="{{ route('merchant.return-reasons.destroy', $selectedReason) }}" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('vendor_scripts')
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/tables/datatables/extensions/responsive.min.js') }}"></script>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && jQuery.fn.DataTable) {
                jQuery.extend(jQuery.fn.dataTable.defaults, {
                    autoWidth: false,
                    dom: '<"datatable-header"fl><"datatable-scroll"t><"datatable-footer"ip>',
                    language: {
                        search: '<span class="me-3">Filter:</span> <div class="form-control-feedback form-control-feedback-end flex-fill">_INPUT_<div class="form-control-feedback-icon"><i class="ph-magnifying-glass opacity-50"></i></div></div>',
                        searchPlaceholder: 'Type to filter...',
                        lengthMenu: '<span class="me-3">Show:</span> _MENU_',
                        paginate: {
                            first: 'First',
                            last: 'Last',
                            next: document.dir == 'rtl' ? '&larr;' : '&rarr;',
                            previous: document.dir == 'rtl' ? '&rarr;' : '&larr;',
                        },
                    },
                });

                jQuery('#return-reasons-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[1, 'asc'], [0, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: -1 },
                        { responsivePriority: 1, targets: 0 },
                        { responsivePriority: 2, targets: -1 },
                    ],
                });
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-return-reason');

                if (!button) {
                    return;
                }

                const form = document.getElementById('delete-reason-form');

                if (!form) {
                    return;
                }

                bootbox.confirm({
                    title: 'Delete Return Reason',
                    message: 'Are you sure you want to delete this return reason? Existing refund history will keep its saved reason details.',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });
        });
    </script>
@endpush
