@extends('storefront.layouts.app')

@section('title', 'Order Placed | WindowShop')
@section('meta_description', 'Your WindowShop order has been placed successfully.')

@push('styles')
    <style>
        .checkout-section {
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
        }

        .checkout-section-title {
            margin-bottom: 18px;
        }

        .checkout-total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
    </style>
@endpush

@section('content')
    @php
        $currency = app(\App\Services\Admin\AdminSettingsService::class)->currencyConfig();
        $money = static function (float|int|string $value) use ($currency): string {
            $amount = number_format((float) $value, (int) ($currency['decimal_places'] ?? 2), (string) ($currency['decimal_separator'] ?? '.'), (string) ($currency['thousands_separator'] ?? ','));
            $symbol = (string) ($currency['symbol'] ?? 'INR ');

            return ($currency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $paymentLabel = match ($order->payment_method) {
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP => 'Cash at Shop',
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY => 'Cash on Delivery',
            default => \Illuminate\Support\Str::headline($order->payment_method),
        };
        $paymentText = match ($order->payment_method) {
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP => 'Pay when you collect your order.',
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY => 'Pay when your order is delivered.',
            default => 'Payment is pending.',
        };
        $shopAddress = collect([
            $order->shop?->address_line_1,
            $order->shop?->address_line_2,
            $order->shop?->city?->name,
            $order->shop?->pincode,
        ])->filter()->implode(', ');
        $deliveryAddress = collect([
            $order->shipping_recipient_name,
            $order->shipping_address_line_1,
            $order->shipping_address_line_2,
            $order->shipping_landmark,
            $order->shipping_city,
            $order->shipping_state,
            $order->shipping_postal_code,
        ])->filter()->implode(', ');
    @endphp

    <section class="s-checkout flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="tf-page-cart-main">
                        <div class="checkout-section">
                            <div class="checkout-section-title">
                                <h3 class="mb-0">Order placed successfully</h3>
                            </div>

                            <div class="checkout-total-row">
                                <span>Order Number</span>
                                <strong>{{ $order->order_number }}</strong>
                            </div>
                            <div class="checkout-total-row">
                                <span>Order Total</span>
                                <strong>{{ $money($order->grand_total) }}</strong>
                            </div>
                            <div class="checkout-total-row">
                                <span>Payment Method</span>
                                <strong>{{ $paymentLabel }}</strong>
                            </div>
                            <p class="text-caption-01 cl-text-3">{{ $paymentText }}</p>
                            <div class="checkout-total-row">
                                <span>Fulfillment Method</span>
                                <strong>{{ \Illuminate\Support\Str::headline($order->fulfilment_type) }}</strong>
                            </div>

                            @if ($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
                                <hr>
                                <h5>Delivery Address</h5>
                                <p class="mb-0">{{ $deliveryAddress ?: '-' }}</p>
                            @elseif ($order->fulfilment_type === \App\Models\Order::FULFILMENT_PICKUP)
                                <hr>
                                <h5>Pickup from Shop</h5>
                                <p class="mb-1"><strong>{{ $order->shop?->name }}</strong></p>
                                <p class="mb-0">{{ $shopAddress ?: '-' }}</p>
                            @endif

                            <div class="mt-24">
                                <a href="{{ route('storefront.home') }}" class="tf-btn animate-btn">Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
