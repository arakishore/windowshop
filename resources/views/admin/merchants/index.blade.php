{{-- Purpose: Lists merchant profiles for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Merchants"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => null]"
        :action-url="route('admin.merchants.create')"
        action-label="Create Merchant"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border', 'suspended' => 'bg-warning'];
        $verificationClasses = ['pending' => 'bg-light text-body border', 'submitted' => 'bg-info', 'approved' => 'bg-success', 'rejected' => 'bg-danger', 'suspended' => 'bg-warning'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Merchant List</h5>
            <a href="#merchant-filter-collapse" class="text-body collapsed merchant-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="merchant-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse" id="merchant-filter-collapse">
            <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.merchants.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="q" class="form-label">Search</label>
                    <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control" placeholder="Business, owner, email, mobile, GST, address, landmark, pincode">
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
        </div>

        @if($merchants->isEmpty())
            <x-empty-state icon="ph-storefront" title="No merchants found" message="Create a merchant or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Business</th>
                            <th>Owner</th>
                            <th>Mobile</th>
                            <th>Shops</th>
                            <th>Address</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
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
                                <td>{{ $merchant->user?->mobile ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-light text-body border">{{ $merchant->shops_count }}</span>
                                </td>
                                <td>
                                    @if($merchant->businessAddress)
                                        <div>{{ $merchant->businessAddress->address_line_1 }}</div>
                                        @if($merchant->businessAddress->address_line_2)
                                            <div class="fs-sm text-muted">{{ $merchant->businessAddress->address_line_2 }}</div>
                                        @endif
                                        @if($merchant->businessAddress->landmark || $merchant->businessAddress->pincode)
                                            <div class="fs-sm text-muted">
                                                @if($merchant->businessAddress->landmark)
                                                    Landmark: {{ $merchant->businessAddress->landmark }}
                                                @endif
                                                @if($merchant->businessAddress->landmark && $merchant->businessAddress->pincode)
                                                    <span class="mx-1">|</span>
                                                @endif
                                                @if($merchant->businessAddress->pincode)
                                                    Pincode: {{ $merchant->businessAddress->pincode }}
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $verificationClasses[$merchant->verification_status] ?? 'bg-secondary' }}">
                                        {{ \App\Enums\MerchantVerificationStatus::badgeLabelFor($merchant->verification_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClasses[$merchant->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($merchant->status) }}
                                    </span>
                                </td>
                                <td>{{ $merchant->created_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.merchants.show', $merchant) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                            <i class="ph-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.merchants.edit', $merchant) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.merchants.destroy', $merchant) }}" class="d-inline js-delete-merchant-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-merchant" data-bs-popup="tooltip" title="Delete">
                                                <i class="ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $merchants->firstItem() }} to {{ $merchants->lastItem() }} of {{ $merchants->total() }} entries
                </div>
                {{ $merchants->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .merchant-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .merchant-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-merchant');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-merchant-form');

                bootbox.confirm({
                    title: 'Delete Record',
                    message: 'Are you sure you want to delete this record?',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });
        });
    </script>
@endpush
