{{-- Purpose: Read-only merchant order detail view for storefront orders. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Order {{ $order->order_number }}"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Orders' => route('merchant.orders.index'), $order->order_number => null]"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string|null $value) use ($posCurrency): string {
            $amount = number_format((float) ($value ?? 0), (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? 'INR ');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $label = static fn (array $map, string|null $code): string => $code ? ($map[$code] ?? Str::headline($code)) : '-';
        $statusLabel = static fn (string|null $code): string => $code && isset($orderStatuses[$code]) ? $orderStatuses[$code]->name : Str::headline((string) ($code ?: 'unknown'));
        $statusClass = static fn (string|null $code): string => $code && isset($orderStatuses[$code]) ? $orderStatuses[$code]->safeBadgeClass() : 'bg-secondary';
        $paymentStatusLabel = static fn (string|null $code): string => $code && isset($paymentStatuses[$code]) ? $paymentStatuses[$code]->name : Str::headline((string) ($code ?: 'unknown'));
        $paymentStatusClass = static fn (string|null $code): string => $code && isset($paymentStatuses[$code]) ? $paymentStatuses[$code]->safeBadgeClass() : 'bg-secondary';
        $balance = max(0, (float) $order->grand_total - (float) $order->amount_paid);
        $formatAddress = static function (array $parts): array {
            return collect($parts)->filter(fn ($value) => filled($value))->values()->all();
        };
        $shippingLines = $formatAddress([
            $order->shipping_recipient_name,
            trim((string) $order->shipping_mobile_country_code.' '.(string) $order->shipping_mobile),
            $order->shipping_address_line_1,
            $order->shipping_address_line_2,
            $order->shipping_landmark,
            collect([$order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_postal_code])->filter()->implode(', '),
        ]);
        $billingLines = $formatAddress([
            $order->billing_recipient_name,
            trim((string) $order->billing_mobile_country_code.' '.(string) $order->billing_mobile),
            $order->billing_address_line_1,
            $order->billing_address_line_2,
            $order->billing_landmark,
            collect([$order->billing_city, $order->billing_state, $order->billing_country, $order->billing_postal_code])->filter()->implode(', '),
        ]);
        $billingSameAsShipping = $order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY && $shippingLines === $billingLines && count($billingLines) > 0;
        $shopAddressLines = $formatAddress([
            $activeShop->name,
            $activeShop->address_line_1,
            $activeShop->address_line_2,
            $activeShop->landmark,
            collect([$activeShop->city?->name, $activeShop->state?->name, $activeShop->country?->name, $activeShop->pincode])->filter()->implode(', '),
        ]);
        $pickupInstructions = (string) $activeShop->setting('fulfillment', 'pickup_instructions', '');
    @endphp

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="text-muted fs-sm">Order</div>
                        <h4 class="mb-0">{{ $order->order_number }}</h4>
                    </div>
                    <span class="badge {{ $statusClass($order->order_status) }} bg-opacity-10 text-body">{{ $statusLabel($order->order_status) }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Order Date</div>
                            <div class="fw-semibold">{{ app_datetime($order->created_at) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Source</div>
                            <div class="fw-semibold">{{ $label($sourceLabels, $order->created_source) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Fulfillment</div>
                            <div class="fw-semibold">{{ $label($fulfillmentTypes, $order->fulfilment_type) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Payment Method</div>
                            <div class="fw-semibold">{{ $label($paymentMethods, $order->payment_method) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Payment Status</div>
                            <span class="badge {{ $paymentStatusClass($order->payment_status) }} bg-opacity-10 text-body">{{ $paymentStatusLabel($order->payment_status) }}</span>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted fs-sm">Active Shop</div>
                            <div class="fw-semibold">{{ $activeShop->name }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU / Variant</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->product_name }}</div>
                                        @if($item->barcode)
                                            <div class="text-muted fs-sm">Barcode: {{ $item->barcode }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $item->sku ?: '-' }}</div>
                                        @if($item->variant_name)
                                            <div class="text-muted fs-sm">{{ $item->variant_name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $money($item->unit_price) }}</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                                    <td class="text-end">{{ $money($item->line_discount) }}</td>
                                    <td class="text-end">{{ $money($item->line_tax) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Status History</h5>
                </div>
                @if($order->statusHistories->isEmpty())
                    <div class="card-body text-muted">No status history recorded yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Changed By</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->statusHistories as $history)
                                    <tr>
                                        <td>{{ app_datetime($history->created_at) }}</td>
                                        <td>{{ $history->from_status ? $statusLabel($history->from_status) : '-' }}</td>
                                        <td>{{ $statusLabel($history->to_status) }}</td>
                                        <td>{{ $history->changedBy?->name ?? 'System' }}</td>
                                        <td>{{ $history->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8">{{ $order->customer_name ?: 'Customer' }}</dd>
                        <dt class="col-sm-4">Mobile</dt>
                        <dd class="col-sm-8">{{ $order->customer_mobile ?: '-' }}</dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">{{ $order->customer_email ?: '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Totals</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Discount</span><span>{{ $money($order->discount_total) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Shipping</span><span>{{ (float) $order->shipping_total > 0 ? $money($order->shipping_total) : 'FREE' }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Tax</span><span>{{ $money($order->tax_total) }}</span></div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-2"><span>Grand Total</span><span>{{ $money($order->grand_total) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Amount Paid</span><span>{{ $money($order->amount_paid) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Balance</span><span>{{ $money($balance) }}</span></div>
                </div>
            </div>

            @if($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Delivery Address</h5>
                    </div>
                    <div class="card-body">
                        @forelse($shippingLines as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div class="text-muted">No delivery address snapshot stored.</div>
                        @endforelse
                    </div>
                </div>
            @elseif($order->fulfilment_type === \App\Models\Order::FULFILMENT_PICKUP)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pickup Information</h5>
                    </div>
                    <div class="card-body">
                        @foreach($shopAddressLines as $line)
                            <div>{{ $line }}</div>
                        @endforeach
                        @if($pickupInstructions !== '')
                            <div class="text-muted fs-sm mt-2">{{ $pickupInstructions }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Billing Address</h5>
                </div>
                <div class="card-body">
                    @if($billingSameAsShipping)
                        <div class="text-muted">Same as Delivery Address</div>
                    @else
                        @forelse($billingLines as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div class="text-muted">No billing address snapshot stored.</div>
                        @endforelse
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Method</dt>
                        <dd class="col-sm-7">{{ $label($paymentMethods, $order->payment_method) }}</dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">{{ $paymentStatusLabel($order->payment_status) }}</dd>
                        <dt class="col-sm-5">Amount Paid</dt>
                        <dd class="col-sm-7">{{ $money($order->amount_paid) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
