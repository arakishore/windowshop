@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Payment Statuses"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Payment Statuses' => null]"
        :action-url="route('admin.master.payment-statuses.create')"
        action-label="Create Payment Status"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['search'] !== '' || $filters['status'] || $filters['category'] || $filters['type'];
    @endphp

    @error('payment_status')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Payment Status List</h5>
            <a href="#payment-status-filter-collapse" class="text-body collapsed payment-status-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="payment-status-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="payment-status-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.payment-statuses.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Code, name, description or category">
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
                        <a href="{{ route('admin.master.payment-statuses.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($paymentStatuses->isEmpty())
            <x-empty-state icon="ph-credit-card" title="No payment statuses found" message="Create a payment status or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Badge</th>
                            <th>System</th>
                            <th>Terminal</th>
                            <th>Merchant Visible</th>
                            <th>Status</th>
                            <th class="text-end">Sort Order</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentStatuses as $paymentStatus)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $paymentStatus->name }}
                                        @if($paymentStatus->description)
                                            <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="{{ $paymentStatus->description }}"></i>
                                        @endif
                                    </div>
                                    <span class="badge bg-light text-muted border mt-1">Code: {{ $paymentStatus->code }}</span>
                                </td>
                                <td>
                                    {{ Str::headline($paymentStatus->category) }}
                                    @if($paymentStatus->category_description)
                                        <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="{{ $paymentStatus->category_description }}"></i>
                                    @endif
                                </td>
                                <td><span class="badge {{ $paymentStatus->safeBadgeClass() }}">{{ Str::headline($paymentStatus->badge_type) }}</span></td>
                                <td>
                                    <span class="badge {{ $paymentStatus->is_system ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-body border' }}">{{ $paymentStatus->is_system ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $paymentStatus->is_terminal ? 'bg-danger bg-opacity-10 text-danger' : 'bg-light text-body border' }}">{{ $paymentStatus->is_terminal ? 'Yes' : 'No' }}</span>
                                </td>
                                <td>{{ $paymentStatus->merchant_visible ? 'Yes' : 'No' }}</td>
                                <td>
                                    @if($paymentStatus->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$paymentStatus->status] ?? 'bg-secondary' }}">{{ Str::headline($paymentStatus->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $paymentStatus->sort_order }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($paymentStatus->trashed())
                                            @unless($paymentStatus->is_system)
                                                <form method="POST" action="{{ route('admin.master.payment-statuses.restore', $paymentStatus) }}" class="d-inline js-confirm-form">
                                                    @csrf
                                                    <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore" data-title="Restore Payment Status" data-message="Restore this payment status as inactive?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                        <i class="ph-arrow-counter-clockwise"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @else
                                            <a href="{{ route('admin.master.payment-statuses.edit', $paymentStatus) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            @unless($paymentStatus->is_system)
                                                <form method="POST" action="{{ route('admin.master.payment-statuses.destroy', $paymentStatus) }}" class="d-inline js-confirm-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Payment Status" data-message="Move this custom payment status to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
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
                    Showing {{ $paymentStatuses->firstItem() }} to {{ $paymentStatuses->lastItem() }} of {{ $paymentStatuses->total() }} entries
                </div>
                {{ $paymentStatuses->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .payment-status-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .payment-status-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush

@push('scripts')
    @include('admin.master-data.payment-statuses.partials.confirm-script')
@endpush
