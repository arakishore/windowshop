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
            gap: 0;
            overflow-x: auto;
            padding: .25rem .25rem .5rem;
        }

        .order-progress-step {
            position: relative;
            min-width: 110px;
            padding: 2.25rem .75rem 0;
            color: var(--bs-secondary-color);
            text-align: center;
        }

        .order-progress-step::before {
            content: "";
            position: absolute;
            top: .85rem;
            left: calc(50% + 1rem);
            right: calc(-50% + 1rem);
            height: 3px;
            background: var(--bs-border-color);
        }

        .order-progress-step:last-child::before {
            display: none;
        }

        .order-progress-dot {
            position: absolute;
            top: .2rem;
            left: 50%;
            width: 1.4rem;
            height: 1.4rem;
            transform: translateX(-50%);
            border-radius: 50%;
            border: 2px solid var(--bs-border-color);
            background: var(--bs-body-bg);
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1;
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
            color: var(--bs-white);
        }

        .order-progress-step.is-current .order-progress-dot {
            border-color: var(--bs-primary);
            background: var(--bs-primary);
            color: var(--bs-white);
            box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .14);
        }

        .order-progress-label {
            display: block;
            font-weight: 600;
            line-height: 1.25;
            white-space: nowrap;
        }

        .order-progress-meta {
            min-height: 1.05rem;
            margin-top: .25rem;
            color: var(--bs-secondary-color);
            font-size: var(--body-font-size-sm);
            line-height: 1.25;
        }

        .order-progress-step.is-current .order-progress-label {
            color: var(--bs-primary);
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

        @media (max-width: 1199.98px) {
            .order-workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .order-hero {
                display: block;
            }

            .order-action-slot {
                margin-top: 1rem;
            }

            .order-progress {
                grid-template-columns: 1fr;
                overflow: visible;
                padding: .25rem 0;
            }

            .order-progress-step {
                min-width: 0;
                padding-top: 0;
                padding-left: 2.25rem;
                padding-bottom: 1rem;
                text-align: left;
            }

            .order-progress-step::before {
                left: .7rem;
                top: 1.45rem;
                bottom: -.25rem;
                right: auto;
                width: 2px;
                height: auto;
            }

            .order-progress-dot {
                top: 0;
                left: 0;
                transform: none;
            }

            .order-progress-label {
                white-space: normal;
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
        $historyByStatus = $activityItems
            ->filter(fn ($history) => in_array($history->to_status, $progressCodes, true))
            ->reject(fn ($history) => ($history->metadata['action'] ?? null) === 'merchant_cod_payment_received')
            ->keyBy('to_status');
        $canAcceptOrder = in_array(\App\Models\Order::STATUS_CONFIRMED, $allowedNextStatuses, true);
        $canStartProcessing = in_array(\App\Models\Order::STATUS_PROCESSING, $allowedNextStatuses, true);
        $canMarkReadyForPickup = in_array(\App\Models\Order::STATUS_READY_FOR_PICKUP, $allowedNextStatuses, true);
        $canMarkPacked = in_array(\App\Models\OrderStatus::CODE_PACKED, $allowedNextStatuses, true);
        $canMarkShipped = in_array(\App\Models\OrderStatus::CODE_SHIPPED, $allowedNextStatuses, true);
        $canMarkOutForDelivery = in_array(\App\Models\OrderStatus::CODE_OUT_FOR_DELIVERY, $allowedNextStatuses, true);
        $canMarkDelivered = in_array(\App\Models\OrderStatus::CODE_DELIVERED, $allowedNextStatuses, true);
        $canCompletePickup = $order->fulfilment_type === \App\Models\Order::FULFILMENT_PICKUP && in_array(\App\Models\Order::STATUS_COMPLETED, $allowedNextStatuses, true);
        $canCancelOrder = in_array(\App\Models\Order::STATUS_CANCELLED, $allowedNextStatuses, true);
        $acceptOrderLabel = $statusActionLabels[\App\Models\Order::STATUS_CONFIRMED] ?? 'Accept Order';
        $startProcessingLabel = $statusActionLabels[\App\Models\Order::STATUS_PROCESSING] ?? 'Start Processing';
        $markReadyForPickupLabel = $statusActionLabels[\App\Models\Order::STATUS_READY_FOR_PICKUP] ?? 'Mark Ready for Pickup';
        $markPackedLabel = $statusActionLabels[\App\Models\OrderStatus::CODE_PACKED] ?? 'Mark Packed';
        $markShippedLabel = $statusActionLabels[\App\Models\OrderStatus::CODE_SHIPPED] ?? 'Mark Shipped';
        $markOutForDeliveryLabel = $statusActionLabels[\App\Models\OrderStatus::CODE_OUT_FOR_DELIVERY] ?? 'Mark Out for Delivery';
        $markDeliveredLabel = $statusActionLabels[\App\Models\OrderStatus::CODE_DELIVERED] ?? 'Mark Delivered';
        $completePickupLabel = $statusActionLabels[\App\Models\Order::STATUS_COMPLETED] ?? 'Complete Pickup';
        $cancelOrderLabel = $statusActionLabels[\App\Models\Order::STATUS_CANCELLED] ?? 'Cancel Order';
        $requiresPickupPaymentConfirmation = $canCompletePickup && $order->payment_method === 'cash_at_shop' && $order->payment_status !== \App\Models\Order::PAYMENT_PAID;
        $requiresDeliveryCodPaymentConfirmation = $canMarkDelivered && $order->payment_method === 'cash_on_delivery' && $order->payment_status !== \App\Models\Order::PAYMENT_PAID;
    @endphp

    @if($errors->any())
        <div class="alert alert-danger">
            Please review the highlighted order action fields.
        </div>
    @endif

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
                <div class="order-action-slot">
                    @if($canAcceptOrder || $canStartProcessing || $canMarkReadyForPickup || $canMarkPacked || $canMarkShipped || $canMarkOutForDelivery || $canMarkDelivered || $canCompletePickup || $canCancelOrder)
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            @if($canCancelOrder)
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                    {{ $cancelOrderLabel }}
                                </button>
                            @endif
                            @if($canAcceptOrder)
                                <form method="POST" action="{{ route('merchant.orders.accept', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $acceptOrderLabel }}</button>
                                </form>
                            @endif
                            @if($canStartProcessing)
                                <form method="POST" action="{{ route('merchant.orders.processing', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $startProcessingLabel }}</button>
                                </form>
                            @endif
                            @if($canMarkReadyForPickup)
                                <form method="POST" action="{{ route('merchant.orders.ready-for-pickup', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $markReadyForPickupLabel }}</button>
                                </form>
                            @endif
                            @if($canMarkPacked)
                                <form method="POST" action="{{ route('merchant.orders.packed', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $markPackedLabel }}</button>
                                </form>
                            @endif
                            @if($canMarkShipped)
                                <form method="POST" action="{{ route('merchant.orders.ship', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $markShippedLabel }}</button>
                                </form>
                            @endif
                            @if($canMarkOutForDelivery)
                                <form method="POST" action="{{ route('merchant.orders.out-for-delivery', $order) }}" data-submit-once>
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ $markOutForDeliveryLabel }}</button>
                                </form>
                            @endif
                            @if($canMarkDelivered)
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markDeliveredModal">
                                    {{ $markDeliveredLabel }}
                                </button>
                            @endif
                            @if($canCompletePickup)
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#completePickupModal">
                                    {{ $completePickupLabel }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($canCancelOrder)
        <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('merchant.orders.cancel', $order) }}" class="modal-content" data-submit-once>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelOrderModalLabel">Cancel Order?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to cancel {{ $order->order_number }}?</p>

                        @if($cancellationReasons->isEmpty())
                            <div class="alert alert-warning mb-0">
                                No active merchant cancellation reasons are available. Add a cancellation reason before cancelling this order.
                            </div>
                        @else
                            <div class="mb-3">
                                <label for="cancellation_reason_id" class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                                <select id="cancellation_reason_id" name="cancellation_reason_id" class="form-select @error('cancellation_reason_id') is-invalid @enderror" required>
                                    <option value="">Select reason</option>
                                    @foreach($cancellationReasons as $reason)
                                        <option value="{{ $reason->getKey() }}" data-requires-comment="{{ $reason->requires_comment ? 1 : 0 }}" @selected(old('cancellation_reason_id') == $reason->getKey())>
                                            {{ $reason->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cancellation_reason_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div>
                                <label for="cancellation_note" class="form-label">Additional Note</label>
                                <textarea id="cancellation_note" name="cancellation_note" rows="3" class="form-control @error('cancellation_note') is-invalid @enderror" maxlength="1000">{{ old('cancellation_note') }}</textarea>
                                @error('cancellation_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" class="btn btn-danger" @disabled($cancellationReasons->isEmpty())>{{ $cancelOrderLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canCompletePickup)
        <div class="modal fade" id="completePickupModal" tabindex="-1" aria-labelledby="completePickupModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('merchant.orders.complete-pickup', $order) }}" class="modal-content" data-submit-once>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="completePickupModalLabel">Complete Pickup?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Confirm that the customer has collected {{ $order->order_number }}.</p>
                        <div class="order-info-list mb-3">
                            <div>
                                <div class="text-muted fs-sm">Payment Method</div>
                                <div class="fw-semibold">{{ $label($paymentMethods, $order->payment_method) }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-sm">Amount to Collect</div>
                                <div class="fw-semibold">{{ $money($balance) }}</div>
                            </div>
                        </div>

                        @if($requiresPickupPaymentConfirmation)
                            <div class="form-check">
                                <input id="payment_received" name="payment_received" type="checkbox" value="1" class="form-check-input @error('payment_received') is-invalid @enderror" required @checked(old('payment_received'))>
                                <label for="payment_received" class="form-check-label">Payment received</label>
                                @error('payment_received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <div class="text-muted fs-sm">Payment is already marked paid. No payment update will be made.</div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" class="btn btn-primary">{{ $completePickupLabel }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canMarkDelivered)
        <div class="modal fade" id="markDeliveredModal" tabindex="-1" aria-labelledby="markDeliveredModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('merchant.orders.deliver', $order) }}" class="modal-content" data-submit-once>
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="markDeliveredModalLabel">Mark Order as Delivered?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Confirm that the customer has received {{ $order->order_number }}.</p>
                        <div class="order-info-list">
                            <div>
                                <div class="text-muted fs-sm">Payment Method</div>
                                <div class="fw-semibold">{{ $label($paymentMethods, $order->payment_method) }}</div>
                            </div>
                            <div>
                                <div class="text-muted fs-sm">{{ $requiresDeliveryCodPaymentConfirmation ? 'Amount Due' : 'Payment Status' }}</div>
                                <div class="fw-semibold">{{ $requiresDeliveryCodPaymentConfirmation ? $money($balance) : $paymentStatusLabel($order->payment_status) }}</div>
                            </div>
                        </div>
                        @if($requiresDeliveryCodPaymentConfirmation)
                            <div class="form-check mt-3">
                                <input id="delivery_payment_received" name="payment_received" type="checkbox" value="1" class="form-check-input @error('payment_received') is-invalid @enderror" required @checked(old('payment_received'))>
                                <label for="delivery_payment_received" class="form-check-label">I confirm the COD payment has been received.</label>
                                @error('payment_received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" class="btn btn-primary">Confirm Delivered</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                            : ($stepIndex < $currentStepIndex || ($stepIndex === $currentStepIndex && $code === \App\Models\Order::STATUS_COMPLETED) ? 'is-complete' : ($stepIndex === $currentStepIndex ? 'is-current' : ''));
                        $stepHistory = $historyByStatus->get($code);
                    @endphp
                    <div class="order-progress-step {{ $stepClass }}">
                        <span class="order-progress-dot">{{ $stepClass === 'is-complete' ? '✓' : $stepIndex + 1 }}</span>
                        <span class="order-progress-label">{{ $stepLabel }}</span>
                        <div class="order-progress-meta">
                            @if($stepClass === 'is-current')
                                <span class="badge bg-primary bg-opacity-10 text-primary">Current</span>
                            @elseif($stepHistory)
                                {{ app_datetime($stepHistory->created_at) }}
                            @else
                                &nbsp;
                            @endif
                        </div>
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
                                        <div class="fw-semibold">
                                            @if($item->product)
                                                <a href="{{ route('merchant.products.edit', $item->product) }}" class="text-body" target="_blank" rel="noopener">
                                                    {{ $item->product_name }}
                                                </a>
                                            @else
                                                {{ $item->product_name }}
                                            @endif
                                        </div>
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
                        <div class="list-feed">
                            @foreach($activityItems as $history)
                                <div class="list-feed-item border-warning">
                                    @php
                                        $activityLabel = ($history->metadata['action'] ?? null) === 'merchant_cod_payment_received'
                                            ? 'Payment Received'
                                            : ($history->from_status ? $statusLabel($history->to_status) : 'Order Placed');
                                    @endphp
                                    <div class="text-muted fs-sm mb-1">{{ app_datetime($history->created_at) }}</div>
                                    <div class="fw-semibold mb-1">{{ $activityLabel }}</div>
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
                        <div class="text-muted fs-sm">Delivery Address</div>
                        @forelse($shippingLines as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div class="text-muted">No delivery address snapshot stored.</div>
                        @endforelse
                        @if($order->shipping_postal_code)
                            <div class="text-muted fs-sm mt-3">PIN</div>
                            <div>{{ $order->shipping_postal_code }}</div>
                        @endif
                        <div class="text-muted fs-sm mt-3">Delivery Charge</div>
                        <div>{{ (float) $order->shipping_total > 0 ? $money($order->shipping_total) : 'FREE' }}</div>
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

@push('scripts')
    <script>
        document.querySelectorAll('[data-submit-once]').forEach((form) => {
            form.addEventListener('submit', () => {
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.disabled = true;
                });
            });
        });

        const cancellationReason = document.getElementById('cancellation_reason_id');
        const cancellationNote = document.getElementById('cancellation_note');
        const applyCancellationRequirement = () => {
            if (!cancellationReason || !cancellationNote) {
                return;
            }

            const selected = cancellationReason.options[cancellationReason.selectedIndex];
            cancellationNote.required = selected?.dataset.requiresComment === '1';
        };

        cancellationReason?.addEventListener('change', applyCancellationRequirement);
        applyCancellationRequirement();

        @if($errors->has('cancellation_reason_id') || $errors->has('cancellation_note'))
            const cancelOrderModal = document.getElementById('cancelOrderModal');
            if (cancelOrderModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(cancelOrderModal).show();
            }
        @endif

        @if($errors->has('payment_received') || $errors->has('payment_status'))
            const completePickupModal = document.getElementById('completePickupModal');
            if (completePickupModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(completePickupModal).show();
            }

            const markDeliveredModal = document.getElementById('markDeliveredModal');
            if (markDeliveredModal && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(markDeliveredModal).show();
            }
        @endif
    </script>
@endpush
