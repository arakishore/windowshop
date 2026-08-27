{{-- Purpose: Lists global customer cancellation reasons for admin management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Customer Cancellation Reasons"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Customer Cancellation Reasons' => null]"
        :action-url="route('admin.master.customer-cancellation-reasons.create')"
        action-label="Create Reason"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php($statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'])
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Reason List</h5></div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.master.customer-cancellation-reasons.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name or code">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.master.customer-cancellation-reasons.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>

        @if($reasons->isEmpty())
            <x-empty-state icon="ph-x-circle" title="No reasons found" message="Create a reason or adjust the filters." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Requires Comment</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reasons as $reason)
                            <tr>
                                <td class="fw-semibold">{{ $reason->name }}</td>
                                <td><code>{{ $reason->code }}</code></td>
                                <td>{{ $reason->requires_comment ? 'Yes' : 'No' }}</td>
                                <td>{{ $reason->sort_order }}</td>
                                <td><span class="badge {{ $statusClasses[$reason->status] ?? 'bg-secondary' }}">{{ ucfirst($reason->status) }}</span></td>
                                <td class="text-center">
                                    <a href="{{ route('admin.master.customer-cancellation-reasons.edit', $reason) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.master.customer-cancellation-reasons.destroy', $reason) }}" class="d-inline" onsubmit="return confirm('Delete this customer cancellation reason?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link p-0 ms-2 text-danger align-baseline" data-bs-popup="tooltip" title="Delete">
                                            <i class="ph-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">Showing {{ $reasons->firstItem() }} to {{ $reasons->lastItem() }} of {{ $reasons->total() }} entries</div>
                {{ $reasons->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection
