{{-- Purpose: Merchant-owned shop list. --}}
@extends('layouts.merchant')

@section('title', 'My Shops | WindowShop')

@section('page_title', 'My Shops')

@section('content')
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h5 class="mb-0">My Shops</h5>
            <i
                class="ph-info text-muted"
                data-bs-placement="right"
                data-bs-popup="tooltip"
                title="Manage your shop information. Use the shop selector in the header to switch the shop you're currently managing."
            ></i>
        </div>

        @if($shops->isEmpty())
            <x-empty-state
                icon="ph-storefront"
                title="No shops are available yet."
                message="Your approved shops will appear here after setup by the admin team."
            />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Shop</th>
                            <th>Shop Type</th>
                            <th>City</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shops as $shop)
                            @php
                                $statusConfig = $shopStatuses[$shop->status] ?? ['label' => ucfirst($shop->status), 'badge_class' => 'bg-secondary'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $shop->name }}</div>
                                    <div class="fs-sm text-muted">{{ $shop->slug }}</div>
                                </td>
                                <td>{{ $shop->rootProductCategory?->name ?? '-' }}</td>
                                <td>{{ $shop->city?->name ?? '-' }}</td>
                                <td><span class="badge {{ $statusConfig['badge_class'] }}">{{ $statusConfig['label'] }}</span></td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('merchant.shops.show', $shop) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="View">
                                            <i class="ph-eye"></i>
                                        </a>
                                        <a href="{{ route('merchant.shops.edit', $shop) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
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
