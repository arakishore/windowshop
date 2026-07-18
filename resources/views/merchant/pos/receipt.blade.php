{{-- Purpose: Printable POS receipt for completed merchant cash sales. --}}
@extends('layouts.merchant')

@section('title', 'POS Receipt | WindowShop')

@push('styles')
    <style>
        .receipt-page {
            min-height: calc(100vh - 2rem);
            background: #f4f5f7;
        }

        .receipt-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
        }

        .receipt-paper {
            width: min(100%, 360px);
            margin: 0 auto 2rem;
            padding: 1.25rem 1rem;
            background: #fff;
            color: #111827;
            font-family: "Courier New", monospace;
            font-size: 12px;
            line-height: 1.35;
            box-shadow: 0 1rem 2rem rgba(15, 23, 42, .08);
        }

        .receipt-rule {
            border-top: 1px dashed #6b7280;
            margin: .65rem 0;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .receipt-row span:first-child {
            min-width: 0;
        }

        .receipt-total {
            font-size: 14px;
            font-weight: 700;
        }

        .receipt-barcode {
            margin: 1rem auto .7rem;
            height: 42px;
            width: 220px;
            background: repeating-linear-gradient(90deg, #111 0 2px, transparent 2px 4px, #111 4px 5px, transparent 5px 8px);
        }

        .receipt-qr {
            display: grid;
            place-items: center;
            width: 74px;
            height: 74px;
            margin: .6rem auto .25rem;
            border: 1px dashed #6b7280;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }

        @media print {
            body,
            .receipt-page {
                background: #fff !important;
            }

            .receipt-toolbar,
            .breadcrumb,
            .page-header,
            .footer {
                display: none !important;
            }

            .content {
                padding: 0 !important;
            }

            .receipt-paper {
                width: 80mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $cityLine = trim(($activeShop->city?->name ?? '').($activeShop->pincode ? ' - '.$activeShop->pincode : ''));
        $cleanProductName = static function (string $name, ?string $variantName): string {
            $name = trim((string) preg_replace('/\b([[:alnum:]]+)\s+\1\b/i', '$1', $name));

            if ($variantName !== null && $variantName !== '') {
                $name = trim((string) preg_replace('/\s+'.preg_quote($variantName, '/').'$/i', '', $name));
            }

            return $name;
        };
        $itemDiscountTotal = $order->items->sum(fn ($item) => (float) $item->line_discount);
        $orderDiscountTotal = (float) $order->order_discount_amount;
        $receiptSettings = $receiptSettings ?? [];
        $showShopName = $receiptSettings['showShopName'] ?? true;
        $showAddress = $receiptSettings['showAddress'] ?? true;
        $showPhone = $receiptSettings['showPhone'] ?? true;
        $showGstNumber = $receiptSettings['showGstNumber'] ?? true;
        $showCustomer = $receiptSettings['showCustomer'] ?? true;
        $showCashier = $receiptSettings['showCashier'] ?? true;
        $showOrderNumber = $receiptSettings['showOrderNumber'] ?? true;
        $showBarcode = $receiptSettings['showBarcode'] ?? false;
        $showQrCode = $receiptSettings['showQrCode'] ?? true;
        $showTaxBreakdown = $receiptSettings['showTaxBreakdown'] ?? true;
        $showItemSku = $receiptSettings['showItemSku'] ?? false;
        $showItemHsnCode = $receiptSettings['showItemHsnCode'] ?? false;
        $showHsnSummary = $receiptSettings['showHsnSummary'] ?? false;
        $footerText = trim((string) ($receiptSettings['footerText'] ?? 'Thank you for shopping with us.'));
        $returnPolicy = trim((string) ($receiptSettings['returnPolicy'] ?? ''));
        $itemHsnCode = static fn ($item): ?string => data_get($item->metadata, 'hsn_code') ?: data_get($item->metadata, 'hsn');
        $hsnSummary = $order->items
            ->map(function ($item) use ($itemHsnCode): ?array {
                $hsnCode = $itemHsnCode($item);

                if (! $hsnCode) {
                    return null;
                }

                return [
                    'hsn' => $hsnCode,
                    'taxable' => (float) $item->line_total,
                    'tax' => (float) $item->line_tax,
                ];
            })
            ->filter()
            ->groupBy('hsn')
            ->map(fn ($items, $hsn): array => [
                'hsn' => $hsn,
                'taxable' => $items->sum('taxable'),
                'tax' => $items->sum('tax'),
            ])
            ->values();
    @endphp

    <div class="receipt-page">
        <div class="receipt-toolbar">
            <a href="{{ route('merchant.pos.index') }}" class="btn btn-light" data-bs-popup="tooltip" title="Return to POS">
                <i class="ph-arrow-left me-1"></i>
                Back
            </a>
            <button type="button" class="btn btn-light" onclick="window.print()" data-bs-popup="tooltip" title="Print this receipt">
                <i class="ph-printer me-1"></i>
                Print
            </button>
        </div>

        <article class="receipt-paper">
            <div class="text-center">
                @if($showShopName)
                    <div class="fw-bold fs-6">{{ $activeShop->name }}</div>
                @endif
                @if($showAddress)
                    @if($activeShop->address_line_1)
                        <div>{{ $activeShop->address_line_1 }}</div>
                    @endif
                    @if($activeShop->address_line_2)
                        <div>{{ $activeShop->address_line_2 }}</div>
                    @endif
                    @if($cityLine !== '')
                        <div>{{ $cityLine }}</div>
                    @endif
                @endif
                @if($showPhone && $activeShop->mobile)
                    <div>Phone : {{ $activeShop->mobile }}</div>
                @endif
                @if($showGstNumber && $activeShop->merchant?->gst_number)
                    <div>GSTIN : {{ $activeShop->merchant->gst_number }}</div>
                @endif
            </div>

            <div class="receipt-rule"></div>

            @if($showOrderNumber)
                <div class="receipt-row">
                    <span class="fw-bold">Invoice :</span>
                    <span class="fw-bold">{{ $order->order_number }}</span>
                </div>
            @endif
            <div class="receipt-row">
                <span>Date :</span>
                <span>{{ $order->created_at?->format('d-M-Y h:i A') }}</span>
            </div>
            @if($showCashier)
                <div>Cashier: {{ $order->createdBy?->name ?? auth()->user()?->name ?? 'Staff' }}</div>
            @endif
            @if($showCustomer && ($order->customer_name || $order->customer_mobile))
                <div>Customer: {{ $order->customer_name ?: 'Customer' }}{{ $order->customer_mobile ? ' / '.$order->customer_mobile : '' }}</div>
            @endif

            <div class="receipt-rule"></div>

            @foreach($order->items as $item)
                <div>{{ $cleanProductName($item->product_name, $item->variant_name) }}</div>
                @if($item->variant_name)
                    <div>{{ $item->variant_name }}</div>
                @endif
                @if($showItemSku && $item->sku)
                    <div>SKU: {{ $item->sku }}</div>
                @endif
                @if($showItemHsnCode && $itemHsnCode($item))
                    <div>HSN: {{ $itemHsnCode($item) }}</div>
                @endif
                <div class="receipt-row">
                    <span>{{ $item->quantity }} x {{ number_format((float) $item->unit_price, 2) }}</span>
                    <span>{{ number_format((float) $item->line_subtotal, 2) }}</span>
                </div>
                @if((float) $item->line_discount > 0)
                    <div class="receipt-row">
                        <span>Line Discount</span>
                        <span>-{{ number_format((float) $item->line_discount, 2) }}</span>
                    </div>
                    <div class="receipt-row">
                        <span>Line Total</span>
                        <span>{{ number_format((float) $item->line_total, 2) }}</span>
                    </div>
                @endif
            @endforeach

            <div class="receipt-rule"></div>

            <div class="receipt-row">
                <span>Subtotal</span>
                <span>{{ number_format((float) $order->subtotal, 2) }}</span>
            </div>
            <div class="receipt-row">
                <span>Item Discount</span>
                <span>{{ number_format($itemDiscountTotal, 2) }}</span>
            </div>
            <div class="receipt-row">
                <span>Order Discount</span>
                <span>{{ number_format($orderDiscountTotal, 2) }}</span>
            </div>
            @if($showTaxBreakdown)
                <div class="receipt-row">
                    <span>Tax</span>
                    <span>{{ number_format((float) $order->tax_total, 2) }}</span>
                </div>
            @endif

            @if($showHsnSummary && $hsnSummary->isNotEmpty())
                <div class="receipt-rule"></div>
                <div class="fw-bold">HSN Summary</div>
                @foreach($hsnSummary as $summary)
                    <div class="receipt-row">
                        <span>{{ $summary['hsn'] }} Taxable</span>
                        <span>{{ number_format((float) $summary['taxable'], 2) }}</span>
                    </div>
                    <div class="receipt-row">
                        <span>{{ $summary['hsn'] }} GST</span>
                        <span>{{ number_format((float) $summary['tax'], 2) }}</span>
                    </div>
                @endforeach
            @endif

            <div class="receipt-rule"></div>

            <div class="receipt-row receipt-total">
                <span>TOTAL</span>
                <span>{{ number_format((float) $order->grand_total, 2) }}</span>
            </div>
            <div class="receipt-row">
                <span>Payment</span>
                <span>{{ Str::headline($order->payment_method) }}</span>
            </div>
            <div class="receipt-row">
                <span>{{ Str::headline($order->payment_method) }}</span>
                <span>{{ number_format((float) $order->amount_paid, 2) }}</span>
            </div>
            @if($order->payment_reference)
                <div class="receipt-row">
                    <span>Reference</span>
                    <span>{{ $order->payment_reference }}</span>
                </div>
            @endif
            @if($order->upi_txn)
                <div class="receipt-row">
                    <span>UPI TXN</span>
                    <span>{{ $order->upi_txn }}</span>
                </div>
            @endif
            @if($order->terminal_id)
                <div class="receipt-row">
                    <span>Terminal</span>
                    <span>{{ $order->terminal_id }}</span>
                </div>
            @endif
            <div class="receipt-row">
                <span>Change</span>
                <span>{{ number_format((float) $order->change_amount, 2) }}</span>
            </div>

            <div class="receipt-rule"></div>

            @if($showBarcode)
                <div class="receipt-barcode"></div>
                <div class="text-center">{{ $order->order_number }}</div>
            @endif
            @if($showQrCode)
                <div class="receipt-qr">QR</div>
                <div class="text-center text-muted">Scan to view</div>
            @endif

            @if($footerText !== '' || $returnPolicy !== '')
                <div class="receipt-rule"></div>
                <div class="text-center">
                    @if($footerText !== '')
                        <div>{!! nl2br(e($footerText)) !!}</div>
                    @endif
                    @if($returnPolicy !== '')
                        <div class="text-muted mt-1">{!! nl2br(e($returnPolicy)) !!}</div>
                    @endif
                </div>
            @endif
        </article>
    </div>
@endsection

@if($autoPrint)
    @push('scripts')
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endpush
@endif
