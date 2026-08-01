{{-- Purpose: Lets admins review merchant requests for missing catalogue categories and attributes. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Catalogue Requests"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Catalogue Requests' => null]"
    />
@endsection

@section('content')
    @php
        $statusClasses = [
            'pending' => 'bg-warning bg-opacity-10 text-warning',
            'approved' => 'bg-success bg-opacity-10 text-success',
            'rejected' => 'bg-danger bg-opacity-10 text-danger',
            'needs_info' => 'bg-info bg-opacity-10 text-info',
        ];
        $hasFilters = $filters['status'] !== '' || $filters['type'] !== '' || $filters['search'] !== '';
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Merchant Catalogue Requests</h5>
            <a href="#catalogue-request-filter-collapse" class="text-body collapsed" data-bs-toggle="collapse" aria-expanded="false" aria-controls="catalogue-request-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="catalogue-request-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.catalogue-requests.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, merchant, shop, product">
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.catalogue-requests.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($requests->isEmpty())
            <x-empty-state icon="ph-paper-plane-tilt" title="No catalogue requests found" message="Merchant category and attribute requests will appear here." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Request</th>
                            <th>Merchant / Shop</th>
                            <th>Shop Type</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th style="width: 280px;">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $requestRow)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $requestRow->suggested_name }}</div>
                                    <span class="badge bg-light text-body border">{{ $types[$requestRow->request_type] ?? ucfirst($requestRow->request_type) }}</span>
                                    <div class="text-muted small">{{ app_datetime($requestRow->created_at) }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $requestRow->merchant?->business_name ?? '-' }}</div>
                                    <div class="text-muted small">{{ $requestRow->shop?->name ?? '-' }}</div>
                                </td>
                                <td>{{ $requestRow->rootCategory?->name ?? '-' }}</td>
                                <td>
                                    <div>Parent: {{ $requestRow->parentCategory?->name ?? '-' }}</div>
                                    @if($requestRow->example_product_name)
                                        <div>Example: {{ $requestRow->example_product_name }}</div>
                                    @endif
                                    @if($requestRow->description)
                                        <div class="text-muted small">{{ $requestRow->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $statusClasses[$requestRow->status] ?? 'bg-light text-body border' }}">
                                        {{ $statuses[$requestRow->status] ?? ucfirst($requestRow->status) }}
                                    </span>
                                    @if($requestRow->reviewedBy)
                                        <div class="text-muted small">By {{ $requestRow->reviewedBy->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.master.catalogue-requests.update', $requestRow) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="mb-2">
                                            <select name="status" class="form-select form-select-sm">
                                                @foreach($statuses as $value => $label)
                                                    <option value="{{ $value }}" @selected($requestRow->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <textarea name="admin_note" rows="2" class="form-control form-control-sm" placeholder="Admin note">{{ $requestRow->admin_note }}</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="ph-check me-1"></i>
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection
