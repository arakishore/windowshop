{{-- Purpose: Lists completed POS sales for the active merchant shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Sales History"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Sales History' => null]"
        :action-url="route('merchant.pos.index')"
        action-label="Open POS"
        action-icon="ph-desktop"
        action-class="btn-primary"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string $value) use ($posCurrency): string {
            $amount = number_format((float) $value, (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? '₹');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $rate = static fn (float|int|string|null $value): string => $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.').'%';
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== '')->isNotEmpty();
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Total sales</div>
                <h3 class="mb-0">{{ $money($summary['total_sales']) }}</h3>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Transactions</div>
                <h3 class="mb-0">{{ $summary['transactions'] }}</h3>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Items sold</div>
                <h3 class="mb-0">{{ $summary['items_sold'] }}</h3>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Subtotal</div>
                <h3 class="mb-0">{{ $money($summary['subtotal']) }}</h3>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Discount</div>
                <h3 class="mb-0">{{ $money($summary['discount_total']) }}</h3>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card card-body">
                <div class="text-muted text-uppercase fs-sm">Tax collected</div>
                <h3 class="mb-0">{{ $money($summary['tax_total']) }}</h3>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Sales History ({{ $orders->total() }})</h5>
            <a href="#sales-filter-collapse" class="text-body {{ $hasFilters ? '' : 'collapsed' }}" data-bs-toggle="collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="sales-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('merchant.sales.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                            <option value="partially_refunded" @selected($filters['status'] === 'partially_refunded')>Partially refunded</option>
                            <option value="refunded" @selected($filters['status'] === 'refunded')>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="payment_method" class="form-label">Payment method</label>
                        <select id="payment_method" name="payment_method" class="form-select">
                            <option value="">All methods</option>
                            @foreach($paymentMethods as $method => $label)
                                <option value="{{ $method }}" @selected($filters['payment_method'] === $method)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="customer" class="form-label">Customer</label>
                        <select id="customer" name="customer" class="form-select">
                            <option value="">All customers</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->customer_id }}" @selected((string) $filters['customer'] === (string) $customer->customer_id)>{{ $customer->customer_name ?: 'Customer' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from" class="form-label">From</label>
                        <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="to" class="form-label">To</label>
                        <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Sale #, customer, cashier">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('merchant.sales.index') }}" class="btn btn-light">Reset filters</a>
                        <button type="submit" class="btn btn-primary">
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
                        <th>Number</th>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Payment method</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td><a href="{{ route('merchant.sales.show', $order) }}" class="fw-semibold text-body">{{ $order->order_number }}</a></td>
                            <td>{{ app_datetime($order->created_at) }}</td>
                            <td>{{ $activeShop->name }}</td>
                            <td>{{ $order->customer_name ?: 'Walk-in' }}</td>
                            <td>{{ $order->createdBy?->name ?? 'Staff' }}</td>
                            <td>{{ $paymentMethods[$order->payment_method] ?? Str::headline($order->payment_method) }}</td>
                            <td>
                                @if($order->payment_status === \App\Models\Order::PAYMENT_REFUNDED)
                                    <span class="badge bg-danger bg-opacity-10 text-danger">Refunded</span>
                                @elseif($order->payment_status === \App\Models\Order::PAYMENT_PARTIALLY_REFUNDED)
                                    <span class="badge bg-warning bg-opacity-10 text-warning">Partially refunded</span>
                                @else
                                    <span class="badge bg-success bg-opacity-10 text-success">Completed</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ $money($order->grand_total) }}</td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-light btn-icon btn-sm rounded-pill" data-bs-toggle="dropdown" data-bs-popup="tooltip" title="Sale actions" aria-expanded="false" aria-label="Sale actions for {{ $order->order_number }}">
                                        <i class="ph-dots-three-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('merchant.sales.show', $order) }}" class="dropdown-item">
                                            <i class="ph-eye me-2"></i>
                                            View
                                        </a>
                                        <a href="{{ route('merchant.pos.receipt', ['order' => $order->getKey(), 'print' => 1]) }}" target="_blank" rel="noopener" class="dropdown-item">
                                            <i class="ph-printer me-2"></i>
                                            Print receipt
                                        </a>
                                        <a href="{{ route('merchant.sales.refund', $order) }}" class="dropdown-item text-warning">
                                            <i class="ph-arrow-counter-clockwise me-2"></i>
                                            Refund / Return
                                        </a>
                                        <a href="{{ route('merchant.sales.exchange', $order) }}" class="dropdown-item text-info">
                                            <i class="ph-swap me-2"></i>
                                            Exchange
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No sales found.</td>
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

    @if((float) $summary['tax_total'] > 0 || $taxSummary->isNotEmpty() || $componentSummary->isNotEmpty())
        <div class="row g-3 mt-1">
            @if($taxSummary->isNotEmpty())
                <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tax Summary</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tax class</th>
                                <th>Rate</th>
                                <th>Mode</th>
                                <th class="text-end">Taxable sales</th>
                                <th class="text-end">Tax collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($taxSummary as $row)
                                <tr>
                                    <td>{{ $row->tax_class_name }}</td>
                                    <td>{{ $rate($row->tax_rate) }}</td>
                                    <td>
                                        @if($row->price_mode)
                                            <span class="badge bg-primary bg-opacity-10 text-primary">{{ Str::headline((string) $row->price_mode) }}</span>
                                        @else
                                            <span class="badge bg-light text-muted">None</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $money($row->taxable_amount) }}</td>
                                    <td class="text-end">{{ $money($row->tax_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            @endif
            @if($componentSummary->isNotEmpty())
                <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Component Summary</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Component</th>
                                <th>Rate</th>
                                <th>Jurisdiction</th>
                                <th class="text-end">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($componentSummary as $component)
                                <tr>
                                    <td>{{ $component->component_name ?: $component->component_code }}</td>
                                    <td>
                                        @if((string) $component->min_rate === (string) $component->max_rate)
                                            {{ $rate($component->min_rate) }}
                                        @else
                                            {{ $rate($component->min_rate) }} - {{ $rate($component->max_rate) }}
                                        @endif
                                    </td>
                                    <td>{{ Str::headline((string) ($component->jurisdiction_type ?: '-')) }}</td>
                                    <td class="text-end">{{ $money($component->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            @endif
        </div>
    @endif
@endsection
