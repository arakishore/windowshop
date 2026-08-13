@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Postal Code Restrictions"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Postal Code Restrictions' => null]"
        :action-url="route('admin.master.postal-code-restrictions.create')"
        action-label="Create Restriction"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['state'] !== '' || $filters['district'] !== '' || $filters['status'] || $filters['search'] !== '';
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Global Restriction List</h5>
            <a href="#postal-restriction-filter-collapse" class="text-body collapsed postal-restriction-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="postal-restriction-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>
        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="postal-restriction-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.postal-code-restrictions.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="">All</option>
                            @foreach($states as $state)
                                <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="district" class="form-label">District</label>
                        <select id="district" name="district" class="form-select">
                            <option value="">All</option>
                            @foreach($districts as $district)
                                <option value="{{ $district }}" @selected($filters['district'] === $district)>{{ $district }}</option>
                            @endforeach
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
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Postal code, office or reason">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                        <a href="{{ route('admin.master.postal-code-restrictions.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($restrictions->isEmpty())
            <x-empty-state icon="ph-map-pin-slash" title="No restrictions found" message="Create a global restriction or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Postal Code</th>
                            <th>Area / Office</th>
                            <th>District</th>
                            <th>State</th>
                            <th>Reason</th>
                            <th>Starts At</th>
                            <th>Ends At</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($restrictions as $restriction)
                            @php($location = $locations[$restriction->postal_code] ?? null)
                            <tr>
                                <td><code>{{ $restriction->postal_code }}</code></td>
                                <td>{{ $location->office_name ?? '-' }}</td>
                                <td>{{ $location->district ?? '-' }}</td>
                                <td>{{ $location->state ?? '-' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $restriction->reason, 70) ?: '-' }}</td>
                                <td>{{ $restriction->starts_at ? app_datetime($restriction->starts_at) : '-' }}</td>
                                <td>{{ $restriction->ends_at ? app_datetime($restriction->ends_at) : '-' }}</td>
                                <td>
                                    @if($restriction->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$restriction->status] ?? 'bg-secondary' }}">{{ ucfirst($restriction->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($restriction->trashed())
                                            <form method="POST" action="{{ route('admin.master.postal-code-restrictions.restore', $restriction) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore" data-title="Restore Restriction" data-message="Restore this restriction?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success"><i class="ph-arrow-counter-clockwise"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.master.postal-code-restrictions.toggle-status', $restriction) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="list-icons-item text-warning border-0 bg-transparent p-0" data-bs-popup="tooltip" title="Activate / Deactivate"><i class="ph-power"></i></button>
                                            </form>
                                            <a href="{{ route('admin.master.postal-code-restrictions.edit', $restriction) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit"><i class="ph-pencil-simple"></i></a>
                                            <form method="POST" action="{{ route('admin.master.postal-code-restrictions.destroy', $restriction) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Restriction" data-message="Move this restriction to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger"><i class="ph-trash"></i></button>
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
                <div class="text-muted mb-3 mb-lg-0">Showing {{ $restrictions->firstItem() }} to {{ $restrictions->lastItem() }} of {{ $restrictions->total() }} entries</div>
                {{ $restrictions->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .postal-restriction-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .postal-restriction-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush

@push('scripts')
    @include('admin.master-data.postal-code-restrictions.partials.confirm-script')
@endpush
