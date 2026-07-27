{{-- Purpose: Printable receipt for a completed POS exchange. --}}
@extends('layouts.merchant')

@section('title', 'Exchange Receipt | WindowShop')

@push('styles')
    <style>
        .exchange-receipt-page { min-height: calc(100vh - 2rem); background: #f4f5f7; }
        .exchange-receipt-toolbar { display: flex; justify-content: space-between; gap: 1rem; padding: 1rem 0; }
        .exchange-receipt-paper {
            width: min(100%, 380px);
            margin: 0 auto 2rem;
            padding: 1.25rem 1rem;
            background: #fff;
            color: #111827;
            font-family: "Courier New", monospace;
            font-size: 12px;
            line-height: 1.35;
            box-shadow: 0 1rem 2rem rgba(15, 23, 42, .08);
        }
        .exchange-receipt-rule { border-top: 1px dashed #6b7280; margin: .65rem 0; }
        .exchange-receipt-row { display: flex; justify-content: space-between; gap: 1rem; }
        .exchange-receipt-total { font-size: 14px; font-weight: 700; }
        @media print {
            body, .exchange-receipt-page { background: #fff !important; }
            .exchange-receipt-toolbar, .breadcrumb, .page-header, .footer { display: none !important; }
            .content { padding: 0 !important; }
            .exchange-receipt-paper { width: 80mm; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
@endpush

@section('content')
    @php
        $money = static function (float|int|string $value) use ($posCurrency): string {
            $amount = number_format((float) $value, (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? 'INR ');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
    @endphp

    <div class="exchange-receipt-page">
        <div class="exchange-receipt-toolbar">
            <a href="{{ route('merchant.sales.show', $exchange->originalOrder) }}" class="btn btn-light">
                <i class="ph-arrow-left me-1"></i>
                Back
            </a>
            <button type="button" class="btn btn-light" onclick="window.print()">
                <i class="ph-printer me-1"></i>
                Print
            </button>
        </div>

        <article class="exchange-receipt-paper">
            <div class="text-center">
                <div class="fw-bold fs-6">{{ $activeShop->name }}</div>
                <div class="fw-bold">Exchange Receipt</div>
            </div>

            <div class="exchange-receipt-rule"></div>
            <div class="exchange-receipt-row">
                <span>Exchange:</span>
                <span>{{ $exchange->exchange_number }}</span>
            </div>
            <div class="exchange-receipt-row">
                <span>Original:</span>
                <span>{{ $exchange->originalOrder?->order_number }}</span>
            </div>
            <div class="exchange-receipt-row">
                <span>Replacement:</span>
                <span>{{ $exchange->replacementOrder?->order_number }}</span>
            </div>
            <div class="exchange-receipt-row">
                <span>Date:</span>
                <span>{{ $exchange->created_at?->format('d-M-Y h:i A') }}</span>
            </div>
            <div>Cashier: {{ $exchange->createdBy?->name ?? auth()->user()?->name ?? 'Staff' }}</div>

            <div class="exchange-receipt-rule"></div>
            <div class="fw-bold">Returned Items</div>
            @foreach($exchange->items as $item)
                <div>{{ $item->orderItem?->product_name ?? 'Product' }}</div>
                <div class="exchange-receipt-row">
                    <span>{{ $item->quantity }} x {{ $money($item->unit_return_value) }}</span>
                    <span>-{{ $money($item->line_total) }}</span>
                </div>
            @endforeach

            <div class="exchange-receipt-rule"></div>
            <div class="fw-bold">Replacement Items</div>
            @foreach($exchange->replacementOrder?->items ?? [] as $item)
                <div>{{ $item->product_name }}</div>
                <div class="exchange-receipt-row">
                    <span>{{ $item->quantity }} x {{ $money($item->unit_price) }}</span>
                    <span>{{ $money($item->line_total) }}</span>
                </div>
            @endforeach

            <div class="exchange-receipt-rule"></div>
            <div class="exchange-receipt-row">
                <span>Returned value</span>
                <span>-{{ $money($exchange->returned_total) }}</span>
            </div>
            <div class="exchange-receipt-row">
                <span>Replacement value</span>
                <span>{{ $money($exchange->replacement_total) }}</span>
            </div>
            <div class="exchange-receipt-row exchange-receipt-total">
                <span>{{ Str::headline($exchange->settlement_type) }}</span>
                <span>
                    @if((float) $exchange->amount_collected > 0)
                        {{ $money($exchange->amount_collected) }}
                    @elseif((float) $exchange->amount_refunded > 0)
                        -{{ $money($exchange->amount_refunded) }}
                    @elseif((float) $exchange->credit_adjustment_amount > 0)
                        -{{ $money($exchange->credit_adjustment_amount) }}
                    @else
                        {{ $money(0) }}
                    @endif
                </span>
            </div>

            @if(data_get($exchange->metadata, 'notes'))
                <div class="exchange-receipt-rule"></div>
                <div>Notes: {{ data_get($exchange->metadata, 'notes') }}</div>
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
