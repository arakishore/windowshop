{{-- Purpose: Manages merchant customer-facing product availability statuses. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Availability Statuses"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Availability Statuses' => null]"
    />
@endsection

@section('content')
    @php
        $editing = $selectedStatus !== null && ! request()->boolean('new') && ! $selectedStatus->trashed();
        $formStatus = old('status', request()->boolean('new') ? 'active' : $selectedStatus?->status);
        $selectedBadge = old('badge_type', request()->boolean('new') ? 'secondary' : $selectedStatus?->badge_type);
    @endphp

    @error('availability_status')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h3 class="mb-1">Availability Statuses</h3>
            <div class="text-muted">Customer-facing labels and purchase availability rules.</div>
        </div>
        <a href="{{ route('merchant.availability-statuses.index', ['new' => 1]) }}" class="btn btn-primary">
            <i class="ph-plus me-2"></i>
            New status
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('merchant.availability-statuses.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, customer text, or code">
                </div>
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Status</label>
                    <select id="status_filter" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        <option value="trash" @selected($filters['status'] === 'trash')>Trash</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="ph-magnifying-glass me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('merchant.availability-statuses.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Statuses ({{ $statuses->count() }})</h5>
                </div>
                @if($statuses->isEmpty())
                    <x-empty-state icon="ph-tag" title="No availability statuses found" message="Create a status or adjust the current filters." />
                @else
                    <div class="table-responsive border-top">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Customer Description</th>
                                    <th class="text-center">Purchase Allowed</th>
                                    <th>Badge</th>
                                    <th>Status</th>
                                    <th class="text-end">Sort Order</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statuses as $statusRow)
                                    <tr class="{{ $selectedStatus?->is($statusRow) ? 'table-active' : '' }}">
                                        <td>
                                            <a href="{{ route('merchant.availability-statuses.index', ['status_row' => $statusRow->getRouteKey()] + array_filter($filters)) }}" class="text-body fw-semibold">{{ $statusRow->name }}</a>
                                            <div class="text-muted small">{{ $statusRow->code }}</div>
                                        </td>
                                        <td class="text-muted small">{{ $statusRow->customer_description ?: '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $statusRow->purchase_allowed ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">
                                                {{ $statusRow->purchase_allowed ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusRow->safeBadgeClass() }}">{{ $statusRow->name }}</span>
                                        </td>
                                        <td>
                                            @if($statusRow->trashed())
                                                <span class="badge bg-danger">Trash</span>
                                            @else
                                                <span class="badge {{ $statusRow->status === 'active' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }}">{{ Str::headline($statusRow->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $statusRow->sort_order }}</td>
                                        <td class="text-center">
                                            @if($statusRow->trashed())
                                                <form method="POST" action="{{ route('merchant.availability-statuses.restore', $statusRow->getRouteKey()) }}" class="d-inline">
                                                    @csrf
                                                    <button class="list-icons-item text-success border-0 bg-transparent p-0" data-bs-popup="tooltip" title="Restore">
                                                        <i class="ph-arrow-clockwise"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <a href="{{ route('merchant.availability-statuses.index', ['status_row' => $statusRow->getRouteKey()] + array_filter($filters)) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit status">
                                                    <i class="ph-pencil-simple"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ request()->boolean('new') || ! $editing ? 'New status' : $selectedStatus->name }}</h5>
                    @if($selectedStatus && ! request()->boolean('new'))
                        <div class="text-muted small">Code {{ $selectedStatus->code }}</div>
                    @endif
                </div>

                @if($selectedStatus?->trashed() && ! request()->boolean('new'))
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">Restore this status before editing it.</div>
                    </div>
                @else
                    <form method="POST" action="{{ $editing ? route('merchant.availability-statuses.update', $selectedStatus) : route('merchant.availability-statuses.store') }}">
                        @csrf
                        @if($editing)
                            @method('PUT')
                        @endif
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input id="name" name="name" value="{{ old('name', request()->boolean('new') ? '' : $selectedStatus?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                                <div class="form-text">Customers will see this label on the website and mobile app.</div>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="customer_description" class="form-label">Customer Description</label>
                                <textarea id="customer_description" name="customer_description" rows="3" class="form-control @error('customer_description') is-invalid @enderror">{{ old('customer_description', request()->boolean('new') ? '' : $selectedStatus?->customer_description) }}</textarea>
                                <div class="form-text">Customer-facing help text for future website/mobile tooltips or product pages.</div>
                                @error('customer_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="badge_type" class="form-label">Badge Type</label>
                                    <select id="badge_type" name="badge_type" class="form-select @error('badge_type') is-invalid @enderror">
                                        @foreach($badgeTypes as $badgeType)
                                            <option value="{{ $badgeType }}" @selected($selectedBadge === $badgeType)>{{ Str::headline($badgeType) }}</option>
                                        @endforeach
                                    </select>
                                    @error('badge_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="sort_order" class="form-label">Sort Order</label>
                                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', request()->boolean('new') ? 99 : $selectedStatus?->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
                                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <label class="form-check mt-3">
                                <input name="purchase_allowed" value="1" type="checkbox" class="form-check-input" @checked(old('purchase_allowed', request()->boolean('new') ? false : $selectedStatus?->purchase_allowed))>
                                <span class="form-check-label">Allow Customer Purchase</span>
                            </label>
                            <div class="form-text">Controls whether customers may purchase products using this availability status. Stock behaviour also depends on the status type.</div>
                            <label class="form-check mt-3">
                                <input name="status" value="active" type="checkbox" class="form-check-input" @checked($formStatus === 'active')>
                                <span class="form-check-label">Active</span>
                            </label>
                        </div>
                        <div class="card-footer d-flex justify-content-between gap-2">
                            @if($editing)
                                <button type="button" class="btn btn-outline-danger js-delete-availability-status">
                                    <i class="ph-trash me-2"></i>
                                    Delete
                                </button>
                            @else
                                <span></span>
                            @endif
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>

                    @if($editing)
                        <form id="delete-availability-status-form" method="POST" action="{{ route('merchant.availability-statuses.destroy', $selectedStatus) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-availability-status');

                if (!button) {
                    return;
                }

                const form = document.getElementById('delete-availability-status-form');

                if (!form) {
                    return;
                }

                bootbox.confirm({
                    title: 'Delete Availability Status',
                    message: 'Delete this availability status?<br><br>Statuses assigned to products or variants cannot be deleted.',
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
