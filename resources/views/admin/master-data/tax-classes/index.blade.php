@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Tax Classes"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => null]"
        :action-url="route('admin.master.tax-classes.create')"
        action-label="Create Tax Class"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $hasFilters = $filters['country_id'] || $filters['status'] || $filters['search'] !== '';
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Tax Class List</h5>
            <a href="#tax-class-filter-collapse" class="text-body collapsed tax-class-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="tax-class-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="tax-class-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.master.tax-classes.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="country_id" class="form-label">Country</label>
                        <select id="country_id" name="country_id" class="form-select">
                            <option value="">All</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->getKey() }}" @selected((string) $filters['country_id'] === (string) $country->getKey())>
                                    {{ $country->name }}
                                </option>
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
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Code, name or description">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.master.tax-classes.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($taxClasses->isEmpty())
            <x-empty-state icon="ph-receipt" title="No tax classes found" message="Create a tax class or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Country</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($taxClasses as $taxClass)
                            <tr>
                                <td>{{ $taxClass->country?->name }}</td>
                                <td><code>{{ $taxClass->code }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ $taxClass->name }}</div>
                                    <div class="fs-sm text-muted">{{ $taxClass->rates_count }} rates</div>
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit((string) $taxClass->description, 90) ?: '-' }}</td>
                                <td>
                                    @if($taxClass->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$taxClass->status] ?? 'bg-secondary' }}">{{ ucfirst($taxClass->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $taxClass->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($taxClass->trashed())
                                            <form method="POST" action="{{ route('admin.master.tax-classes.restore', $taxClass) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Restore" data-title="Restore Tax Class" data-message="Restore this tax class as inactive?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                    <i class="ph-arrow-counter-clockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.master.tax-classes.show', $taxClass) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                                <i class="ph-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.master.tax-classes.edit', $taxClass) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.master.tax-classes.destroy', $taxClass) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-bs-popup="tooltip" title="Delete" data-title="Delete Tax Class" data-message="Move this tax class to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
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
                    Showing {{ $taxClasses->firstItem() }} to {{ $taxClasses->lastItem() }} of {{ $taxClasses->total() }} entries
                </div>
                {{ $taxClasses->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .tax-class-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .tax-class-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush

@push('scripts')
    @include('admin.master-data.tax-classes.partials.confirm-script')
@endpush
