{{-- Purpose: Read-only merchant order-processing workspace for operational orders. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Order {{ $order->order_number }}"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Orders' => route('merchant.orders.index'), $order->order_number => null]"
    />
@endsection

@push('styles')
    <style>
        .order-workspace-grid {
            display: grid;
            grid-template-columns: minmax(0, 2.15fr) minmax(300px, .85fr);
            gap: 1rem;
            align-items: start;
        }

        .order-hero {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .order-action-slot {
            min-width: 220px;
            min-height: 36px;
        }

        .order-meta-line {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .65rem;
            align-items: center;
        }

        .order-progress {
            display: grid;
            grid-template-columns: repeat(var(--progress-count), minmax(110px, 1fr));
            gap: .5rem;
            overflow-x: auto;
            padding-bottom: .25rem;
        }

        .order-progress-step {
            position: relative;
            min-width: 110px;
            padding-top: 1.8rem;
            color: var(--bs-secondary-color);
        }

        .order-progress-step::before {
            content: "";
            position: absolute;
            top: .55rem;
            left: 1rem;
            right: -1.5rem;
            height: 2px;
            background: var(--bs-border-color);
        }

        .order-progress-step:last-child::before {
            display: none;
        }

        .order-progress-dot {
            position: absolute;
            top: .2rem;
            left: .35rem;
            width: .85rem;
            height: .85rem;
            border-radius: 50%;
            border: 2px solid var(--bs-border-color);
            background: var(--bs-body-bg);
            z-index: 1;
        }

        .order-progress-step.is-complete,
        .order-progress-step.is-current {
            color: var(--bs-body-color);
        }

        .order-progress-step.is-complete::before {
            background: var(--bs-success);
        }

        .order-progress-step.is-complete .order-progress-dot {
            border-color: var(--bs-success);
            background: var(--bs-success);
        }

        .order-progress-step.is-current .order-progress-dot {
            border-color: var(--bs-primary);
            background: var(--bs-primary);
            box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .14);
        }

        .order-info-list {
            display: grid;
            gap: .7rem;
        }

        .order-money-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .55rem;
        }

        .order-activity {
            display: grid;
            gap: 0;
        }

        .order-activity-item {
            position: relative;
            padding-left: 1.75rem;
            padding-bottom: 1.2rem;
        }

        .order-activity-item::before {
            content: "";
            position: absolute;
            left: .42rem;
            top: .95rem;
            bottom: 0;
            width: 1px;
            background: var(--bs-border-color);
        }

        .order-activity-item:last-child {
            padding-bottom: 0;
        }

        .order-activity-item:last-child::before {
            display: none;
        }

        .order-activity-dot {
            position: absolute;
            left: 0;
            top: .2rem;
            width: .85rem;
            height: .85rem;
            border-radius: 50%;
            background: var(--bs-primary);
        }

        @media (max-width: 1199.98px) {
            .order-workspace-grid {
                grid-template-columns: 1fr;
            }

            .order-action-slot {
                display: none;
            }
        }

        @media (max-width: 767.98px) {
            .order-hero {
                display: block;
            }

            .order-progress {
                grid-template-columns: 1fr;
                overflow: visible;
            }

            .order-progress-step {
                min-width: 0;
                padding-top: 0;
                padding-left: 1.75rem;
                padding-bottom: 1rem;
            }

            .order-progress-step::before {
                left: .75rem;
                top: 1rem;
                bottom: -.25rem;
                right: auto;
                width: 2px;
                height: auto;
            }

            .order-progress-dot {
                top: .25rem;
                left: .35rem;
            }
        }
    </style>
@endpush

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
        $compactLocation = static function (array $parts): string {
            return collect($parts)->filter(fn ($value) => filled($value))->implode(', ');
        };
        $shippingLines = $formatAddress([
            $order->shipping_recipient_name,
            trim((string) $order->shipping_mobile_country_code.' '.(string) $order->shipping_mobile),
            $order->shipping_address_line_1,
            $order->shipping_address_line_2,
            $order->shipping_landmark,
            $compactLocation([$order->shipping_city, $order->shipping_state, $order->shipping_country, $order->shipping_postal_code]),
        ]);
        $billingLines = $formatAddress([
            $order->billing_recipient_name,
            trim((string) $order->billing_mobile_country_code.' '.(string) $order->billing_mobile),
            $order->billing_address_line_1,
            $order->billing_address_line_2,
            $order->billing_landmark,
            $compactLocation([$order->billing_city, $order->billing_state, $order->billing_country, $order->billing_postal_code]),
        ]);
        $billingSameAsShipping = $order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY && $shippingLines === $billingLines && count($billingLines) > 0;
        $shopAddressLines = $formatAddress([
            $activeShop->name,
            $activeShop->address_line_1,
            $activeShop->address_line_2,
            $activeShop->landmark,
            $compactLocation([$activeShop->city?->name, $activeShop->state?->name, $activeShop->country?->name, $activeShop->pincode]),
        ]);
        $pickupInstructions = (string) $activeShop->setting('fulfillment', 'pickup_instructions', '');
        $deliveryEstimateMin = (int) $activeShop->setting('fulfillment', 'delivery_estimate_min_days', 0);
        $deliveryEstimateMax = (int) $activeShop->setting('fulfillment', 'delivery_estimate_max_days', 0);
        $deliveryEstimate = null;
        if ($deliveryEstimateMin > 0 || $deliveryEstimateMax > 0) {
            $minLabel = $deliveryEstimateMin <= 0 ? 'Same day' : $deliveryEstimateMin.' '.Str::plural('day', $deliveryEstimateMin);
            $maxLabel = $deliveryEstimateMax <= 0 ? null : $deliveryEstimateMax.' '.Str::plural('day', $deliveryEstimateMax);
            $deliveryEstimate = $maxLabel && $maxLabel !== $minLabel ? $minLabel.' to '.$maxLabel : $minLabel;
        }
        $progressSteps = $order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY
            ? [
                \App\Models\Order::STATUS_PENDING => 'Order Placed',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmed',
                \App\Models\Order::STATUS_PROCESSING => 'Processing',
                \App\Models\OrderStatus::CODE_PACKED => 'Packed',
                \App\Models\OrderStatus::CODE_SHIPPED => 'Shipped',
                \App\Models\OrderStatus::CODE_OUT_FOR_DELIVERY => 'Out for Delivery',
                \App\Models\OrderStatus::CODE_DELIVERED => 'Delivered',
                \App\Models\Order::STATUS_COMPLETED => 'Completed',
            ]
            : [
                \App\Models\Order::STATUS_PENDING => 'Order Placed',
                \App\Models\Order::STATUS_CONFIRMED => 'Confirmed',
                \App\Models\Order::STATUS_PROCESSING => 'Processing',
                \App\Models\Order::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
                \App\Models\Order::STATUS_COMPLETED => 'Completed',
            ];
        $progressCodes = array_keys($progressSteps);
        $currentStepIndex = array_search($order->order_status, $progressCodes, true);
        $activityItems = $order->statusHistories->sortBy('created_at')->values();
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="order-hero">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h3 class="mb-0">{{ $order->order_number }}</h3>
                        <span class="badge {{ $statusClass($order->order_status) }} bg-opacity-10 text-body">{{ $statusLabel($order->order_status) }}</span>
                    </div>
                    <div class="text-muted mb-2">Placed {{ app_datetime($order->created_at) }}</div>
                    <div class="order-meta-line fw-semibold">
                        <span>{{ $label($sourceLabels, $order->created_source) }}</span>
                        <span class="text-muted">/</span>
                        <span>{{ $label($fulfillmentTypes, $order->fulfilment_type) }}</span>
                        <span class="text-muted">/</span>
                        <span>{{ $label($paymentMethods, $order->payment_method) }}</span>
                    </div>
                </div>
                <div class="order-action-slot"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Order Progress</h5>
        </div>
        <div class="card-body">
            <div class="order-progress" style="--progress-count: {{ count($progressSteps) }}">
                @foreach($progressSteps as $code => $stepLabel)
                    @php
                        $stepIndex = array_search($code, $progressCodes, true);
                        $stepClass = $currentStepIndex === false
                            ? ''
                            : ($stepIndex < $currentStepIndex ? 'is-complete' : ($stepIndex === $currentStepIndex ? 'is-current' : ''));
                    @endphp
                    <div class="order-progress-step {{ $stepClass }}">
                        <span class="order-progress-dot"></span>
                        <div class="fw-semibold">{{ $stepLabel }}</div>
                    </div>
                @endforeach
            </div>
            @if($currentStepIndex === false)
                <div class="text-muted fs-sm mt-2">This status is outside the standard {{ $label($fulfillmentTypes, $order->fulfilment_type) }} progress path.</div>
            @endif
        </div>
    </div>

    <div class="order-workspace-grid">
        <div>
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
                    <h5 class="mb-0">Order Activity</h5>
                </div>
                <div class="card-body">
                    @if($activityItems->isEmpty())
                        <div class="text-muted">No activity recorded yet.</div>
                    @else
                        <div class="order-activity">
                            @foreach($activityItems as $history)
                                <div class="order-activity-item">
                                    <span class="order-activity-dot"></span>
                                    <div class="fw-semibold">{{ $history->from_status ? $statusLabel($history->to_status) : 'Order Placed' }}</div>
                                    <div class="text-muted fs-sm">{{ app_datetime($history->created_at) }}</div>
                                    @if($history->notes)
                                        <div class="mt-1">{{ $history->notes }}</div>
                                    @endif
                                    <div class="text-muted fs-sm mt-1">{{ $history->changedBy?->name ? 'Updated by '.$history->changedBy->name : 'System' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Notes / Comments</h5>
                </div>
                <div class="card-body text-muted">
                    Internal and customer-visible notes will be managed here in a later workflow step.
                </div>
            </div>
        </div>

        <aside>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer</h5>
                </div>
                <div class="card-body order-info-list">
                    <div>
                        <div class="fw-semibold">{{ $order->customer_name ?: 'Customer' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-sm">Mobile</div>
                        <div>{{ $order->customer_mobile ?: '-' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-sm">Email</div>
                        <div>{{ $order->customer_email ?: '-' }}</div>
                    </div>
                </div>
            </div>

            @if($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Delivery Information</h5>
                    </div>
                    <div class="card-body">
                        @forelse($shippingLines as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div class="text-muted">No delivery address snapshot stored.</div>
                        @endforelse
                        @if($deliveryEstimate)
                            <div class="text-muted fs-sm mt-3">Estimated Delivery</div>
                            <div>{{ $deliveryEstimate }}</div>
                        @endif
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
                            <div class="text-muted fs-sm mt-3">Pickup Instructions</div>
                            <div>{{ $pickupInstructions }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment</h5>
                </div>
                <div class="card-body">
                    <div class="fw-semibold mb-2">{{ $label($paymentMethods, $order->payment_method) }}</div>
                    <div class="mb-3">
                        <span class="badge {{ $paymentStatusClass($order->payment_status) }} bg-opacity-10 text-body">{{ $paymentStatusLabel($order->payment_status) }}</span>
                    </div>
                    <div class="order-money-row"><span>Order Total</span><span class="fw-semibold">{{ $money($order->grand_total) }}</span></div>
                    <div class="order-money-row"><span>Amount Paid</span><span>{{ $money($order->amount_paid) }}</span></div>
                    <div class="order-money-row mb-0"><span>Balance</span><span class="fw-semibold">{{ $money($balance) }}</span></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="order-money-row"><span>Subtotal</span><span>{{ $money($order->subtotal) }}</span></div>
                    <div class="order-money-row"><span>Discount</span><span>{{ $money($order->discount_total) }}</span></div>
                    <div class="order-money-row"><span>Shipping</span><span>{{ (float) $order->shipping_total > 0 ? $money($order->shipping_total) : 'FREE' }}</span></div>
                    <div class="order-money-row"><span>Tax</span><span>{{ $money($order->tax_total) }}</span></div>
                    <hr>
                    <div class="order-money-row mb-0 fs-5 fw-bold"><span>Grand Total</span><span>{{ $money($order->grand_total) }}</span></div>
                </div>
            </div>

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
        </aside>
    </div>
@endsection
