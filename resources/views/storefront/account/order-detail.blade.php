@extends('storefront.layouts.app')

@section('title', $order->order_number.' | WindowShop')
@section('meta_description', 'WindowShop customer order details.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Order '.$order->order_number])
        @php
            $fulfilmentLabel = $presenter->label($presenter->fulfilmentTypes(), $order->fulfilment_type);
            $paymentMethodLabel = $presenter->label($presenter->paymentMethods(), $order->payment_method);
            $shippingLines = $presenter->shippingLines($order);
            $billingLines = $presenter->billingLines($order);
            $pickupLines = $presenter->pickupLines($order);
            $showBilling = $billingLines !== [] && ! $presenter->billingSameAsShipping($order);
            $activityItems = $presenter->activity($order);
            $cancelledHistory = $order->statusHistories
                ->where('to_status', \App\Models\Order::STATUS_CANCELLED)
                ->sortByDesc('created_at')
                ->first();
        @endphp

        <div class="account-order-detail-head mb-24">
            <div>
                <p class="text-caption-01 cl-text-3 mb-6">Order Detail</p>
                <div class="account-order-title-row">
                    <h4 class="mb-0">{{ $order->order_number }}</h4>
                    <span class="account-status-badge {{ $presenter->statusClass($order->order_status) }}">{{ $presenter->statusLabel($order->order_status) }}</span>
                </div>
                <p class="cl-text-2 mb-0">Placed {{ app_datetime($order->created_at) }}</p>
                <p class="fw-medium mb-0">{{ $order->shop?->name ?? 'WindowShop Store' }}</p>
            </div>
            <div class="account-order-actions">
                @if ($canCancelOrder)
                    <button type="button" class="account-cancel-order-btn" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">Cancel Order</button>
                @endif
                <a href="{{ route('storefront.account.orders') }}" class="tf-btn btn-line small">Back to Orders</a>
            </div>
        </div>

        @if ($canCancelOrder)
            <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('storefront.account.orders.cancel', $order) }}" class="modal-content account-cancel-modal" data-customer-cancel-order-form>
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="cancelOrderModalLabel">Cancel Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to cancel this order?</p>

                            <div class="mb-16">
                                <label for="cancellation_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                <select id="cancellation_reason" name="cancellation_reason" class="form-select @error('cancellation_reason') is-invalid @enderror" required data-customer-cancel-reason>
                                    <option value="">Select reason</option>
                                    @foreach ($cancellationReasons as $reasonValue => $reason)
                                        <option value="{{ $reasonValue }}" data-requires-note="{{ $reason['requires_comment'] ? 1 : 0 }}" @selected(old('cancellation_reason') === $reasonValue)>
                                            {{ $reason['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cancellation_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div>
                                <label for="cancellation_note" class="form-label">Additional Note</label>
                                <textarea id="cancellation_note" name="cancellation_note" rows="3" maxlength="1000" class="form-control @error('cancellation_note') is-invalid @enderror" data-customer-cancel-note>{{ old('cancellation_note') }}</textarea>
                                @error('cancellation_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="account-modal-secondary-btn" data-bs-dismiss="modal">Keep Order</button>
                            <button type="submit" class="account-modal-danger-btn">Cancel Order</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($order->order_status === \App\Models\Order::STATUS_CANCELLED)
            <div class="account-order-alert mb-24">
                <h6 class="mb-6">Order was cancelled{{ $order->cancelled_at ? ' on '.app_datetime($order->cancelled_at) : ($cancelledHistory ? ' on '.app_datetime($cancelledHistory->created_at) : '') }}.</h6>
                @if ($cancelledHistory && filled($cancelledHistory->metadata['reason_name'] ?? null))
                    <p class="mb-0">{{ $cancelledHistory->metadata['reason_name'] }}</p>
                @endif
            </div>
        @endif

        <section class="account-order-section mb-24">
            <h6 class="mb-16">Order Progress</h6>
            <div class="account-progress" style="--progress-count: {{ count($presenter->progress($order)) }}">
                @foreach ($presenter->progress($order) as $index => $step)
                    <div class="account-progress-step is-{{ $step['state'] }}">
                        <span class="account-progress-dot">@if ($step['state'] === 'complete') &#10003; @else {{ $index + 1 }} @endif</span>
                        <span class="account-progress-label">{{ $step['label'] }}</span>
                        <span class="account-progress-meta">
                            @if ($step['state'] === 'current')
                                Current
                            @elseif ($step['timestamp'])
                                {{ $step['timestamp'] }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="account-order-detail-grid">
            <div class="account-order-main">
                <section class="account-order-section">
                    <h6 class="mb-16">Order Items</h6>
                    <div class="account-order-items">
                        @foreach ($order->items as $item)
                            @php($productUrl = $presenter->productUrl($item))
                            <div class="account-order-item">
                                <a href="{{ $productUrl ?? '#;' }}" class="account-order-item-image">
                                    <img src="{{ $presenter->imageUrl($item) }}" alt="{{ $item->product_name }}" loading="lazy">
                                </a>
                                <div class="account-order-item-info">
                                    <a href="{{ $productUrl ?? '#;' }}" class="fw-medium link-underline-text">{{ $item->product_name }}</a>
                                    @if ($item->variant_name)
                                        <p class="text-caption-01 cl-text-3 mb-0">{{ $item->variant_name }}</p>
                                    @endif
                                    @if ($item->sku)
                                        <p class="text-caption-01 cl-text-3 mb-0">SKU: {{ $item->sku }}</p>
                                    @endif
                                    @php($itemEligibility = $returnExchangeEligibility['items'][$item->getKey()] ?? null)
                                    @if ($itemEligibility)
                                        <div class="account-return-policy">
                                            <span>{{ $itemEligibility['refund']['policy_label'] }}</span>
                                            <span>{{ $itemEligibility['exchange']['policy_label'] }}</span>
                                            <span>{{ $itemEligibility['exchange']['remaining_quantity'] }} remaining for exchange</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="account-order-item-money">
                                    <div>{{ $presenter->money($item->unit_price) }} x {{ $item->quantity }}</div>
                                    @if ((float) $item->line_discount > 0)
                                        <div class="text-caption-01 cl-text-3">Discount {{ $presenter->money($item->line_discount) }}</div>
                                    @endif
                                    <strong>{{ $presenter->money($item->line_total) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if (! empty($returnExchangeEligibility['items']))
                    <section class="account-order-section">
                        <h6 class="mb-16">Return / Exchange</h6>
                        <div class="account-return-summary">
                            <p class="mb-0">{{ $returnExchangeEligibility['return_method']['customer_message'] }}</p>
                            @foreach ($order->items as $item)
                                @php($itemEligibility = $returnExchangeEligibility['items'][$item->getKey()] ?? null)
                                @continue(! $itemEligibility)
                                <div class="account-return-row">
                                    <strong>{{ $item->product_name }}</strong>
                                    <div>
                                        <span @class(['is-muted' => ! $itemEligibility['refund']['customer_eligible']])>{{ $itemEligibility['refund']['customer_message'] }}</span>
                                        <span @class(['is-muted' => ! $itemEligibility['exchange']['customer_eligible']])>{{ $itemEligibility['exchange']['customer_message'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($activityItems->isNotEmpty())
                    <section class="account-order-section">
                        <h6 class="mb-16">Order Activity</h6>
                        <div class="account-activity-list">
                            @foreach ($activityItems as $activity)
                                <div class="account-activity-item is-{{ $activity['type'] }} is-{{ $activity['tone'] }}">
                                    <span class="account-activity-dot"></span>
                                    <div>
                                        <p class="text-caption-01 cl-text-3 mb-4">{{ $activity['display_time'] }}</p>
                                        <p class="fw-semibold mb-4">{{ $activity['title'] }}</p>
                                        @if (filled($activity['description']))
                                            <p class="mb-0">{{ $activity['description'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="account-order-side">
                <section class="account-order-section">
                    <h6 class="mb-16">Order Totals</h6>
                    <div class="account-total-list">
                        @forelse ($order->totals as $total)
                            <div class="account-total-row {{ $total->code === \App\Models\OrderTotal::CODE_GRAND_TOTAL ? 'is-grand' : '' }}">
                                <span>{{ $total->title }}</span>
                                <strong>{{ $presenter->money($total->amount) }}</strong>
                            </div>
                        @empty
                            <div class="account-total-row"><span>Subtotal</span><strong>{{ $presenter->money($order->subtotal) }}</strong></div>
                            @if ((float) $order->discount_total > 0)
                                <div class="account-total-row"><span>Discount</span><strong>-{{ $presenter->money($order->discount_total) }}</strong></div>
                            @endif
                            @if ((float) $order->shipping_total > 0)
                                <div class="account-total-row"><span>Delivery</span><strong>{{ $presenter->money($order->shipping_total) }}</strong></div>
                            @endif
                            @if ((float) $order->tax_total > 0)
                                <div class="account-total-row"><span>Tax</span><strong>{{ $presenter->money($order->tax_total) }}</strong></div>
                            @endif
                            <div class="account-total-row is-grand"><span>Grand Total</span><strong>{{ $presenter->money($order->grand_total) }}</strong></div>
                        @endforelse
                    </div>
                </section>

                <section class="account-order-section">
                    <h6 class="mb-16">Payment</h6>
                    <div class="account-info-list">
                        <div><span>Method</span><strong>{{ $paymentMethodLabel }}</strong></div>
                        <div><span>Status</span><strong>{{ $presenter->paymentStatusLabel($order->payment_status) }}</strong></div>
                        <div><span>Amount Paid</span><strong>{{ $presenter->money($order->amount_paid) }}</strong></div>
                        <div><span>Balance</span><strong>{{ $presenter->money($presenter->balance($order)) }}</strong></div>
                    </div>
                </section>

                <section class="account-order-section">
                    <h6 class="mb-16">Fulfilment</h6>
                    <div class="account-info-list mb-12">
                        <div><span>Type</span><strong>{{ $fulfilmentLabel }}</strong></div>
                    </div>
                    @if ($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
                        <div class="account-address-snapshot">
                            <strong>Shipping Address</strong>
                            @forelse ($shippingLines as $line)
                                <span>{{ $line }}</span>
                            @empty
                                <span>No shipping address was stored for this order.</span>
                            @endforelse
                        </div>
                    @else
                        <div class="account-address-snapshot">
                            <strong>Pickup From</strong>
                            @forelse ($pickupLines as $line)
                                <span>{{ $line }}</span>
                            @empty
                                <span>Pickup details are not available.</span>
                            @endforelse
                        </div>
                    @endif
                </section>

                @if ($showBilling)
                    <section class="account-order-section">
                        <h6 class="mb-16">Billing Address</h6>
                        <div class="account-address-snapshot">
                            @foreach ($billingLines as $line)
                                <span>{{ $line }}</span>
                            @endforeach
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    @endcomponent
@endsection

@push('styles')
    <style>
        .account-order-detail-head,
        .account-order-title-row,
        .account-order-actions {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .account-order-actions {
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .account-cancel-order-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 16px;
            border: 1px solid rgba(220, 53, 69, .35);
            border-radius: 6px;
            background: #fff;
            color: #dc3545;
            font-weight: 700;
            line-height: 1.2;
        }

        .account-cancel-order-btn:hover {
            border-color: #dc3545;
            background: rgba(220, 53, 69, .08);
            color: #b02a37;
        }

        .account-cancel-modal {
            border: 0;
            border-radius: 8px;
            overflow: hidden;
        }

        .account-cancel-modal .modal-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid #eef0f3;
        }

        .account-cancel-modal .modal-title {
            font-size: 22px;
            line-height: 1.25;
            font-weight: 700;
        }

        .account-cancel-modal .modal-body {
            display: grid;
            gap: 16px;
            padding: 18px 20px;
        }

        .account-cancel-modal .modal-body p,
        .account-cancel-modal .form-label {
            margin-bottom: 0;
        }

        .account-cancel-modal .form-select,
        .account-cancel-modal .form-control {
            border-color: #d9dee5;
            border-radius: 6px;
        }

        .account-cancel-modal textarea.form-control {
            min-height: 96px;
            resize: vertical;
        }

        .account-cancel-modal .modal-footer {
            gap: 10px;
            padding: 14px 20px 18px;
            border-top: 1px solid #eef0f3;
        }

        .account-modal-secondary-btn,
        .account-modal-danger-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            line-height: 1.2;
        }

        .account-modal-secondary-btn {
            border: 1px solid #d9dee5;
            background: #fff;
            color: var(--main);
        }

        .account-modal-secondary-btn:hover {
            background: #f6f7f9;
        }

        .account-modal-danger-btn {
            border: 1px solid #dc3545;
            background: #dc3545;
            color: #fff;
        }

        .account-modal-danger-btn:hover {
            border-color: #b02a37;
            background: #b02a37;
        }

        .account-order-title-row {
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .account-order-detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 18px;
            align-items: start;
        }

        .account-order-main,
        .account-order-side {
            display: grid;
            gap: 18px;
        }

        .account-order-section,
        .account-order-alert {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 18px;
        }

        .account-order-alert {
            border-color: rgba(220, 53, 69, .22);
            background: rgba(220, 53, 69, .04);
        }

        .account-status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .account-status-primary { background: rgba(13, 110, 253, .1); color: #0d6efd; }
        .account-status-success { background: rgba(25, 135, 84, .1); color: #198754; }
        .account-status-danger { background: rgba(220, 53, 69, .1); color: #dc3545; }
        .account-status-warning { background: rgba(255, 193, 7, .18); color: #8a6500; }
        .account-status-info { background: rgba(13, 202, 240, .14); color: #087990; }
        .account-status-secondary { background: #f1f5f9; color: #475569; }

        .account-progress {
            display: grid;
            grid-template-columns: repeat(var(--progress-count), minmax(110px, 1fr));
            overflow-x: auto;
            padding: 4px 4px 8px;
        }

        .account-progress-step {
            position: relative;
            min-width: 110px;
            padding: 34px 8px 0;
            color: #64748b;
            text-align: center;
        }

        .account-progress-step::before {
            content: "";
            position: absolute;
            top: 15px;
            left: calc(50% + 15px);
            right: calc(-50% + 15px);
            height: 3px;
            background: #e5e7eb;
        }

        .account-progress-step:last-child::before {
            display: none;
        }

        .account-progress-dot {
            position: absolute;
            top: 4px;
            left: 50%;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border: 2px solid #e5e7eb;
            border-radius: 50%;
            background: #fff;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: 700;
        }

        .account-progress-step.is-complete,
        .account-progress-step.is-current {
            color: var(--main);
        }

        .account-progress-step.is-complete::before {
            background: #198754;
        }

        .account-progress-step.is-complete .account-progress-dot {
            border-color: #198754;
            background: #198754;
            color: #fff;
        }

        .account-progress-step.is-current .account-progress-dot {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .account-progress-label {
            display: block;
            font-weight: 700;
            line-height: 1.25;
        }

        .account-progress-meta {
            display: block;
            min-height: 18px;
            margin-top: 5px;
            color: #64748b;
            font-size: 12px;
        }

        .account-order-items,
        .account-return-summary,
        .account-activity-list,
        .account-total-list,
        .account-info-list,
        .account-address-snapshot {
            display: grid;
            gap: 12px;
        }

        .account-order-item {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr) minmax(120px, auto);
            gap: 14px;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef0f3;
        }

        .account-order-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .account-order-item-image {
            width: 76px;
            height: 92px;
            border-radius: 6px;
            overflow: hidden;
            background: #f7f7f7;
        }

        .account-order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .account-order-item-money {
            display: grid;
            gap: 4px;
            justify-items: end;
            text-align: right;
        }

        .account-return-policy {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .account-return-policy span {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            line-height: 1;
        }

        .account-return-row {
            display: grid;
            gap: 6px;
            padding-top: 12px;
            border-top: 1px solid #eef0f3;
        }

        .account-return-row > div {
            display: grid;
            gap: 4px;
            color: #198754;
        }

        .account-return-row .is-muted {
            color: #64748b;
        }

        .account-total-row,
        .account-info-list > div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        .account-total-row.is-grand {
            margin-top: 6px;
            padding-top: 12px;
            border-top: 1px solid #eef0f3;
            font-size: 17px;
        }

        .account-info-list span,
        .account-address-snapshot span {
            color: #64748b;
        }

        .account-address-snapshot span,
        .account-address-snapshot strong {
            display: block;
        }

        .account-activity-list {
            position: relative;
            gap: 0;
        }

        .account-activity-item {
            position: relative;
            display: grid;
            grid-template-columns: 18px minmax(0, 1fr);
            gap: 12px;
            padding-bottom: 16px;
        }

        .account-activity-item::before {
            content: "";
            position: absolute;
            top: 18px;
            bottom: -2px;
            left: 8px;
            width: 2px;
            background: #eef0f3;
        }

        .account-activity-item:last-child {
            padding-bottom: 0;
        }

        .account-activity-item:last-child::before {
            display: none;
        }

        .account-activity-dot {
            position: relative;
            z-index: 1;
            width: 18px;
            height: 18px;
            margin-top: 2px;
            border: 3px solid #fff;
            border-radius: 50%;
            background: #64748b;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
        }

        .account-activity-item.is-message .account-activity-dot,
        .account-activity-item.is-success .account-activity-dot {
            background: #198754;
        }

        .account-activity-item.is-danger .account-activity-dot {
            background: #dc3545;
        }

        @media (max-width: 991px) {
            .account-order-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575px) {
            .account-order-detail-head,
            .account-order-item {
                display: grid;
            }

            .account-order-item {
                grid-template-columns: 64px minmax(0, 1fr);
            }

            .account-order-item-money {
                grid-column: 1 / -1;
                justify-items: start;
                text-align: left;
            }

            .account-progress {
                grid-template-columns: 1fr;
                overflow: visible;
            }

            .account-progress-step {
                min-width: 0;
                padding: 0 0 18px 34px;
                text-align: left;
            }

            .account-progress-step::before {
                top: 24px;
                left: 11px;
                right: auto;
                bottom: -4px;
                width: 2px;
                height: auto;
            }

            .account-progress-dot {
                left: 0;
                transform: none;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-customer-cancel-order-form]').forEach((form) => {
                const reason = form.querySelector('[data-customer-cancel-reason]');
                const note = form.querySelector('[data-customer-cancel-note]');
                const applyRequirement = () => {
                    const selected = reason?.selectedOptions?.[0];
                    note.required = selected?.dataset.requiresNote === '1';
                };

                reason?.addEventListener('change', applyRequirement);
                applyRequirement();
            });

            @if($errors->has('cancellation_reason') || $errors->has('cancellation_note') || $errors->has('order_status'))
                const cancelModal = document.getElementById('cancelOrderModal');
                if (cancelModal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(cancelModal).show();
                }
            @endif
        });
    </script>
@endpush
