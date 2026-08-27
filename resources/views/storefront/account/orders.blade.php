@extends('storefront.layouts.app')

@section('title', 'My Orders | WindowShop')
@section('meta_description', 'WindowShop customer order area.')

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'My Orders'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">My Orders</p>
            <h4 class="mb-10">My Orders</h4>
            <p class="cl-text-2 mb-0">Track your orders from all WindowShop stores.</p>
        </div>

        <div class="account-order-list">
            @forelse ($orders as $order)
                @php
                    $firstItem = $order->items->first();
                    $itemCount = $order->items->sum('quantity');
                    $additionalItems = max(0, $order->items->count() - 1);
                    $productUrl = $firstItem ? $presenter->productUrl($firstItem) : null;
                @endphp
                <article class="account-order-card">
                    <div class="account-order-head">
                        <div>
                            <a href="{{ route('storefront.account.orders.show', $order) }}" class="account-order-number">{{ $order->order_number }}</a>
                            <p class="cl-text-2 mb-0">{{ $order->shop?->name ?? 'WindowShop Store' }}</p>
                        </div>
                        <span class="account-status-badge {{ $presenter->statusClass($order->order_status) }}">{{ $presenter->statusLabel($order->order_status) }}</span>
                    </div>

                    <div class="account-order-body">
                        @if ($firstItem)
                            <a href="{{ $productUrl ?? route('storefront.account.orders.show', $order) }}" class="account-order-image">
                                <img src="{{ $presenter->imageUrl($firstItem) }}" alt="{{ $firstItem->product_name }}" loading="lazy">
                            </a>
                            <div class="account-order-product">
                                <a href="{{ $productUrl ?? route('storefront.account.orders.show', $order) }}" class="fw-medium link-underline-text">{{ $firstItem->product_name }}</a>
                                @if ($firstItem->variant_name)
                                    <p class="text-caption-01 cl-text-3 mb-0">{{ $firstItem->variant_name }}</p>
                                @endif
                                @if ($additionalItems > 0)
                                    <p class="text-caption-01 cl-text-3 mb-0">+ {{ $additionalItems }} more {{ Str::plural('item', $additionalItems) }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="account-order-meta">
                            <p class="mb-4">Placed {{ app_datetime($order->created_at) }}</p>
                            <p class="mb-0">{{ $presenter->quantityLabel((int) $itemCount) }}</p>
                            <p class="mb-0">{{ $presenter->label($presenter->fulfilmentTypes(), $order->fulfilment_type) }} &bull; {{ $presenter->label($presenter->paymentMethods(), $order->payment_method) }}</p>
                        </div>

                        <div class="account-order-total">
                            <span>{{ $presenter->money($order->grand_total) }}</span>
                            <a href="{{ route('storefront.account.orders.show', $order) }}" class="tf-btn btn-line small">View Order</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="account-empty-panel">
                    <h6 class="mb-6">You haven't placed any orders yet.</h6>
                    <a href="{{ route('storefront.products') }}" class="tf-btn btn-fill small">Continue Shopping</a>
                </div>
            @endforelse
        </div>

        <div class="mt-24">
            @include('storefront.partials.pagination', ['paginator' => $orders])
        </div>
    @endcomponent
@endsection

@push('styles')
    <style>
        .account-order-list {
            display: grid;
            gap: 14px;
        }

        .account-order-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .account-order-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid #eef0f3;
        }

        .account-order-number {
            color: var(--main);
            font-size: 17px;
            font-weight: 700;
        }

        .account-order-body {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr) minmax(190px, auto) minmax(130px, auto);
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
        }

        .account-order-image {
            width: 72px;
            height: 88px;
            border-radius: 6px;
            overflow: hidden;
            background: #f7f7f7;
        }

        .account-order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .account-order-meta {
            color: #64748b;
            font-size: 14px;
            line-height: 1.45;
        }

        .account-order-total {
            display: grid;
            gap: 10px;
            justify-items: end;
            font-weight: 700;
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

        @media (max-width: 991px) {
            .account-order-body {
                grid-template-columns: 72px minmax(0, 1fr);
            }

            .account-order-meta,
            .account-order-total {
                grid-column: 1 / -1;
            }

            .account-order-total {
                display: flex;
                justify-content: space-between;
                justify-items: stretch;
                align-items: center;
            }
        }

        @media (max-width: 575px) {
            .account-order-head {
                display: grid;
            }

            .account-order-body {
                grid-template-columns: 58px minmax(0, 1fr);
                gap: 12px;
            }

            .account-order-image {
                width: 58px;
                height: 72px;
            }
        }
    </style>
@endpush
