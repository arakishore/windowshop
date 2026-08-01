@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Tax Class Details"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), $taxClass->name => null]"
        :action-url="route('admin.master.tax-classes.rates.create', $taxClass)"
        action-label="Add Tax Rate"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">{{ $taxClass->name }}</h5>
            <a href="{{ route('admin.master.tax-classes.edit', $taxClass) }}" class="btn btn-sm btn-light border">
                <i class="ph-pencil-simple me-1"></i>
                Edit Tax Class
            </a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted fs-sm">Country</div>
                    <div class="fw-semibold">{{ $taxClass->country?->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-sm">Code</div>
                    <code>{{ $taxClass->code }}</code>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-sm">Status</div>
                    <span class="badge {{ $statusClasses[$taxClass->status] ?? 'bg-secondary' }}">{{ ucfirst($taxClass->status) }}</span>
                </div>
                <div class="col-md-3">
                    <div class="text-muted fs-sm">Created</div>
                    <div>{{ app_datetime($taxClass->created_at) }}</div>
                </div>
                @if($taxClass->description)
                    <div class="col-12">
                        <div class="text-muted fs-sm">Description</div>
                        <div>{{ $taxClass->description }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Tax Rates</h5>
            <a href="{{ route('admin.master.tax-classes.rates.create', $taxClass) }}" class="btn btn-primary btn-sm">
                <i class="ph-plus me-1"></i>
                Add Rate
            </a>
        </div>

        @if($rates->isEmpty())
            <x-empty-state icon="ph-percent" title="No tax rates found" message="Add the first effective tax rate for this tax class." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Total Rate</th>
                            <th>Effective From</th>
                            <th>Effective To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Components</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rates as $rate)
                            <tr>
                                <td class="fw-semibold">{{ $rate->name }}</td>
                                <td>{{ $rate->total_rate }}%</td>
                                <td>{{ app_date($rate->effective_from) }}</td>
                                <td>{{ app_date($rate->effective_to, '-') }}</td>
                                <td>{{ $rate->priority }}</td>
                                <td>
                                    @if($rate->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge {{ $statusClasses[$rate->status] ?? 'bg-secondary' }}">{{ ucfirst($rate->status) }}</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-light text-body border">{{ $rate->components_count }}</span></td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($rate->trashed())
                                            <form method="POST" action="{{ route('admin.master.tax-classes.rates.restore', [$taxClass, $rate]) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-title="Restore Tax Rate" data-message="Restore this tax rate as inactive?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                    <i class="ph-arrow-counter-clockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.master.tax-classes.rates.edit', [$taxClass, $rate]) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.master.tax-classes.rates.destroy', [$taxClass, $rate]) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-title="Delete Tax Rate" data-message="Move this tax rate to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
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
                    Showing {{ $rates->firstItem() }} to {{ $rates->lastItem() }} of {{ $rates->total() }} entries
                </div>
                {{ $rates->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    @include('admin.master-data.tax-classes.partials.confirm-script')
@endpush
