{{-- Purpose: Lists shops scoped to a single merchant. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Merchant Shops"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => route('admin.merchants.show', $merchant), 'Shops' => null]"
        :action-url="route('admin.merchants.index')"
        action-label="Back to Merchants"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @include('admin.merchants.partials.management-header')
    @include('admin.merchants.partials.management-tabs')

    <div class="card">
        <div class="card-header d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-sm-between">
            <div>
                <h5 class="mb-0">Shops</h5>
                <div class="text-muted small">Total shops: {{ $totalShops }}</div>
            </div>
            <a href="{{ route('admin.merchants.shops.create', $merchant) }}" class="btn btn-primary">
                <i class="ph-plus me-2"></i>
                Add Shop
            </a>
        </div>

        @if($shops->isEmpty())
            <div class="card-body">
                <x-empty-state
                    icon="ph-storefront"
                    title="No shops have been added for this merchant yet."
                    message="Create the first customer-facing storefront for this merchant."
                />
                <div class="text-center">
                    <a href="{{ route('admin.merchants.shops.create', $merchant) }}" class="btn btn-primary">
                        <i class="ph-plus me-2"></i>
                        Add First Shop
                    </a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Shop Name</th>
                            <th>Category</th>
                            <th>Audiences</th>
                            <th>City</th>
                            <th>Mobile</th>
                            <th>Account Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shops as $shop)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $shop->name }}</div>
                                    <div class="fs-sm text-muted">{{ $shop->slug }}</div>
                                </td>
                                <td>{{ $shop->category?->name ?? '-' }}</td>
                                <td>
                                    @forelse($shop->audiences as $audience)
                                        <span class="badge bg-light text-body border me-1">{{ $audience->name }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
                                <td>{{ $shop->city?->name ?? '-' }}</td>
                                <td>{{ $shop->mobile ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $shopStatuses[$shop->status]['badge_class'] ?? 'bg-secondary' }}">
                                        {{ $shopStatuses[$shop->status]['label'] ?? ucfirst($shop->status) }}
                                    </span>
                                </td>
                                <td>{{ $shop->created_at?->format('d M Y') ?? '-' }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.merchants.shops.show', [$merchant, $shop]) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                            <i class="ph-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.merchants.shops.edit', [$merchant, $shop]) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.merchants.shops.destroy', [$merchant, $shop]) }}" class="d-inline js-delete-shop-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-shop" data-bs-popup="tooltip" title="Delete">
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
                    Showing {{ $shops->firstItem() }} to {{ $shops->lastItem() }} of {{ $shops->total() }} entries
                </div>
                {{ $shops->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-shop');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-shop-form');

                bootbox.confirm({
                    title: 'Delete Shop',
                    message: 'Are you sure you want to delete this shop?',
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
