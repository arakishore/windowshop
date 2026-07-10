{{-- Purpose: Lists merchant profiles for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Merchants"
        subtitle="Manage merchant business profiles and verification state."
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => null]"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning'];
        $verificationClasses = ['pending' => 'secondary', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger', 'suspended' => 'warning'];
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h5 class="mb-0">Merchant List</h5>
            <a href="{{ route('admin.merchants.create') }}" class="btn btn-primary">
                <i class="ph-plus me-2"></i>
                Create Merchant
            </a>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.merchants.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="q" class="form-label">Search</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control" placeholder="Business, owner, email, mobile, GST, PAN">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="status" class="form-label">Account status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach($accountStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="verification_status" class="form-label">Verification</label>
                    <select id="verification_status" name="verification_status" class="form-select">
                        <option value="">All</option>
                        @foreach($verificationStatuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['verification_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="ph-magnifying-glass me-2"></i>
                        Filter
                    </button>
                    <a href="{{ route('admin.merchants.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>

        @if($merchants->isEmpty())
            <x-empty-state icon="ph-storefront" title="No merchants found" message="Create a merchant or adjust the current filters." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Owner</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>GST</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($merchants as $merchant)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $merchant->business_name }}</div>
                                    @if($merchant->legal_name)
                                        <div class="fs-sm text-muted">{{ $merchant->legal_name }}</div>
                                    @endif
                                </td>
                                <td>{{ $merchant->user?->name ?? 'Unavailable' }}</td>
                                <td>{{ $merchant->user?->email ?? '-' }}</td>
                                <td>{{ $merchant->user?->mobile ?? '-' }}</td>
                                <td>{{ $merchant->gst_number ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $verificationClasses[$merchant->verification_status] ?? 'secondary' }}">
                                        {{ ucfirst($merchant->verification_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusClasses[$merchant->status] ?? 'secondary' }}">
                                        {{ ucfirst($merchant->status) }}
                                    </span>
                                </td>
                                <td>{{ $merchant->created_at?->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-sm btn-light">View</a>
                                        <a href="{{ route('admin.merchants.edit', $merchant) }}" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" action="{{ route('admin.merchants.destroy', $merchant) }}" onsubmit="return confirm('Delete this merchant? This will soft delete the merchant profile and owner account.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body">
                {{ $merchants->links() }}
            </div>
        @endif
    </div>
@endsection
