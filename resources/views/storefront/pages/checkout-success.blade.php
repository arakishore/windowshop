@extends('storefront.layouts.app')

@section('title', 'Order Placed | ' . $marketplaceName)
@section('meta_description', 'Your '.$marketplaceName.' order has been placed successfully.')

@push('styles')
    <style>
        .order-success-card { padding: 32px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; box-shadow: 0 12px 36px rgba(15, 23, 42, .06); }
        .order-success-header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .order-success-check { display: flex; width: 58px; height: 58px; flex: 0 0 58px; align-items: center; justify-content: center; border-radius: 50%; background: #22c55e; color: #fff; font-size: 28px; }
        .order-success-header h3 { margin-bottom: 3px; }
        .order-success-header p { margin: 0; color: #64748b; }
        .order-success-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(240px, .8fr); gap: 24px; }
        .order-success-details, .order-success-address { border: 1px solid #e5e7eb; border-radius: 10px; background: #fbfcfd; }
        .order-success-detail-row { display: grid; grid-template-columns: 28px minmax(120px, 1fr) minmax(140px, auto); gap: 12px; align-items: center; padding: 14px 18px; border-bottom: 1px solid #e9edf2; }
        .order-success-detail-row:last-child { border-bottom: 0; }
        .order-success-detail-row i, .order-success-address > i { color: #475569; font-size: 19px; }
        .order-success-detail-label { color: #64748b; }
        .order-success-detail-value { text-align: right; color: #111827; font-weight: 700; }
        .order-success-payment-copy { display: block; margin-top: 2px; color: #64748b; font-size: 13px; font-weight: 400; }
        .order-success-aside { display: grid; grid-template-rows: 1fr auto; gap: 12px; }
        .order-success-visual { position: relative; min-height: 145px; overflow: hidden; border-radius: 12px; background: linear-gradient(145deg, #f0fdf4, #ecfdf5); }
        .order-success-visual::before, .order-success-visual::after { position: absolute; content: ''; border-radius: 50%; background: rgba(34, 197, 94, .12); }
        .order-success-visual::before { width: 150px; height: 150px; right: 18px; top: -35px; }
        .order-success-visual::after { width: 90px; height: 90px; left: 28px; bottom: -35px; }
        .order-success-visual-icons { position: absolute; inset: 0; z-index: 1; display: flex; align-items: center; justify-content: center; gap: 18px; color: #16a34a; }
        .order-success-visual-icons .icon-Handbag { font-size: 76px; }
        .order-success-visual-icons .icon-Package { align-self: flex-end; margin-bottom: 28px; color: #d97706; font-size: 48px; }
        .order-success-notice { display: grid; grid-template-columns: 30px 1fr; gap: 10px; padding: 14px 16px; border-radius: 10px; background: #ecfdf5; color: #166534; }
        .order-success-notice.is-pending { background: #fffbeb; color: #92400e; }
        .order-success-notice i { font-size: 22px; }
        .order-success-notice strong, .order-success-notice span { display: block; }
        .order-success-notice span { margin-top: 2px; color: #475569; font-size: 13px; line-height: 1.45; }
        .order-success-address { display: grid; grid-template-columns: 30px 1fr; gap: 12px; margin-top: 20px; padding: 16px 18px; }
        .order-success-address h5 { margin-bottom: 4px; }
        .order-success-address p { margin: 0; color: #475569; line-height: 1.5; }
        .order-success-footer { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 20px; }
        .order-success-reassurance { display: flex; flex: 1 1 330px; justify-content: flex-end; gap: 22px; color: #64748b; font-size: 12px; }
        .order-success-reassurance span { display: inline-flex; align-items: center; gap: 7px; }
        .order-success-reassurance i { color: #475569; font-size: 17px; }
        @media (max-width: 767.98px) {
            .order-success-card { padding: 20px; }
            .order-success-grid { grid-template-columns: 1fr; }
            .order-success-aside { grid-template-rows: auto; }
            .order-success-visual { display: none; }
            .order-success-detail-row { grid-template-columns: 24px 1fr; }
            .order-success-detail-value { grid-column: 2; text-align: left; }
            .order-success-footer .tf-btn { flex: 1 1 100%; }
            .order-success-reassurance { justify-content: flex-start; gap: 14px; }
        }
        @media (max-width: 479.98px) { .order-success-reassurance { display: grid; grid-template-columns: 1fr; } }
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
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI => 'Direct Merchant UPI',
            default => \Illuminate\Support\Str::headline($order->payment_method),
        };
        $paymentText = match ($order->payment_method) {
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP => 'Pay at the shop when you collect your order.',
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY => 'Pay when your order is delivered.',
            \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI => 'Your payment reference was submitted and is awaiting merchant verification.',
            default => 'Payment is pending.',
        };
        $isUpiVerificationPending = $order->payment_method === \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI
            && $order->payment_status === \App\Models\Order::PAYMENT_PENDING;
        $noticeTitle = match (true) {
            $isUpiVerificationPending => 'Payment verification pending',
            $order->order_status === \App\Models\Order::STATUS_CONFIRMED => 'Order confirmed',
            $order->order_status === \App\Models\Order::STATUS_PENDING => 'Order pending',
            default => 'Order '.strtolower(\Illuminate\Support\Str::headline($order->order_status)),
        };
        $noticeText = match (true) {
            $isUpiVerificationPending => 'Your payment is awaiting merchant verification. We’ll notify you once it has been verified.',
            $order->order_status === \App\Models\Order::STATUS_CONFIRMED => 'The shop has confirmed your order. We’ll notify you as it progresses.',
            $order->order_status === \App\Models\Order::STATUS_PENDING => 'Your order has been placed successfully. We’ll notify you when the shop confirms your order.',
            default => 'Your order status has been updated. View your order for the latest details.',
        };
        $isNoticePending = $isUpiVerificationPending || $order->order_status === \App\Models\Order::STATUS_PENDING;
        $shopAddress = collect([$order->shop?->address_line_1, $order->shop?->address_line_2, $order->shop?->city?->name, $order->shop?->pincode])->filter()->implode(', ');
        $deliveryAddress = collect([$order->shipping_recipient_name, $order->shipping_address_line_1, $order->shipping_address_line_2, $order->shipping_landmark, $order->shipping_city, $order->shipping_state, $order->shipping_postal_code])->filter()->implode(', ');
    @endphp

    <section class="s-checkout flat-spacing"><div class="container"><div class="row justify-content-center"><div class="col-xl-10 col-lg-11"><div class="tf-page-cart-main"><div class="order-success-card">
        <div class="order-success-header"><span class="order-success-check"><i class="icon-check"></i></span><div><h3>Order placed successfully!</h3><p>Thank you! Your order has been placed. We will keep you updated.</p></div></div>
        <div class="order-success-grid">
            <div class="order-success-details">
                <div class="order-success-detail-row"><i class="icon-ReceiptX"></i><span class="order-success-detail-label">Order Number</span><strong class="order-success-detail-value">{{ $order->order_number }}</strong></div>
                <div class="order-success-detail-row"><i class="icon-Tag"></i><span class="order-success-detail-label">Order Total</span><strong class="order-success-detail-value">{{ $money($order->grand_total) }}</strong></div>
                <div class="order-success-detail-row"><i class="icon-shopping-cart-simple"></i><span class="order-success-detail-label">Payment Method</span><strong class="order-success-detail-value">{{ $paymentLabel }}<span class="order-success-payment-copy">{{ $paymentText }}</span></strong></div>
                <div class="order-success-detail-row"><i class="{{ $order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY ? 'icon-Truck' : 'icon-storefront' }}"></i><span class="order-success-detail-label">Fulfillment Method</span><strong class="order-success-detail-value">{{ \Illuminate\Support\Str::headline($order->fulfilment_type) }}</strong></div>
            </div>
            <div class="order-success-aside">
                <div class="order-success-visual" aria-hidden="true"><div class="order-success-visual-icons"><i class="icon-Handbag"></i><i class="icon-Package"></i></div></div>
                <div class="order-success-notice {{ $isNoticePending ? 'is-pending' : '' }}"><i class="{{ $isNoticePending ? 'icon-HourglassMedium' : ($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY ? 'icon-Truck' : 'icon-storefront') }}"></i><div><strong>{{ $noticeTitle }}</strong><span>{{ $noticeText }}</span></div></div>
            </div>
        </div>
        @if ($order->fulfilment_type === \App\Models\Order::FULFILMENT_DELIVERY)
            <div class="order-success-address"><i class="icon-HouseLine"></i><div><h5>Delivery Address</h5><p>{{ $deliveryAddress ?: '-' }}</p></div></div>
        @elseif ($order->fulfilment_type === \App\Models\Order::FULFILMENT_PICKUP)
            <div class="order-success-address"><i class="icon-storefront"></i><div><h5>Pickup from Shop</h5><p><strong>{{ $order->shop?->name }}</strong><br>{{ $shopAddress ?: '-' }}</p></div></div>
        @endif
        <div class="order-success-footer">
            <a href="{{ route('storefront.account.orders.show', $order) }}" class="tf-btn animate-btn">View Order</a>
            <a href="{{ route('storefront.home') }}" class="tf-btn btn-stroke">Continue Shopping</a>
            <div class="order-success-reassurance"><span><i class="icon-CheckCircle"></i> Order updates available</span><span><i class="icon-Truck2"></i> Status notifications</span><span><i class="icon-ShieldCheck"></i> Safe &amp; Secure Shopping</span></div>
        </div>
    </div></div></div></div></div></section>
@endsection
