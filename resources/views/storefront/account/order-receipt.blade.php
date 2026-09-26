@extends('storefront.layouts.app')

@section('title', 'Order Receipt '.$receipt['number'])
@section('meta_description', 'Customer order receipt from '.$marketplaceName.'.')

@push('styles')
    <style>
        .customer-receipt-page { background: #f6f7f9; }
        .customer-receipt-toolbar, .customer-receipt-paper { width: min(100%, 920px); margin-left: auto; margin-right: auto; }
        .customer-receipt-toolbar { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .customer-receipt-paper { padding: 40px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #111827; box-shadow: 0 12px 32px rgba(15,23,42,.06); }
        .customer-receipt-head { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 22px; border-bottom: 2px solid #111827; }
        .customer-receipt-title { font-size: 28px; font-weight: 700; letter-spacing: .06em; }
        .customer-receipt-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding: 22px 0; }
        .customer-receipt-box { padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
        .customer-receipt-label { display: block; margin-bottom: 4px; color: #64748b; font-size: 12px; text-transform: uppercase; }
        .customer-receipt-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; }
        .customer-receipt-table { width: 100%; border-collapse: collapse; }
        .customer-receipt-table th, .customer-receipt-table td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        .customer-receipt-table th:last-child, .customer-receipt-table td:last-child { text-align: right; }
        .customer-receipt-item-note { margin-top: 3px; color: #64748b; font-size: 12px; }
        .customer-receipt-free { color: #15803d; font-weight: 700; }
        .customer-receipt-summary { width: min(100%, 380px); margin: 24px 0 0 auto; }
        .customer-receipt-summary-row { display: flex; justify-content: space-between; gap: 20px; padding: 6px 0; }
        .customer-receipt-grand { margin-top: 8px; padding-top: 12px; border-top: 2px solid #111827; font-size: 18px; font-weight: 700; }
        .customer-receipt-payment { margin-top: 26px; padding: 18px; border-radius: 8px; background: #f8fafc; }
        @media (max-width: 767.98px) {
            .customer-receipt-paper { padding: 20px; }
            .customer-receipt-head, .customer-receipt-toolbar { flex-direction: column; }
            .customer-receipt-meta, .customer-receipt-parties { grid-template-columns: 1fr; }
            .customer-receipt-table { display: block; overflow-x: auto; }
            .customer-receipt-toolbar .tf-btn { width: 100%; }
        }
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body > :not(#wrapper), #wrapper > :not(.customer-receipt-page), .customer-receipt-toolbar, #goTop, #preload { display: none !important; }
            html, body, #wrapper, .customer-receipt-page { width: 100% !important; min-width: 0 !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .customer-receipt-page .container { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .customer-receipt-paper { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; box-shadow: none !important; font-size: 9pt; line-height: 1.25; }
            .customer-receipt-head { display: flex !important; flex-direction: row !important; padding-bottom: 8px; }
            .customer-receipt-title { font-size: 18pt; }
            .customer-receipt-meta { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 6px; padding: 8px 0; }
            .customer-receipt-parties { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 8px; margin-bottom: 8px; }
            .customer-receipt-box { padding: 6px 8px; }
            .customer-receipt-label { margin-bottom: 2px; font-size: 8pt; }
            .customer-receipt-table { display: table !important; width: 100% !important; overflow: visible !important; font-size: 8.5pt; }
            .customer-receipt-table th, .customer-receipt-table td { padding: 5px 6px; }
            .customer-receipt-item-note { margin-top: 1px; font-size: 8pt; line-height: 1.15; }
            .customer-receipt-settlement { display: grid !important; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: end; margin-top: 8px; }
            .customer-receipt-summary { width: 340px !important; margin: 0 !important; grid-column: 2; }
            .customer-receipt-summary-row { padding: 2px 0; }
            .customer-receipt-grand { margin-top: 3px; padding-top: 5px; font-size: 12pt; }
            .customer-receipt-payment { margin: 0 !important; padding: 8px; grid-column: 1; grid-row: 1; }
            .customer-receipt-box, .customer-receipt-settlement, tr { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
@endpush

@section('content')
    <section class="customer-receipt-page flat-spacing">
        <div class="container">
            <div class="customer-receipt-toolbar">
                <a href="{{ route('storefront.account.orders.show', $order) }}" class="tf-btn btn-stroke">Back to Order</a>
                <button type="button" class="tf-btn animate-btn" onclick="window.print()">Print Receipt</button>
            </div>
            <article class="customer-receipt-paper">
                <header class="customer-receipt-head">
                    <div><div class="customer-receipt-title">ORDER RECEIPT</div><div>{{ $order->shop?->name ?? $marketplaceName }}</div></div>
                    <div><strong>{{ $receipt['number'] }}</strong><br><span class="cl-text-2">{{ $receipt['date'] }}</span></div>
                </header>

                <div class="customer-receipt-meta">
                    <div class="customer-receipt-box"><span class="customer-receipt-label">Order Status</span><strong>{{ $receipt['status'] }}</strong></div>
                    <div class="customer-receipt-box"><span class="customer-receipt-label">Payment Status</span><strong>{{ $receipt['payment_status'] }}</strong></div>
                    <div class="customer-receipt-box"><span class="customer-receipt-label">Fulfilment</span><strong>{{ $receipt['fulfilment'] }}</strong></div>
                </div>

                <div class="customer-receipt-parties">
                    <div class="customer-receipt-box"><span class="customer-receipt-label">Customer</span><strong>{{ $receipt['customer']['name'] ?? 'Customer' }}</strong>@if(!empty($receipt['customer']['mobile']))<br>{{ $receipt['customer']['mobile'] }}@endif @if(!empty($receipt['customer']['email']))<br>{{ $receipt['customer']['email'] }}@endif</div>
                    <div class="customer-receipt-box"><span class="customer-receipt-label">{{ $receipt['address_title'] }}</span>{{ $receipt['address'] ?: '-' }}</div>
                    @if($receipt['billing_address'] !== '' && $receipt['billing_address'] !== $receipt['address'])
                        <div class="customer-receipt-box"><span class="customer-receipt-label">Billing Address</span>{{ $receipt['billing_address'] }}</div>
                    @endif
                </div>

                <table class="customer-receipt-table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount / Tax</th><th>Line Total</th></tr></thead>
                    <tbody>
                        @foreach($receipt['items'] as $item)
                            <tr>
                                <td><strong>{{ $item['name'] }}</strong>@if($item['variant'])<div class="customer-receipt-item-note">{{ $item['variant'] }}</div>@endif @if($item['sku'])<div class="customer-receipt-item-note">SKU: {{ $item['sku'] }}</div>@endif @if($item['free_gift'])<div class="customer-receipt-free">Free Gift</div>@elseif($item['promotion'])<div class="customer-receipt-item-note">Offer: {{ $item['promotion'] }}{{ $item['coupon'] ? ' · Coupon '.$item['coupon'] : '' }}</div>@endif</td>
                                <td>{{ $item['quantity'] }}</td><td>{{ $item['unit_price'] }}</td>
                                <td>@if($item['discount'])Discount: -{{ $item['discount'] }}@endif @if($item['tax'])<div>Tax: {{ $item['tax'] }}</div>@endif @if(!$item['discount'] && !$item['tax'])—@endif</td>
                                <td class="{{ $item['free_gift'] ? 'customer-receipt-free' : '' }}">{{ $item['free_gift'] ? 'FREE' : $item['line_total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="customer-receipt-settlement">
                    <div class="customer-receipt-summary">
                        @foreach($receipt['totals'] as $total)<div class="customer-receipt-summary-row"><span>{{ $total['label'] }}</span><span>{{ $total['value'] }}</span></div>@endforeach
                        <div class="customer-receipt-summary-row customer-receipt-grand"><span>Grand Total</span><span>{{ $receipt['grand_total'] }}</span></div>
                    </div>

                    <div class="customer-receipt-payment"><span class="customer-receipt-label">Payment Information</span><strong>{{ $receipt['payment_method'] }}</strong> · {{ $receipt['payment_status'] }}@if($receipt['payment_reference'])<br>Reference: {{ $receipt['payment_reference'] }}@endif</div>
                </div>
            </article>
        </div>
    </section>
@endsection

@if($autoPrint)
    @push('scripts')
        <script>
            (() => {
                let printStarted = false;
                const nextFrame = () => new Promise((resolve) => requestAnimationFrame(resolve));
                const waitForImages = () => Promise.all(Array.from(document.querySelectorAll('.customer-receipt-paper img')).map((image) => {
                    if (image.complete) return image.decode?.().catch(() => {}) ?? Promise.resolve();
                    return new Promise((resolve) => {
                        image.addEventListener('load', resolve, { once: true });
                        image.addEventListener('error', resolve, { once: true });
                    });
                }));
                const printWhenReady = async () => {
                    if (printStarted) return;
                    printStarted = true;
                    if (document.readyState !== 'complete') {
                        await new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));
                    }
                    if (document.fonts?.ready) await document.fonts.ready.catch(() => {});
                    await waitForImages();
                    await nextFrame();
                    await nextFrame();
                    window.print();
                };
                printWhenReady();
            })();
        </script>
    @endpush
@endif
