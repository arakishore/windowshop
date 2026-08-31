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

        .order-note-box {
            white-space: pre-line;
            color: var(--bs-body-color);
        }

        .order-comment-options {
            display: grid;
            gap: .75rem;
        }

        .order-stock-shortage-list {
            display: grid;
            gap: .75rem;
        }

        .order-stock-shortage-item {
            border: 1px solid rgba(var(--bs-warning-rgb), .45);
            border-radius: .5rem;
            padding: .85rem 1rem;
            background: rgba(var(--bs-warning-rgb), .08);
        }

        .order-return-exchange-list {
            display: grid;
            gap: .85rem;
        }

        .order-return-exchange-item {
            border: 1px solid var(--bs-border-color);
            border-radius: .5rem;
            padding: 1rem;
            background: var(--bs-body-bg);
        }

        .order-return-exchange-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }

        .order-return-exchange-label {
            color: var(--bs-secondary-color);
            font-size: var(--body-font-size-sm);
            margin-bottom: .2rem;
        }

        @media (max-width: 991.98px) {
            .order-return-exchange-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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
        $statusActivityItems = $order->statusHistories
            ->map(fn ($history) => ['type' => 'status', 'created_at' => $history->created_at, 'record' => $history]);
        $commentActivityItems = $order->comments
            ->map(fn ($comment) => ['type' => 'comment', 'created_at' => $comment->created_at, 'record' => $comment]);
        $activityItems = $statusActivityItems
            ->merge($commentActivityItems)
            ->sortBy('created_at')
            ->values();
        $historyByStatus = $order->statusHistories
            ->sortBy('created_at')
            ->values()
            ->filter(fn ($history) => in_array($history->to_status, $progressCodes, true))
            ->reject(fn ($history) => ($history->metadata['action'] ?? null) === 'merchant_cod_payment_received')
            ->keyBy('to_status');
        $commentVisibilityLabels = (array) config('order_comments.visibilities', []);
        $commentVisibilityLabel = static fn (string|null $visibility): string => $visibility ? ($commentVisibilityLabels[$visibility] ?? Str::headline($visibility)) : '-';
        $commentChannels = static function (\App\Models\OrderComment $comment): array {
            $channels = [];
            if ($comment->notify_email) {
                $channels[] = 'Email';
            }
            if ($comment->notify_sms) {
                $channels[] = 'SMS';
            }
            if ($comment->notify_whatsapp) {
                $channels[] = 'WhatsApp';
            }

            return $channels;
        };
        $oldVisibility = old('visibility', \App\Models\OrderComment::VISIBILITY_MERCHANT_ONLY);
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
            {{ $errors->has('stock_shortage') ? $errors->first('stock_shortage') : 'Please review the highlighted order action fields.' }}
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
                                <form method="POST" action="{{ route('merchant.orders.accept', $order) }}" data-submit-once data-stock-shortage-accept-form>
                                    @csrf
                                    <input type="hidden" name="confirm_stock_shortage" value="0" data-stock-shortage-confirmation>
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

    @if($canAcceptOrder && $stockShortage['has_shortage'])
        <div class="modal fade" id="acceptStockShortageModal" tabindex="-1" aria-labelledby="acceptStockShortageModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="acceptStockShortageModalLabel">Accept Order with Stock Shortage?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>This order currently has insufficient stock. By accepting it, you confirm stock will be arranged before fulfilment.</p>
                        <div class="order-stock-shortage-list">
                            @foreach($stockShortage['items'] as $shortageItem)
                                <div class="order-stock-shortage-item">
                                    <div class="fw-semibold">{{ $shortageItem['product_name'] }}</div>
                                    @if(filled($shortageItem['variant_name'] ?? null) || filled($shortageItem['sku'] ?? null))
                                        <div class="text-muted fs-sm">
                                            {{ collect([$shortageItem['variant_name'] ?? null, $shortageItem['sku'] ?? null])->filter(fn ($value) => filled($value))->implode(' / ') }}
                                        </div>
                                    @endif
                                    <div class="row g-2 mt-2">
                                        <div class="col-4">
                                            <div class="text-muted fs-sm">Ordered</div>
                                            <div class="fw-semibold">{{ rtrim(rtrim(number_format((float) $shortageItem['ordered_quantity'], 3, '.', ''), '0'), '.') }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted fs-sm">In Stock</div>
                                            <div class="fw-semibold">{{ rtrim(rtrim(number_format((float) $shortageItem['display_available_stock'], 3, '.', ''), '0'), '.') }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted fs-sm">Short</div>
                                            <div class="fw-semibold">{{ $shortageItem['short_quantity'] === null ? 'Outstanding' : rtrim(rtrim(number_format((float) $shortageItem['short_quantity'], 3, '.', ''), '0'), '.') }}</div>
                                        </div>
                                    </div>
                                    <div class="text-muted fs-sm mt-2">{{ $shortageItem['message'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Go Back</button>
                        <button type="button" class="btn btn-primary" data-confirm-stock-shortage-accept>Accept Order</button>
                    </div>
                </div>
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
                @if($stockShortage['has_shortage'])
                    <div class="card-body border-bottom">
                        <div class="alert alert-warning mb-0">
                            <div class="fw-semibold mb-2">
                                <i class="ph-warning-circle me-1"></i>
                                Stock Shortage
                            </div>
                            <div class="order-stock-shortage-list">
                                @foreach($stockShortage['items'] as $shortageItem)
                                    <div>
                                        <div class="fw-semibold">{{ $shortageItem['product_name'] }}</div>
                                        <div class="text-muted fs-sm">
                                            Ordered Qty: {{ rtrim(rtrim(number_format((float) $shortageItem['ordered_quantity'], 3, '.', ''), '0'), '.') }}
                                            / Currently In Stock: {{ rtrim(rtrim(number_format((float) $shortageItem['display_available_stock'], 3, '.', ''), '0'), '.') }}
                                            / Short Qty: {{ $shortageItem['short_quantity'] === null ? 'Outstanding' : rtrim(rtrim(number_format((float) $shortageItem['short_quantity'], 3, '.', ''), '0'), '.') }}
                                        </div>
                                        <div class="fs-sm">{{ $shortageItem['message'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
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

            @if(! empty($returnExchangeEligibility['items']))
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Return / Exchange</h5>
                    </div>
                    <div class="card-body">
                        <div class="order-return-exchange-list">
                            @foreach($order->items as $item)
                                @php
                                    $itemEligibility = $returnExchangeEligibility['items'][$item->getKey()] ?? null;
                                    $refund = $itemEligibility['refund'] ?? null;
                                    $exchange = $itemEligibility['exchange'] ?? null;
                                    $refundExceptionAvailable = (bool) ($refund['merchant_exception_available'] ?? false);
                                    $exchangeExceptionAvailable = (bool) ($exchange['merchant_exception_available'] ?? false);
                                    $policyTextClass = static fn (array $facts): string => $facts['allowed_by_policy'] ? '' : 'text-danger fw-semibold';
                                    $eligibilityTextClass = static fn (array $facts): string => $facts['customer_eligible']
                                        ? 'text-success fw-semibold'
                                        : (in_array($facts['merchant_status'], ['Not Eligible', 'Expired'], true) ? 'text-danger fw-semibold' : 'text-warning fw-semibold');
                                @endphp
                                @continue(! $itemEligibility || ! $refund || ! $exchange)

                                <div class="order-return-exchange-item">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                        <div>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            @if($item->sku || $item->variant_name)
                                                <div class="text-muted fs-sm">{{ $item->sku ?: '-' }}{{ $item->variant_name ? ' / '.$item->variant_name : '' }}</div>
                                            @endif
                                        </div>
                                        @if($refundExceptionAvailable || $exchangeExceptionAvailable)
                                            <span class="badge bg-warning bg-opacity-10 text-warning">Merchant exception available</span>
                                        @endif
                                    </div>

                                    <div class="order-return-exchange-grid">
                                        <div>
                                            <div class="order-return-exchange-label">Customer Policy</div>
                                            <div @class([$policyTextClass($refund)])>Refund: {{ $refund['allowed_by_policy'] ? 'Within '.$refund['window_days'].' '.Str::plural('day', $refund['window_days']) : 'Not Allowed' }}</div>
                                            <div @class([$policyTextClass($exchange)])>Exchange: {{ $exchange['allowed_by_policy'] ? 'Within '.$exchange['window_days'].' '.Str::plural('day', $exchange['window_days']) : 'Not Allowed' }}</div>
                                        </div>
                                        <div>
                                            <div class="order-return-exchange-label">Current Eligibility</div>
                                            <div @class([$eligibilityTextClass($refund)])>Refund: {{ $refund['merchant_status'] }}</div>
                                            <div @class([$eligibilityTextClass($exchange)])>Exchange: {{ $exchange['merchant_status'] }}</div>
                                        </div>
                                        <div>
                                            <div class="order-return-exchange-label">Relevant Dates</div>
                                            @if($returnExchangeEligibility['order']['eligibility_start_label'])
                                                <div>Start: {{ $returnExchangeEligibility['order']['eligibility_start_label'] }}</div>
                                            @else
                                                <div>Window not started</div>
                                            @endif
                                            @if($refund['allowed_by_policy'] && $refund['window_expires_label'])
                                                <div>Refund until: {{ $refund['window_expires_label'] }}</div>
                                            @endif
                                            @if($exchange['allowed_by_policy'] && $exchange['window_expires_label'])
                                                <div>Exchange until: {{ $exchange['window_expires_label'] }}</div>
                                            @endif
                                            @if($refund['window_expired'])
                                                <div class="text-warning">Refund expired by {{ $refund['expired_by_days'] }} {{ Str::plural('day', $refund['expired_by_days']) }}</div>
                                            @endif
                                            @if($exchange['window_expired'])
                                                <div class="text-warning">Exchange expired by {{ $exchange['expired_by_days'] }} {{ Str::plural('day', $exchange['expired_by_days']) }}</div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="order-return-exchange-label">Remaining</div>
                                            <div>Refund: {{ $refund['remaining_quantity'] }} {{ Str::plural('item', $refund['remaining_quantity']) }}</div>
                                            <div>Exchange: {{ $exchange['remaining_quantity'] }} {{ Str::plural('item', $exchange['remaining_quantity']) }}</div>
                                        </div>
                                    </div>

                                    @if($refundExceptionAvailable || $exchangeExceptionAvailable)
                                        <div class="border-top mt-3 pt-3">
                                            <div class="order-return-exchange-label">Merchant Exception</div>
                                            @if($refundExceptionAvailable)
                                                <div class="text-muted fs-sm">{{ $refund['merchant_message'] }}</div>
                                            @endif
                                            @if($exchangeExceptionAvailable)
                                                <div class="text-muted fs-sm">{{ $exchange['merchant_message'] }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(filled($order->customer_order_note))
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Customer Order Note</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-muted fs-sm mb-1">Submitted during checkout</div>
                        <div class="order-note-box">{{ $order->customer_order_note }}</div>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Activity</h5>
                </div>
                <div class="card-body">
                    @if($activityItems->isEmpty())
                        <div class="text-muted">No activity recorded yet.</div>
                    @else
                        <div class="list-feed">
                            @foreach($activityItems as $activityItem)
                                @php
                                    $isCommentActivity = $activityItem['type'] === 'comment';
                                    $activity = $activityItem['record'];
                                @endphp
                                <div class="list-feed-item border-warning">
                                    <div class="text-muted fs-sm mb-1">{{ app_datetime($activity->created_at) }}</div>
                                    @if($isCommentActivity)
                                        @php
                                            $channels = $commentChannels($activity);
                                        @endphp
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <div class="fw-semibold">{{ $activity->visibility === \App\Models\OrderComment::VISIBILITY_CUSTOMER ? 'Customer Comment' : 'Internal Note' }}</div>
                                            <span class="badge bg-light text-body">{{ $commentVisibilityLabel($activity->visibility) }}</span>
                                            @if($activity->notify_customer)
                                                <span class="badge bg-info bg-opacity-10 text-body">Notify via {{ implode(', ', $channels) }}</span>
                                            @endif
                                        </div>
                                        <div class="order-note-box mt-1">{{ $activity->comment }}</div>
                                        <div class="text-muted fs-sm mt-1">{{ $activity->createdBy?->name ? 'Added by '.$activity->createdBy->name : 'Added by system' }}</div>
                                    @else
                                        @php
                                            $activityLabel = ($activity->metadata['action'] ?? null) === 'merchant_cod_payment_received'
                                                ? 'Payment Received'
                                                : ($activity->from_status ? $statusLabel($activity->to_status) : 'Order Placed');
                                        @endphp
                                        <div class="fw-semibold mb-1">{{ $activityLabel }}</div>
                                        @if($activity->notes)
                                            <div class="mt-1">{{ $activity->notes }}</div>
                                        @endif
                                        <div class="text-muted fs-sm mt-1">{{ $activity->changedBy?->name ? 'Updated by '.$activity->changedBy->name : 'System' }}</div>
                                    @endif
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
                <div class="card-body">
                    <form method="POST" action="{{ route('merchant.orders.comments.store', $order) }}" data-submit-once data-order-comment-form>
                        @csrf
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comment <span class="text-danger">*</span></label>
                            <textarea id="comment" name="comment" rows="4" maxlength="1000" class="form-control @error('comment') is-invalid @enderror" required>{{ old('comment') }}</textarea>
                            @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="order-comment-options">
                            <div>
                                <div class="form-label mb-2">Visibility</div>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input id="visibility_merchant_only" name="visibility" type="radio" value="{{ \App\Models\OrderComment::VISIBILITY_MERCHANT_ONLY }}" class="form-check-input" @checked($oldVisibility === \App\Models\OrderComment::VISIBILITY_MERCHANT_ONLY)>
                                        <label for="visibility_merchant_only" class="form-check-label">Merchant Only</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="visibility_customer" name="visibility" type="radio" value="{{ \App\Models\OrderComment::VISIBILITY_CUSTOMER }}" class="form-check-input" @checked($oldVisibility === \App\Models\OrderComment::VISIBILITY_CUSTOMER)>
                                        <label for="visibility_customer" class="form-check-label">Customer Visible</label>
                                    </div>
                                </div>
                                @error('visibility')<div class="text-danger fs-sm mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-check" data-notify-customer-wrap>
                                <input id="notify_customer" name="notify_customer" type="checkbox" value="1" class="form-check-input" data-notify-customer-toggle @checked(old('notify_customer'))>
                                <label for="notify_customer" class="form-check-label">Notify customer</label>
                            </div>

                            <div data-notify-channels-wrap>
                                <div class="form-label mb-2">Notification Channels</div>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input id="notify_email" name="notify_email" type="checkbox" value="1" class="form-check-input" @checked(old('notify_email'))>
                                        <label for="notify_email" class="form-check-label">Email</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="notify_sms" name="notify_sms" type="checkbox" value="1" class="form-check-input" @checked(old('notify_sms'))>
                                        <label for="notify_sms" class="form-check-label">SMS</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="notify_whatsapp" name="notify_whatsapp" type="checkbox" value="1" class="form-check-input" @checked(old('notify_whatsapp'))>
                                        <label for="notify_whatsapp" class="form-check-label">WhatsApp</label>
                                    </div>
                                </div>
                                @error('notify_channels')<div class="text-danger fs-sm mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Add Comment</button>
                        </div>
                    </form>
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
        const stockShortageAcceptForm = document.querySelector('[data-stock-shortage-accept-form]');
        const stockShortageConfirmation = stockShortageAcceptForm?.querySelector('[data-stock-shortage-confirmation]');
        const stockShortageModal = document.getElementById('acceptStockShortageModal');
        const confirmStockShortageAccept = document.querySelector('[data-confirm-stock-shortage-accept]');

        stockShortageAcceptForm?.addEventListener('submit', (event) => {
            if (!stockShortageModal || stockShortageConfirmation?.value === '1') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(stockShortageModal).show();
            }
        }, true);

        confirmStockShortageAccept?.addEventListener('click', () => {
            if (!stockShortageAcceptForm || !stockShortageConfirmation) {
                return;
            }

            stockShortageConfirmation.value = '1';
            stockShortageAcceptForm.requestSubmit();
        });

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

        const commentForm = document.querySelector('[data-order-comment-form]');
        if (commentForm) {
            const visibilityInputs = commentForm.querySelectorAll('input[name="visibility"]');
            const notifyWrap = commentForm.querySelector('[data-notify-customer-wrap]');
            const notifyToggle = commentForm.querySelector('[data-notify-customer-toggle]');
            const channelsWrap = commentForm.querySelector('[data-notify-channels-wrap]');
            const channelInputs = channelsWrap ? channelsWrap.querySelectorAll('input[type="checkbox"]') : [];

            const applyCommentControls = () => {
                const selectedVisibility = commentForm.querySelector('input[name="visibility"]:checked')?.value || '{{ \App\Models\OrderComment::VISIBILITY_MERCHANT_ONLY }}';
                const customerVisible = selectedVisibility === '{{ \App\Models\OrderComment::VISIBILITY_CUSTOMER }}';

                if (notifyWrap && notifyToggle) {
                    notifyWrap.hidden = !customerVisible;
                    notifyToggle.disabled = !customerVisible;
                    if (!customerVisible) {
                        notifyToggle.checked = false;
                    }
                }

                const shouldShowChannels = customerVisible && Boolean(notifyToggle?.checked);
                if (channelsWrap) {
                    channelsWrap.hidden = !shouldShowChannels;
                }

                channelInputs.forEach((input) => {
                    input.disabled = !shouldShowChannels;
                    if (!shouldShowChannels) {
                        input.checked = false;
                    }
                });
            };

            visibilityInputs.forEach((input) => input.addEventListener('change', applyCommentControls));
            notifyToggle?.addEventListener('change', applyCommentControls);
            applyCommentControls();
        }

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
