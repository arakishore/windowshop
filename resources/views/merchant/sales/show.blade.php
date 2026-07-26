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
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('merchant.sales.index') }}" class="text-body"><i class="ph-arrow-left"></i></a>
                <h3 class="mb-0">{{ $order->order_number }}</h3>
                @if($order->payment_status === \App\Models\Order::PAYMENT_REFUNDED)
                    <span class="badge bg-secondary bg-opacity-10 text-secondary">Refunded</span>
                @endif
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
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ Str::headline($order->payment_method) }}</td>
                                <td>{{ $order->payment_reference ?: '-' }}</td>
                                <td>{{ $order->created_at?->format('d-m-Y h:i A') }}</td>
                                <td class="text-end">{{ $money($order->amount_paid) }}</td>
                                <td class="text-end fw-semibold">{{ $money($order->grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Totals</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><div class="text-muted">Subtotal</div><div>{{ $money($order->subtotal) }}</div></div>
                        <div class="col-6"><div class="text-muted">Discount</div><div>{{ $money($order->discount_total) }}</div></div>
                        <div class="col-6"><div class="text-muted">Tax</div><div>{{ $money($order->tax_total) }}</div></div>
                    </div>
                    <hr>
                    <div class="fw-bold">Grand total</div>
                    <h5>{{ $money($order->grand_total) }}</h5>
                    <div class="row g-3 mt-1">
                        <div class="col-6"><div class="text-muted">Paid</div><div>{{ $money($order->amount_paid) }}</div></div>
                        <div class="col-6"><div class="text-muted">Change</div><div>{{ $money($order->change_amount) }}</div></div>
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

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Customer</h5></div>
                <div class="card-body text-muted">{{ $order->customer_name ?: 'Walk-in' }}</div>
            </div>
        </div>
    </div>
@endsection
