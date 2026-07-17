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
    @endphp

    <div class="receipt-page">
        <div class="receipt-toolbar">
            <a href="{{ route('merchant.pos.index') }}" class="btn btn-light">
                <i class="ph-arrow-left me-1"></i>
                Back
            </a>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="ph-printer me-1"></i>
                Print
            </button>
        </div>

        <article class="receipt-paper">
            <div class="text-center">
                <div class="fw-bold fs-6">{{ $activeShop->name }}</div>
                <div>{{ $activeShop->address_line_1 }}</div>
                @if($activeShop->address_line_2)
                    <div>{{ $activeShop->address_line_2 }}</div>
                @endif
                @if($cityLine !== '')
                    <div>{{ $cityLine }}</div>
                @endif
                @if($activeShop->merchant?->gst_number)
                    <div>GSTIN : {{ $activeShop->merchant->gst_number }}</div>
                @endif
            </div>

            <div class="receipt-rule"></div>

            <div class="receipt-row">
                <span class="fw-bold">Invoice :</span>
                <span class="fw-bold">{{ $order->order_number }}</span>
            </div>
            <div class="receipt-row">
                <span>Date :</span>
                <span>{{ $order->created_at?->format('d-M-Y h:i A') }}</span>
            </div>
            <div>Cashier: {{ $order->createdBy?->name ?? auth()->user()?->name ?? 'Staff' }}</div>

            <div class="receipt-rule"></div>

            @foreach($order->items as $item)
                <div>{{ $cleanProductName($item->product_name, $item->variant_name) }}</div>
                @if($item->variant_name)
                    <div>{{ $item->variant_name }}</div>
                @endif
                <div class="receipt-row">
                    <span>{{ $item->quantity }} x {{ number_format((float) $item->unit_price, 2) }}</span>
                    <span>{{ number_format((float) $item->line_total, 2) }}</span>
                </div>
            @endforeach

            <div class="receipt-rule"></div>

            <div class="receipt-row">
                <span>Subtotal</span>
                <span>{{ number_format((float) $order->subtotal, 2) }}</span>
            </div>
            <div class="receipt-row">
                <span>Discount</span>
                <span>{{ number_format((float) $order->discount_total, 2) }}</span>
            </div>
            <div class="receipt-row">
                <span>Tax</span>
                <span>{{ number_format((float) $order->tax_total, 2) }}</span>
            </div>

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

            <div class="receipt-barcode"></div>
            <div class="text-center">{{ $order->order_number }}</div>
            <div class="receipt-qr">QR</div>
            <div class="text-center text-muted">Scan to view</div>

            <div class="receipt-rule"></div>
            <div class="text-center">
                <div>Thank you for shopping!</div>
                <div>Visit Again</div>
            </div>
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
