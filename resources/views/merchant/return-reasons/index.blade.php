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
                    <h5 class="mb-0">Reasons ({{ $reasons->total() }})</h5>
                    <form method="GET" action="{{ route('merchant.return-reasons.index') }}" style="width: min(100%, 320px);">
                        <div class="input-group">
                            <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                            <input name="search" value="{{ $search }}" type="search" class="form-control" placeholder="Search...">
                        </div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="text-end">Order</th>
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
                                        <a href="{{ route('merchant.return-reasons.index', ['reason' => $reason->getRouteKey()]) }}" class="list-icons-item" data-bs-popup="tooltip" title="Edit reason"><i class="ph-dots-three-vertical"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                    <div class="text-muted mb-3 mb-lg-0">
                        Showing {{ $reasons->firstItem() }} to {{ $reasons->lastItem() }} of {{ $reasons->total() }}
                    </div>
                    {{ $reasons->onEachSide(1)->links('pagination::admin-datatable') }}
                </div>
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
                        <label class="form-check mb-3">
                            <input name="requires_manager_override" value="1" type="checkbox" class="form-check-input" @checked(old('requires_manager_override', request()->boolean('new') ? false : $selectedReason?->requires_manager_override))>
                            <span class="form-check-label">Requires manager override</span>
                        </label>
                        <label class="form-check">
                            <input name="status" value="active" type="checkbox" class="form-check-input" @checked(old('status', request()->boolean('new') ? 'active' : $selectedReason?->status) === 'active')>
                            <span class="form-check-label">Active</span>
                        </label>
                    </div>
                    <div class="card-footer d-flex justify-content-between gap-2">
                        @if($editing && ! request()->boolean('new'))
                            <button type="submit" form="delete-reason-form" class="btn btn-outline-danger">
                                <i class="ph-trash me-2"></i>
                                Delete reason
                            </button>
                        @else
                            <span></span>
                        @endif
                        <div class="d-flex gap-2">
                            <a href="{{ route('merchant.return-reasons.index') }}" class="btn btn-light">Discard</a>
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
