@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Order Statuses"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Order Statuses' => null]"
        :action-url="route('admin.master.order-statuses.create')"
        action-label="Create Order Status"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['search'] !== '' || $filters['status'] || $filters['category'] || $filters['type'];
    @endphp

    @error('order_status')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Order Status List</h5>
            <a href="#order-status-filter-collapse" class="text-body collapsed order-status-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="order-status-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="order-status-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.order-statuses.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Code, name, description or note">
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">All</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ Str::headline($category) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All</option>
                            <option value="system" @selected($filters['type'] === 'system')>System</option>
                            <option value="custom" @selected($filters['type'] === 'custom')>Custom</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                            <option value="trash" @selected($filters['status'] === 'trash')>Trash</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.order-statuses.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($orderStatuses->isEmpty())
            <x-empty-state icon="ph-list-checks" title="No order statuses found" message="Create an order status or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Customer Label</th>
                            <th>Category</th>
                            <th>Badge</th>
                            <th>System</th>
                            <th>Terminal</th>
                            <th>Customer Visible</th>
                            <th>Merchant Visible</th>
                            <th>Status</th>
                            <th class="text-end">Sort Order</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orderStatuses as $orderStatus)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $orderStatus->name }}
                                        @if($orderStatus->admin_description)
                                            <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="{{ $orderStatus->admin_description }}"></i>
                                        @endif
                                    </div>
                                    <span class="badge bg-light text-muted border mt-1">Code: {{ $orderStatus->code }}</span>
                                    @if($orderStatus->customer_description)
                                        <div class="text-muted small">
                                            Customer message
                                            <i class="ph-chat-circle-text ms-1" data-bs-popup="tooltip" title="{{ $orderStatus->customer_description }}"></i>
                                        </div>
                                    @endif
                                    @if($orderStatus->internal_notes)
                                        <div class="text-muted small">
                                            Internal note
                                            <i class="ph-note ms-1" data-bs-popup="tooltip" title="{{ $orderStatus->internal_notes }}"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $orderStatus->customer_label ?: '-' }}</td>
                                <td>{{ Str::headline($orderStatus->category) }}</td>
                                <td><span class="badge {{ $orderStatus->safeBadgeClass() }}">{{ Str::headline($orderStatus->badge_type) }}</span></td>
                                <td>
                                    <span class="badge {{ $orderStatus->is_system ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-body border' }}">{{ $orderStatus->is_system ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $orderStatus->is_terminal ? 'bg-danger bg-opacity-10 text-danger' : 'bg-light text-body border' }}">{{ $orderStatus->is_terminal ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>{{ $orderStatus->customer_visible ? 'Yes' : 'No' }}</td>
                                <td>{{ $orderStatus->merchant_visible ? 'Yes' : 'No' }}</td>
                                <td>
                                    @if($orderStatus->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$orderStatus->status] ?? 'bg-secondary' }}">{{ Str::headline($orderStatus->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $orderStatus->sort_order }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($orderStatus->trashed())
                                            @unless($orderStatus->is_system)
                                                <form method="POST" action="{{ route('admin.master.order-statuses.restore', $orderStatus) }}" class="d-inline js-confirm-form">
                                                    @csrf
                                                    <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore" data-title="Restore Order Status" data-message="Restore this order status as inactive?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                        <i class="ph-arrow-counter-clockwise"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @else
                                            <a href="{{ route('admin.master.order-statuses.edit', $orderStatus) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            @unless($orderStatus->is_system)
                                                <form method="POST" action="{{ route('admin.master.order-statuses.destroy', $orderStatus) }}" class="d-inline js-confirm-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Order Status" data-message="Move this custom order status to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
                                                        <i class="ph-trash"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $orderStatuses->firstItem() }} to {{ $orderStatuses->lastItem() }} of {{ $orderStatuses->total() }} entries
                </div>
                {{ $orderStatuses->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .order-status-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .order-status-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush

@push('scripts')
    @include('admin.master-data.order-statuses.partials.confirm-script')
@endpush
