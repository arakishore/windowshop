{{-- Purpose: Read-only operational storefront orders list for the active merchant shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Orders"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Orders' => null]"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string|null $value) use ($posCurrency): string {
            $amount = number_format((float) ($value ?? 0), (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? 'INR ');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $statusLabel = static fn (string|null $code): string => $code && isset($orderStatuses[$code]) ? $orderStatuses[$code]->name : Str::headline((string) ($code ?: 'unknown'));
        $statusClass = static fn (string|null $code): string => $code && isset($orderStatuses[$code]) ? $orderStatuses[$code]->safeBadgeClass() : 'bg-secondary';
        $paymentStatusLabel = static fn (string|null $code): string => $code && isset($paymentStatuses[$code]) ? $paymentStatuses[$code]->name : Str::headline((string) ($code ?: 'unknown'));
        $paymentStatusClass = static fn (string|null $code): string => $code && isset($paymentStatuses[$code]) ? $paymentStatuses[$code]->safeBadgeClass() : 'bg-secondary';
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== '')->isNotEmpty();
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0">Orders ({{ $orders->total() }})</h5>
                <div class="text-muted fs-sm mt-1">Storefront orders for {{ $activeShop->name }}</div>
            </div>
            <a href="#orders-filter-collapse" class="text-body {{ $hasFilters ? '' : 'collapsed' }}" data-bs-toggle="collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="orders-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('merchant.orders.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="source" class="form-label">Source</label>
                        <select id="source" name="source" class="form-select">
                            <option value="">All sources</option>
                            @foreach($operationalSources as $source)
                                <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $sourceLabels[$source] ?? Str::headline($source) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach($orderStatusOptions as $code => $label)
                                <option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fulfillment" class="form-label">Fulfillment</label>
                        <select id="fulfillment" name="fulfillment" class="form-select">
                            <option value="">All fulfillment</option>
                            <option value="{{ \App\Models\Order::FULFILMENT_DELIVERY }}" @selected($filters['fulfillment'] === \App\Models\Order::FULFILMENT_DELIVERY)>Delivery</option>
                            <option value="{{ \App\Models\Order::FULFILMENT_PICKUP }}" @selected($filters['fulfillment'] === \App\Models\Order::FULFILMENT_PICKUP)>Pickup</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Order number, customer, mobile">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <a href="{{ route('merchant.orders.index') }}" class="btn btn-light w-100">Reset</a>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order Number</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Customer</th>
                        <th>Fulfillment</th>
                        <th>Payment</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('merchant.orders.show', $order) }}" class="fw-semibold text-body">{{ $order->order_number }}</a>
                            </td>
                            <td>{{ app_datetime($order->created_at) }}</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $sourceLabels[$order->created_source] ?? Str::headline($order->created_source) }}</span></td>
                            <td>
                                <div>{{ $order->customer_name ?: 'Customer' }}</div>
                                @if($order->customer_mobile)
                                    <div class="text-muted fs-sm">{{ $order->customer_mobile }}</div>
                                @endif
                            </td>
                            <td>{{ $fulfillmentTypes[$order->fulfilment_type] ?? Str::headline((string) $order->fulfilment_type) }}</td>
                            <td>{{ $paymentMethods[$order->payment_method] ?? Str::headline((string) $order->payment_method) }}</td>
                            <td><span class="badge {{ $paymentStatusClass($order->payment_status) }} bg-opacity-10 text-body">{{ $paymentStatusLabel($order->payment_status) }}</span></td>
                            <td>
                                <span class="badge {{ $statusClass($order->order_status) }} bg-opacity-10 text-body">{{ $statusLabel($order->order_status) }}</span>
                                @php($orderStockShortage = $stockShortages[$order->getKey()] ?? ['has_shortage' => false])
                                @if($orderStockShortage['has_shortage'])
                                    <div class="mt-1">
                                        <span class="badge bg-warning bg-opacity-10 text-warning">
                                            <i class="ph-warning-circle me-1"></i>
                                            Stock Shortage
                                        </span>
                                        @if(filled($orderStockShortage['summary'] ?? null))
                                            <div class="text-muted fs-sm">{{ $orderStockShortage['summary'] }}</div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ $money($order->grand_total) }}</td>
                            <td class="text-center">
                                <a href="{{ route('merchant.orders.show', $order) }}" class="btn btn-light btn-sm">
                                    <i class="ph-eye me-1"></i>
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No operational orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
            <div class="text-muted mb-3 mb-lg-0">
                @if($orders->total() > 0)
                    Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} entries
                @else
                    Showing 0 entries
                @endif
            </div>
            {{ $orders->onEachSide(1)->links('pagination::admin-datatable') }}
        </div>
    </div>
@endsection
