{{-- Purpose: Shows a completed POS sale with refund actions. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="{{ $order->order_number }}"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Sales History' => route('merchant.sales.index'), $order->order_number => null]"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string $value) use ($posCurrency): string {
            $amount = number_format((float) $value, (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? '₹');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $canRefund = collect($refundableQuantities)->sum() > 0;
        $canExchange = collect($exchangeableQuantities ?? [])->sum() > 0;
        $itemDiscount = max(0, (float) $order->discount_total - (float) $order->order_discount_amount);
        $paymentReference = collect([
            $order->payment_reference ? 'Reference: '.$order->payment_reference : null,
            $order->upi_txn ? 'UPI: '.$order->upi_txn : null,
            $order->terminal_id ? 'Terminal: '.$order->terminal_id : null,
        ])->filter()->implode(' | ');
        $fulfilmentLabel = Str::headline($order->fulfilment_type ?: \App\Models\Order::FULFILMENT_COUNTER);
        $fulfilmentClass = match ($order->fulfilment_type) {
            \App\Models\Order::FULFILMENT_PICKUP => 'bg-info bg-opacity-10 text-info',
            \App\Models\Order::FULFILMENT_DELIVERY => 'bg-primary bg-opacity-10 text-primary',
            default => 'bg-secondary bg-opacity-10 text-secondary',
        };
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('merchant.sales.index') }}" class="text-body"><i class="ph-arrow-left"></i></a>
                <h3 class="mb-0">{{ $order->order_number }}</h3>
                @if($order->payment_status === \App\Models\Order::PAYMENT_REFUNDED)
                    <span class="badge bg-danger bg-opacity-10 text-danger">Refunded</span>
                @endif
                <span class="badge {{ $fulfilmentClass }}">{{ $fulfilmentLabel }}</span>
            </div>
            <div class="text-muted">{{ $activeShop->name }} · {{ $order->created_at?->format('d-m-Y h:i A') }} · {{ $order->createdBy?->name ?? 'Staff' }}</div>
        </div>
        <div class="d-flex gap-2">
            @if($canRefund)
                <a href="{{ route('merchant.sales.refund', $order) }}" class="btn btn-primary">
                    <i class="ph-arrow-counter-clockwise me-2"></i>
                    Process refund
                </a>
            @endif
            @if($canExchange)
                <a href="{{ route('merchant.sales.exchange', $order) }}" class="btn btn-warning">
                    <i class="ph-swap me-2"></i>
                    Process exchange
                </a>
            @endif
            <a href="{{ route('merchant.pos.receipt', ['order' => $order->getKey(), 'print' => 1]) }}" target="_blank" class="btn btn-light">
                <i class="ph-receipt me-2"></i>
                Print receipt
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Items</h5></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit price</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product_name }}</div>
                                        <code>{{ $item->sku ?: $item->barcode ?: '-' }}</code>
                                    </td>
                                    <td class="text-end">{{ $item->quantity }} pc</td>
                                    <td class="text-end">{{ $money($item->unit_price) }}</td>
                                    <td class="text-end">{{ $money($item->line_tax) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Payments</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Paid at</th>
                                <th class="text-end">Tendered</th>
                                <th class="text-end">Change</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ Str::headline($order->payment_method) }}</td>
                                <td>{{ $paymentReference !== '' ? $paymentReference : '-' }}</td>
                                <td>{{ $order->created_at?->format('d-m-Y h:i A') }}</td>
                                <td class="text-end">{{ $money($order->amount_paid) }}</td>
                                <td class="text-end">{{ $money($order->change_amount) }}</td>
                                <td class="text-end fw-semibold">{{ $money($order->grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Sale Summary</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span class="text-muted">Fulfilment</span>
                        <span class="badge {{ $fulfilmentClass }}">{{ $fulfilmentLabel }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span class="text-muted">Customer</span>
                        <span class="text-end">{{ $order->customer_name ?: 'Walk-in' }}</span>
                    </div>
                    @if($order->customer_mobile)
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Mobile</span>
                            <span>{{ $order->customer_mobile }}</span>
                        </div>
                    @endif
                    @if($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
                        <hr>
                        <div class="text-muted mb-1">Delivery Address</div>
                        <div>
                            {{ collect([
                                $order->shipping_address_line_1,
                                $order->shipping_address_line_2,
                                $order->shipping_landmark,
                                $order->shipping_city,
                                $order->shipping_state,
                                $order->shipping_postal_code,
                            ])->filter()->implode(', ') ?: '-' }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Totals</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold">{{ $money($order->subtotal) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Discount</span>
                        <span class="fw-semibold text-danger">{{ $money($itemDiscount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Order Discount</span>
                        <span class="fw-semibold text-danger">{{ $money($order->order_discount_amount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Shipping</span>
                        <span class="fw-semibold">{{ $money($order->shipping_total) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax</span>
                        <span class="fw-semibold">{{ $money($order->tax_total) }}</span>
                    </div>
                    @if((float) $order->rounding_adjustment !== 0.0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Round off</span>
                            <span class="fw-semibold">{{ $money($order->rounding_adjustment) }}</span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">Grand Total</span>
                        <span class="fw-bold">{{ $money($order->grand_total) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tendered</span>
                        <span class="fw-semibold">{{ $money($order->amount_paid) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Change</span>
                        <span class="fw-semibold">{{ $money($order->change_amount) }}</span>
                    </div>
                </div>
            </div>

            @if($order->refunds->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Refunds against this sale</h5></div>
                    <div class="list-group list-group-flush">
                        @foreach($order->refunds as $refund)
                            <div class="list-group-item d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $refund->refund_number }}</div>
                                    <div class="text-muted small">{{ $refund->created_at?->format('d-m-Y') }} · {{ $refund->reason_name }}</div>
                                </div>
                                <div class="fw-semibold text-nowrap">-{{ $money($refund->refund_total) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($order->exchanges->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Exchanges against this sale</h5></div>
                    <div class="list-group list-group-flush">
                        @foreach($order->exchanges as $exchange)
                            <a href="{{ route('merchant.sales.exchange.receipt', $exchange) }}" class="list-group-item d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $exchange->exchange_number }}</div>
                                    <div class="text-muted small">Replacement {{ $exchange->replacementOrder?->order_number ?? '-' }}</div>
                                </div>
                                <div class="text-end text-nowrap">
                                    <div class="fw-semibold">{{ $money($exchange->replacement_total) }}</div>
                                    <div class="small text-muted">{{ Str::headline($exchange->settlement_type) }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
