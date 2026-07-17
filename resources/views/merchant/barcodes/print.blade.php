<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Barcode Labels</title>
    <style>
        @page {
            size: {{ $template['page'] === 'a4' ? 'A4' : $template['width'].' '.$template['height'] }};
            margin: {{ $template['page'] === 'a4' ? '8mm' : '0' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fff;
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: 9px;
        }

        .print-actions {
            padding: 12px;
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
        }

        .print-actions button {
            border: 1px solid #9ca3af;
            border-radius: 4px;
            background: #fff;
            padding: 7px 12px;
            cursor: pointer;
        }

        .label-sheet {
            display: grid;
            grid-template-columns: repeat({{ $template['columns'] }}, {{ $template['width'] }});
            align-content: start;
            justify-content: {{ $template['page'] === 'a4' ? 'center' : 'start' }};
            gap: 0;
            padding: {{ $template['page'] === 'a4' ? '0' : '0' }};
        }

        .label {
            width: {{ $template['width'] }};
            height: {{ $template['height'] }};
            overflow: hidden;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .label-product,
        .label-shop {
            font-weight: 700;
            line-height: 1.12;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-variant,
        .label-meta,
        .label-price {
            line-height: 1.12;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .barcode {
            margin-top: 1mm;
        }

        .barcode svg {
            display: block;
            width: 100%;
            height: 9mm;
        }

        .barcode-text {
            text-align: center;
            letter-spacing: 1px;
            font-size: 8px;
            line-height: 1;
            margin-top: .5mm;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <main class="label-sheet">
        @foreach($labels as $variant)
            <section class="label">
                @if($options['shop_name'])
                    <div class="label-shop">{{ $activeShop->name }}</div>
                @endif
                @if($options['product_name'])
                    <div class="label-product">{{ $variant->product?->product_name }}</div>
                @endif
                @if($options['variant_name'])
                    <div class="label-variant">{{ $variant->name ?: 'Default' }}</div>
                @endif
                @if($options['sku'])
                    <div class="label-meta">SKU: {{ $variant->sku ?: '-' }}</div>
                @endif
                @if($options['selling_price'])
                    <div class="label-price">INR {{ number_format((float) $variant->selling_price, 2) }}</div>
                @endif
                @if($options['barcode'] && $variant->barcode)
                    <div class="barcode">
                        {!! $barcodeSvgService->svg($variant->barcode, 42, 1) !!}
                        <div class="barcode-text">{{ $variant->barcode }}</div>
                    </div>
                @endif
            </section>
        @endforeach
    </main>
</body>
</html>
