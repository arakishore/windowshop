@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Tax Rate"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Tax Classes' => route('admin.master.tax-classes.index'), $taxClass->name => route('admin.master.tax-classes.show', $taxClass), $taxRate->name => null]"
        :action-url="route('admin.master.tax-rates.components.create', $taxRate)"
        action-label="Add Component"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.master.tax-classes.rates.update', [$taxClass, $taxRate]) }}">
        @csrf
        @method('PUT')
        @include('admin.master-data.tax-rates._form')
    </form>

    @php
        $components = $taxRate->components;
        $componentTotal = $components->whereNull('deleted_at')->sum(fn ($component) => (float) $component->rate);
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Components</h5>
            <a href="{{ route('admin.master.tax-rates.components.create', $taxRate) }}" class="btn btn-primary btn-sm">
                <i class="ph-plus me-1"></i>
                Add Component
            </a>
        </div>
        <div class="card-body border-bottom">
            <span class="badge bg-light text-body border">Total rate: {{ $taxRate->total_rate }}%</span>
            <span class="badge bg-light text-body border">Component total: {{ number_format($componentTotal, 4, '.', '') }}%</span>
        </div>
        @if($components->isEmpty())
            <x-empty-state icon="ph-puzzle-piece" title="No components found" message="Add one component whose rate equals the total, or maintain a matching component set." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Rate</th>
                            <th>Jurisdiction Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($components as $component)
                            <tr>
                                <td><code>{{ $component->code }}</code></td>
                                <td class="fw-semibold">{{ $component->name }}</td>
                                <td>{{ $component->rate }}%</td>
                                <td>{{ $component->jurisdiction_type ? ucfirst($component->jurisdiction_type) : '-' }}</td>
                                <td>{{ $component->priority }}</td>
                                <td>
                                    @if($component->trashed())
                                        <span class="badge bg-danger">Trash</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        @if($component->trashed())
                                            <form method="POST" action="{{ route('admin.master.tax-rates.components.restore', [$taxRate, $component]) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                <button type="button" class="list-icons-item text-success border-0 bg-transparent p-0 js-confirm-action" data-title="Restore Component" data-message="Restore this tax component?" data-confirm-label="Yes, Restore" data-confirm-class="btn-success">
                                                    <i class="ph-arrow-counter-clockwise"></i>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.master.tax-rates.components.edit', [$taxRate, $component]) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                                <i class="ph-pencil-simple"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.master.tax-rates.components.destroy', [$taxRate, $component]) }}" class="d-inline js-confirm-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-action" data-title="Delete Component" data-message="Move this component to Trash?" data-confirm-label="Yes, Delete" data-confirm-class="btn-danger">
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
        @endif
    </div>
@endsection

@push('scripts')
    @include('admin.master-data.tax-classes.partials.confirm-script')
@endpush
