@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Postal Codes"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Codes' => null]"
        :action-url="route('admin.master.postal-codes.create')"
        action-label="Create Postal Code"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['postal_code'] !== '' || $filters['state'] !== '' || $filters['district'] !== '' || $filters['shipping_enabled'] !== null || $filters['status'] || $filters['search'] !== '';
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Postal Code List</h5>
            <a href="#postal-code-filter-collapse" class="text-body collapsed postal-code-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="postal-code-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="postal-code-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.postal-codes.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input id="postal_code" name="postal_code" type="text" value="{{ $filters['postal_code'] }}" class="form-control" placeholder="400001">
                    </div>
                    <div class="col-md-2">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">All</option>
                            @foreach($states as $state)
                                <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="district" class="form-label">District</label>
                        <select id="district" name="district" class="form-select">
                            <option value="">All</option>
                            @foreach($districts as $district)
                                <option value="{{ $district }}" @selected($filters['district'] === $district)>{{ $district }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="shipping_enabled" class="form-label">Shipping</label>
                        <select id="shipping_enabled" name="shipping_enabled" class="form-select">
                            <option value="">All</option>
                            <option value="1" @selected($filters['shipping_enabled'] === '1')>Yes</option>
                            <option value="0" @selected($filters['shipping_enabled'] === '0')>No</option>
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
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="PIN, office, district, state, circle">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.postal-codes.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($postalCodes->isEmpty())
            <x-empty-state icon="ph-map-pin" title="No postal codes found" message="Create a postal code or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Postal Code</th>
                            <th>Office / Place Name</th>
                            <th>District</th>
                            <th>State</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Shipping</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($postalCodes as $postalCode)
                            <tr>
                                <td>{{ $postalCode->getKey() }}</td>
                                <td><code>{{ $postalCode->postal_code }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ $postalCode->office_name }}</div>
                                    <div class="fs-sm text-muted">
                                        {{ collect([$postalCode->office_type, $postalCode->division_name, $postalCode->region_name])->filter()->implode(' | ') ?: '-' }}
                                    </div>
                                </td>
                                <td>{{ $postalCode->district ?: '-' }}</td>
                                <td>{{ $postalCode->state ?: '-' }}</td>
                                <td>{{ $postalCode->latitude ?? '-' }}</td>
                                <td>{{ $postalCode->longitude ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $postalCode->shipping_enabled ? 'bg-success' : 'bg-light text-body border' }}">
                                        {{ $postalCode->shipping_enabled ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    @if($postalCode->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$postalCode->status] ?? 'bg-secondary' }}">{{ ucfirst($postalCode->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($postalCode->trashed())
                                            <form method="POST" action="{{ route('admin.master.postal-codes.restore', $postalCode) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore" data-title="Restore Postal Code" data-message="Restore this postal code record?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                    <i class="ph-arrow-counter-clockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.master.postal-codes.show', $postalCode) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                                <i class="ph-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.master.postal-codes.edit', $postalCode) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.master.postal-codes.destroy', $postalCode) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Postal Code" data-message="Move this postal code record to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
                                                    <i class="ph-trash"></i>
                                                </button>
                                            </form>
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
                    Showing {{ $postalCodes->firstItem() }} to {{ $postalCodes->lastItem() }} of {{ $postalCodes->total() }} entries
                </div>
                {{ $postalCodes->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .postal-code-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .postal-code-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush

@push('scripts')
    @include('admin.master-data.postal-codes.partials.confirm-script')
@endpush
