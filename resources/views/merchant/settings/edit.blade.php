{{-- Purpose: Merchant settings editor backed by the generic merchant_settings table. --}}
@extends('layouts.merchant')

@section('title', 'Settings | WindowShop')

@section('page_title', 'Settings')

@push('styles')
    <style>
        .merchant-settings-layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .merchant-settings-tabs {
            position: sticky;
            top: 1rem;
        }

        .merchant-settings-tabs .nav-link {
            justify-content: flex-start;
            gap: .5rem;
            border-radius: .375rem;
            color: var(--body-color);
        }

        .merchant-settings-tabs .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .merchant-settings-card .card-body {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .merchant-settings-hero {
            border-left: 4px solid var(--primary);
        }

        .settings-section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray-600, #6c757d);
            border-bottom: 1px solid var(--border-color, #ddd);
            padding-bottom: .45rem;
            margin-bottom: .85rem;
        }

        .merchant-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem 1rem;
        }

        .payment-methods-table {
            min-width: 720px;
        }

        .payment-methods-table th {
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--gray-600, #6c757d);
            background: var(--gray-100, #f5f5f5);
        }

        .payment-method-code {
            font-size: .75rem;
            color: var(--gray-600, #6c757d);
        }

        .pos-tile-size-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .pos-tile-size-option {
            min-height: 7rem;
            padding: .8rem;
            border: 1px solid var(--border-color, #ddd);
            border-radius: .375rem;
            text-align: center;
            cursor: pointer;
            background: #fff;
        }

        .pos-tile-size-option:has(input:checked) {
            border-color: var(--primary);
            background: rgba(var(--primary-rgb, 13, 110, 253), .06);
            box-shadow: 0 0 0 .125rem rgba(var(--primary-rgb, 13, 110, 253), .12);
        }

        .pos-tile-size-icon {
            width: 4.5rem;
            height: 3.2rem;
            margin: 0 auto .45rem;
            display: grid;
            gap: .25rem;
            justify-content: center;
            align-content: center;
            border-radius: .35rem;
            background: var(--gray-100, #f5f5f5);
        }

        .pos-tile-size-icon span {
            display: block;
            width: .8rem;
            height: .8rem;
            border-radius: .15rem;
            background: var(--gray-300, #d1d5db);
        }

        .pos-tile-size-icon.is-compact {
            grid-template-columns: repeat(4, .8rem);
        }

        .pos-tile-size-icon.is-comfortable {
            grid-template-columns: repeat(3, .8rem);
        }

        .pos-tile-size-icon.is-spacious {
            grid-template-columns: repeat(2, 1.15rem);
        }

        .pos-tile-size-icon.is-spacious span {
            width: 1.15rem;
        }

        .receipt-settings-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 1rem;
            align-items: start;
        }

        .receipt-preview-card {
            position: sticky;
            top: 1rem;
        }

        .receipt-preview-shell {
            display: flex;
            justify-content: center;
            padding: 1rem;
            background: #f3f4f6;
        }

        .receipt-preview-paper {
            width: 260px;
            padding: 1rem .85rem;
            background: #fff;
            color: #111827;
            font-family: "Courier New", monospace;
            font-size: 11px;
            line-height: 1.35;
            box-shadow: 0 .5rem 1.5rem rgba(15, 23, 42, .08);
        }

        .receipt-preview-rule {
            border-top: 1px dashed #6b7280;
            margin: .55rem 0;
        }

        .receipt-preview-row {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
        }

        .receipt-preview-barcode {
            height: 36px;
            width: 180px;
            margin: .75rem auto .35rem;
            background: repeating-linear-gradient(90deg, #111 0 2px, transparent 2px 4px, #111 4px 5px, transparent 5px 8px);
        }

        .receipt-preview-qr {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: .75rem auto .25rem;
            border: 1px dashed #6b7280;
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        .merchant-settings-savebar {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: var(--body-bg, #f5f7fb);
            border-top: 1px solid var(--border-color, #ddd);
            padding: .75rem 0;
        }

        @media (max-width: 991.98px) {
            .merchant-settings-layout {
                grid-template-columns: 1fr;
            }

            .receipt-settings-layout {
                grid-template-columns: 1fr;
            }

            .merchant-settings-tabs {
                position: static;
            }

            .receipt-preview-card {
                position: static;
            }

            .merchant-settings-tabs .nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .merchant-settings-grid,
            .pos-tile-size-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $oldSettings = old('settings', []);
        $selectOptions = [
            'pos.cash_rounding.method' => ['nearest' => 'Nearest', 'up' => 'Up', 'down' => 'Down'],
        ];
        $field = function (string $group, string $key) use ($settings, $defaults, $oldSettings) {
            return [
                'fullKey' => "{$group}.{$key}",
                'name' => "settings[{$group}][{$key}]",
                'id' => 'setting_'.Str::slug($group.'_'.$key, '_'),
                'value' => $oldSettings[$group][$key] ?? $settings["{$group}.{$key}"] ?? $defaults[$group][$key]['value'] ?? null,
                'type' => $defaults[$group][$key]['type'] ?? \App\Models\MerchantSetting::TYPE_STRING,
                'errorKey' => "settings.{$group}.{$key}",
            ];
        };
    @endphp

    <div class="card merchant-settings-hero">
        <div class="card-body d-flex align-items-center gap-3">
            <span class="btn btn-primary btn-icon rounded-pill">
                <i class="ph-gear"></i>
            </span>
            <div>
                <h4 class="mb-1">Merchant Settings</h4>
                <div class="text-muted">Configure how your store behaves.</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('merchant.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="merchant-settings-layout">
            <div class="merchant-settings-tabs">
                <div class="card">
                    <div class="card-body p-2">
                        <div class="nav nav-pills flex-column" role="tablist">
                            @foreach ([
                                'general' => ['General', 'ph-gear'],
                                'pos' => ['POS', 'ph-desktop'],
                                'orders' => ['Orders', 'ph-receipt'],
                                'inventory' => ['Inventory', 'ph-stack'],
                                'products' => ['Products', 'ph-package'],
                                'payments' => ['Payments', 'ph-credit-card'],
                                'receipts' => ['Receipts', 'ph-printer'],
                                'notifications' => ['Notifications', 'ph-bell'],
                                'advanced' => ['Advanced', 'ph-sliders'],
                            ] as $tab => [$label, $icon])
                                <button
                                    type="button"
                                    class="nav-link d-flex align-items-center {{ $tab === 'pos' ? 'active' : '' }}"
                                    data-bs-toggle="tab"
                                    data-bs-target="#settings_{{ $tab }}"
                                    role="tab"
                                >
                                    <i class="{{ $icon }}"></i>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade" id="settings_general" role="tabpanel">
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">General Settings</h5>
                        </div>
                        <div class="card-body text-muted">
                            General merchant preferences will appear here as WindowShop grows.
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade show active" id="settings_pos" role="tabpanel">
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Cash Rounding</h5>
                            <div class="text-muted fs-sm mt-1">Round the final payable amount for selected payment methods.</div>
                        </div>
                        <div class="card-body">
                            @php
                                $cashMethod = $field('pos', 'cash_rounding.method');
                                $cashApply = $field('pos', 'cash_rounding.apply_to');
                                $applyValue = (string) $cashApply['value'];
                                $applyMethods = $applyValue === 'all'
                                    ? ['cash', 'upi', 'card']
                                    : explode(',', $applyValue);
                            @endphp

                             
                            <div class="merchant-settings-grid">
                                <div>
                                    <label class="form-label fw-semibold d-block">Method</label>
                                    <div class="d-flex flex-wrap gap-3" role="radiogroup" aria-label="Cash rounding method">
                                        @foreach ([
                                            'nearest' => 'Nearest',
                                            'up' => 'Always Up',
                                            'down' => 'Always Down',
                                        ] as $method => $label)
                                            <div class="form-check form-check-inline mb-0">
                                                <input
                                                    type="radio"
                                                    class="form-check-input js-cash-rounding-method"
                                                    name="{{ $cashMethod['name'] }}"
                                                    id="{{ $cashMethod['id'] }}_{{ $method }}"
                                                    value="{{ $method }}"
                                                    @checked($cashMethod['value'] === $method)
                                                >
                                                <label class="form-check-label" for="{{ $cashMethod['id'] }}_{{ $method }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="text-muted fs-sm mt-2 js-cash-rounding-method-example">Example: Rs 1043.28 becomes Rs 1043.00.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Apply Rounding To</h5>
                            <div class="text-muted fs-sm mt-1">Choose which payment methods use cash rounding in POS.</div>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="{{ $cashApply['name'] }}" class="js-cash-rounding-apply-value" value="{{ $applyValue }}">
                            <div class="merchant-settings-grid">
                                @foreach ([
                                    'cash' => 'Cash',
                                    'upi' => 'UPI',
                                    'card' => 'Card',
                                ] as $method => $label)
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input js-cash-rounding-apply" id="cash_rounding_apply_{{ $method }}" value="{{ $method }}" @checked(in_array($method, $applyMethods, true))>
                                        <label class="form-check-label fw-semibold" for="cash_rounding_apply_{{ $method }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @if ($errors->has($cashApply['errorKey']))
                                <div class="invalid-feedback d-block">{{ $errors->first($cashApply['errorKey']) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="ph-info me-1"></i>
                        Cash rounding is applied only to the final payable amount after discounts, shipping and taxes are calculated. Product prices, taxes, discounts and reports continue to use the exact calculated values.
                    </div>

                    @php
                        $tileSize = $field('pos', 'product.tile_size');
                        $tileOptions = [
                            'compact' => ['label' => 'Compact', 'px' => 130, 'cells' => 12],
                            'comfortable' => ['label' => 'Comfortable', 'px' => 150, 'cells' => 6],
                            'spacious' => ['label' => 'Spacious', 'px' => 180, 'cells' => 4],
                        ];
                    @endphp
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Product Tiles</h5>
                            <div class="text-muted fs-sm mt-1">Control how product cards appear inside POS.</div>
                        </div>
                        <div class="card-body">
                            <label class="form-label fw-semibold d-block">Tile size</label>
                            <div class="pos-tile-size-grid">
                                @foreach ($tileOptions as $size => $option)
                                    <label class="pos-tile-size-option" for="{{ $tileSize['id'] }}_{{ $size }}">
                                        <input
                                            type="radio"
                                            class="form-check-input visually-hidden"
                                            id="{{ $tileSize['id'] }}_{{ $size }}"
                                            name="{{ $tileSize['name'] }}"
                                            value="{{ $size }}"
                                            @checked($tileSize['value'] === $size)
                                        >
                                        <div class="pos-tile-size-icon is-{{ $size }}" aria-hidden="true">
                                            @for ($i = 0; $i < $option['cells']; $i++)
                                                <span></span>
                                            @endfor
                                        </div>
                                        <div class="fw-semibold">{{ $option['label'] }} ({{ $option['px'] }} px)</div>
                                    </label>
                                @endforeach
                            </div>
                            @if ($errors->has($tileSize['errorKey']))
                                <div class="invalid-feedback d-block">{{ $errors->first($tileSize['errorKey']) }}</div>
                            @endif
                            <div class="text-muted fs-sm mt-2">How wide each product tile is. Smaller fits more per row; larger is easier to tap on touch screens.</div>
                        </div>
                    </div>

                    @include('merchant.settings.partials.setting-card', [
                        'title' => 'Cart Feedback',
                        'description' => 'Control feedback when products are added to the cart.',
                        'fields' => [
                            ['group' => 'pos', 'key' => 'cart.play_add_sound', 'label' => 'Play a sound when an item is added to the cart', 'kind' => 'boolean'],
                        ],
                        'field' => $field,
                        'selectOptions' => $selectOptions,
                    ])

                    @include('merchant.settings.partials.setting-card', [
                        'title' => 'Order Defaults',
                        'description' => 'Control how POS orders are created.',
                        'fields' => [
                            ['group' => 'pos', 'key' => 'order.allow_order_discount', 'label' => 'Allow order discount', 'kind' => 'boolean'],
                            ['group' => 'pos', 'key' => 'order.allow_item_discount', 'label' => 'Allow item discount', 'kind' => 'boolean'],
                        ],
                        'field' => $field,
                        'selectOptions' => $selectOptions,
                    ])

                    @include('merchant.settings.partials.setting-card', [
                        'title' => 'Held Orders',
                        'description' => 'Control how long held POS orders remain available.',
                        'fields' => [
                            ['group' => 'pos', 'key' => 'held_order.expiry_days', 'label' => 'Held order expiry days', 'kind' => 'number'],
                        ],
                        'field' => $field,
                        'selectOptions' => $selectOptions,
                    ])
                </div>

                <div class="tab-pane fade" id="settings_orders" role="tabpanel">
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Orders</h5>
                        </div>
                        <div class="card-body text-muted">
                            Online order preferences will appear here later.
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_inventory" role="tabpanel">
                    @include('merchant.settings.partials.setting-card', [
                        'title' => 'Inventory',
                        'description' => 'Keep stock control predictable during checkout.',
                        'fields' => [
                            ['group' => 'inventory', 'key' => 'allow_negative_stock', 'label' => 'Prevent selling when stock reaches zero', 'kind' => 'inverse_boolean'],
                            ['group' => 'inventory', 'key' => 'show_low_stock_warning', 'label' => 'Low stock alert', 'kind' => 'boolean'],
                            ['group' => 'inventory', 'key' => 'low_stock_default', 'label' => 'Notify when stock falls below', 'kind' => 'number'],
                        ],
                        'field' => $field,
                        'selectOptions' => $selectOptions,
                    ])
                </div>

                <div class="tab-pane fade" id="settings_products" role="tabpanel">
                    @include('merchant.settings.partials.setting-card', [
                        'title' => 'Barcode',
                        'description' => 'Default barcode behaviour for products.',
                        'fields' => [
                            ['group' => 'product', 'key' => 'barcode.auto_generate', 'label' => 'Generate automatically', 'kind' => 'boolean'],
                        ],
                        'field' => $field,
                        'selectOptions' => $selectOptions,
                    ])
                </div>

                <div class="tab-pane fade" id="settings_payments" role="tabpanel">
                    @php
                        $paymentDefault = $field('payment', 'default_payment_method');
                        $paymentMethods = [
                            'cash' => ['setting' => 'allow_cash', 'label' => 'Cash', 'type' => 'Cash', 'badges' => []],
                            'card' => ['setting' => 'allow_card', 'label' => 'Card', 'type' => 'Card', 'badges' => ['Requires reference']],
                            'upi' => ['setting' => 'allow_upi', 'label' => 'UPI', 'type' => 'Digital', 'badges' => ['Requires reference']],
                            'credit' => ['setting' => 'allow_credit', 'label' => 'Credit', 'type' => 'Credit', 'badges' => ['Pay later']],
                        ];
                    @endphp

                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Methods</h5>
                            <div class="text-muted fs-sm mt-1">Turn manual tender types on or off for POS checkout.</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 payment-methods-table">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Type</th>
                                        <th>Configuration</th>
                                        <th class="text-center">Default</th>
                                        <th class="text-center">Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($paymentMethods as $method => $methodConfig)
                                    @php
                                        $methodField = $field('payment', $methodConfig['setting']);
                                        $hasError = $errors->has($methodField['errorKey']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $methodConfig['label'] }}</div>
                                            <div class="payment-method-code">{{ $method }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-10 text-body">{{ $methodConfig['type'] }}</span>
                                            @foreach ($methodConfig['badges'] as $badge)
                                                <span class="badge bg-light text-muted border ms-1">{{ $badge }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-muted">-</td>
                                        <td class="text-center">
                                            <input
                                                type="radio"
                                                class="form-check-input"
                                                id="{{ $paymentDefault['id'] }}_{{ $method }}"
                                                name="{{ $paymentDefault['name'] }}"
                                                value="{{ $method }}"
                                                @checked($paymentDefault['value'] === $method)
                                                aria-label="Use {{ $methodConfig['label'] }} as default payment method"
                                            >
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="{{ $methodField['name'] }}" value="0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input {{ $hasError ? 'is-invalid' : '' }}"
                                                id="{{ $methodField['id'] }}"
                                                name="{{ $methodField['name'] }}"
                                                value="1"
                                                @checked((bool) $methodField['value'])
                                                aria-label="Enable {{ $methodConfig['label'] }} at checkout"
                                            >
                                            @if ($hasError)
                                                <div class="invalid-feedback d-block">{{ $errors->first($methodField['errorKey']) }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($errors->has($paymentDefault['errorKey']))
                                <div class="invalid-feedback d-block px-3 pb-3">{{ $errors->first($paymentDefault['errorKey']) }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_receipts" role="tabpanel">
                    <div class="receipt-settings-layout">
                        <div>
                            @include('merchant.settings.partials.setting-card', [
                                'title' => 'What to show',
                                'description' => 'Toggle the optional pieces on or off so receipts stay clean or detailed as needed.',
                                'fields' => [
                                    ['group' => 'pos', 'key' => 'receipt.show_shop_name', 'label' => 'Shop Name', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_address', 'label' => 'Address', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_phone', 'label' => 'Phone', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_gst_number', 'label' => 'GST Number', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_customer', 'label' => 'Customer name + phone', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_cashier', 'label' => 'Cashier name', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_tax_breakdown', 'label' => 'Tax breakdown', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_barcode', 'label' => 'Sale barcode', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_qr_code', 'label' => 'QR Code', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.show_order_number', 'label' => 'Order Number', 'kind' => 'boolean'],
                                ],
                                'field' => $field,
                                'selectOptions' => $selectOptions,
                            ])

                            @include('merchant.settings.partials.setting-card', [
                                'title' => 'Line item details',
                                'description' => 'Extra codes under each item and the GST HSN-wise tax summary. Off by default; turn them on for tax-invoice compliance.',
                                'fields' => [
                                    ['group' => 'pos', 'key' => 'receipt.line_item.show_sku', 'label' => 'SKU under each item', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.line_item.show_hsn_code', 'label' => 'HSN code under each item', 'kind' => 'boolean'],
                                    ['group' => 'pos', 'key' => 'receipt.line_item.show_hsn_summary', 'label' => 'HSN-wise tax summary (GST)', 'kind' => 'boolean'],
                                ],
                                'field' => $field,
                                'selectOptions' => $selectOptions,
                            ])

                            @include('merchant.settings.partials.setting-card', [
                                'title' => 'Receipt text',
                                'description' => 'Free-form text printed below the totals and at the very bottom.',
                                'fields' => [
                                    ['group' => 'pos', 'key' => 'receipt.footer', 'label' => 'Footer text', 'kind' => 'textarea', 'rows' => 3],
                                    ['group' => 'pos', 'key' => 'receipt.return_policy', 'label' => 'Return policy', 'kind' => 'textarea', 'rows' => 3],
                                ],
                                'field' => $field,
                                'selectOptions' => $selectOptions,
                            ])
                        </div>

                        <div class="card merchant-settings-card receipt-preview-card">
                            <div class="card-header">
                                <h5 class="mb-0">Live Preview</h5>
                                <div class="text-muted fs-sm mt-1">Sample receipt using current options.</div>
                            </div>
                            <div class="receipt-preview-shell">
                                <div class="receipt-preview-paper">
                                    <div class="text-center">
                                        <div class="fw-bold" data-receipt-preview="shop_name">Demo Retail Store</div>
                                        <div data-receipt-preview="address">Main Road, Nashik - 422001</div>
                                        <div data-receipt-preview="phone">Phone: 9876543210</div>
                                        <div data-receipt-preview="gst_number">GSTIN: 27ABCDE1234F1Z5</div>
                                    </div>

                                    <div class="receipt-preview-rule"></div>

                                    <div class="receipt-preview-row" data-receipt-preview="order_number">
                                        <span>Invoice</span>
                                        <span>POS-1001</span>
                                    </div>
                                    <div class="receipt-preview-row">
                                        <span>Date</span>
                                        <span>18-Jul-2026</span>
                                    </div>
                                    <div data-receipt-preview="cashier">Cashier: Ramesh</div>
                                    <div data-receipt-preview="customer">Customer: Rahul Sharma / 9876543210</div>

                                    <div class="receipt-preview-rule"></div>

                                    <div>Berry Kajal</div>
                                    <div class="text-muted" data-receipt-preview="item_sku">SKU: KAJAL-BLK-01</div>
                                    <div class="text-muted" data-receipt-preview="item_hsn">HSN: 3304</div>
                                    <div class="receipt-preview-row">
                                        <span>1 x 1349.00</span>
                                        <span>1349.00</span>
                                    </div>
                                    <div>Satin Lipstick</div>
                                    <div class="receipt-preview-row">
                                        <span>2 x 299.00</span>
                                        <span>598.00</span>
                                    </div>

                                    <div class="receipt-preview-rule"></div>

                                    <div class="receipt-preview-row">
                                        <span>Subtotal</span>
                                        <span>1947.00</span>
                                    </div>
                                    <div class="receipt-preview-row">
                                        <span>Discount</span>
                                        <span>100.00</span>
                                    </div>
                                    <div class="receipt-preview-row" data-receipt-preview="tax_breakdown">
                                        <span>Tax</span>
                                        <span>0.00</span>
                                    </div>
                                    <div data-receipt-preview="hsn_summary">
                                        <div class="receipt-preview-rule"></div>
                                        <div class="fw-semibold">HSN Summary</div>
                                        <div class="receipt-preview-row">
                                            <span>3304 GST</span>
                                            <span>46.18</span>
                                        </div>
                                    </div>
                                    <div class="receipt-preview-row fw-bold">
                                        <span>Total</span>
                                        <span>1847.00</span>
                                    </div>
                                    <div class="receipt-preview-row">
                                        <span>Cash</span>
                                        <span>1900.00</span>
                                    </div>
                                    <div class="receipt-preview-row">
                                        <span>Change</span>
                                        <span>53.00</span>
                                    </div>

                                    <div data-receipt-preview="barcode">
                                        <div class="receipt-preview-barcode"></div>
                                        <div class="text-center">POS-1001</div>
                                    </div>
                                    <div data-receipt-preview="qr_code">
                                        <div class="receipt-preview-qr">QR</div>
                                        <div class="text-center text-muted">Scan to view</div>
                                    </div>

                                    <div class="receipt-preview-rule"></div>
                                    <div class="text-center" data-receipt-preview="footer">Thank you for shopping with us.</div>
                                    <div class="text-center text-muted mt-1" data-receipt-preview="return_policy"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_notifications" role="tabpanel">
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Notifications</h5>
                        </div>
                        <div class="card-body text-muted">
                            SMS, email, and WhatsApp preferences will appear here later.
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_advanced" role="tabpanel">
                    <div class="card merchant-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Advanced</h5>
                        </div>
                        <div class="card-body text-muted">
                            Advanced configuration will appear here later.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="merchant-settings-savebar">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('merchant.dashboard') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-1"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const applyInputs = Array.from(document.querySelectorAll('.js-cash-rounding-apply'));
            const applyValueInput = document.querySelector('.js-cash-rounding-apply-value');
            const methodInputs = Array.from(document.querySelectorAll('.js-cash-rounding-method'));
            const receiptSettings = {
                'receipt.show_shop_name': ['shop_name'],
                'receipt.show_address': ['address'],
                'receipt.show_phone': ['phone'],
                'receipt.show_gst_number': ['gst_number'],
                'receipt.show_customer': ['customer'],
                'receipt.show_cashier': ['cashier'],
                'receipt.show_barcode': ['barcode'],
                'receipt.show_qr_code': ['qr_code'],
                'receipt.show_order_number': ['order_number'],
                'receipt.show_tax_breakdown': ['tax_breakdown'],
                'receipt.line_item.show_sku': ['item_sku'],
                'receipt.line_item.show_hsn_code': ['item_hsn'],
                'receipt.line_item.show_hsn_summary': ['hsn_summary'],
            };
            const footerInput = document.querySelector('textarea[name="settings[pos][receipt.footer]"]');
            const returnPolicyInput = document.querySelector('textarea[name="settings[pos][receipt.return_policy]"]');
            const exampleAmount = 1043.28;
            const formatMoney = (value) => `Rs ${Number(value || 0).toFixed(2)}`;
            const selectedMethod = () => methodInputs.find((input) => input.checked)?.value || 'nearest';
            const roundedAmount = (amount, method) => {
                if (method === 'up') {
                    return Math.ceil(amount);
                }

                if (method === 'down') {
                    return Math.floor(amount);
                }

                return Math.round(amount);
            };
            const renderPreview = () => {
                const method = selectedMethod();
                const currentRounded = roundedAmount(exampleAmount, method);

                const methodExampleEl = document.querySelector('.js-cash-rounding-method-example');
                if (methodExampleEl) {
                    methodExampleEl.textContent = `Example: ${formatMoney(exampleAmount)} becomes ${formatMoney(currentRounded)}.`;
                }
            };

            const syncApplyValue = () => {
                const selected = applyInputs
                    .filter((input) => input.checked)
                    .map((input) => input.value);
                const allMethods = applyInputs.map((input) => input.value);

                applyValueInput.value = selected.length === allMethods.length ? 'all' : (selected.join(',') || 'cash');
            };

            applyInputs.forEach((input) => input.addEventListener('change', syncApplyValue));
            methodInputs.forEach((input) => {
                input?.addEventListener('input', renderPreview);
                input?.addEventListener('change', renderPreview);
            });

            const receiptCheckbox = (key) => document.querySelector(`input[type="checkbox"][name="settings[pos][${key}]"]`);
            const setReceiptPreviewVisible = (previewKey, visible) => {
                document.querySelectorAll(`[data-receipt-preview="${previewKey}"]`).forEach((element) => {
                    element.classList.toggle('d-none', !visible);
                });
            };
            const renderReceiptPreview = () => {
                Object.entries(receiptSettings).forEach(([settingKey, previewKeys]) => {
                    const checkbox = receiptCheckbox(settingKey);
                    previewKeys.forEach((previewKey) => setReceiptPreviewVisible(previewKey, checkbox?.checked ?? true));
                });

                const footerPreview = document.querySelector('[data-receipt-preview="footer"]');
                if (footerPreview) {
                    footerPreview.textContent = footerInput?.value?.trim() || 'Thank you for shopping with us.';
                }

                const returnPolicyPreview = document.querySelector('[data-receipt-preview="return_policy"]');
                if (returnPolicyPreview) {
                    const text = returnPolicyInput?.value?.trim() || '';
                    returnPolicyPreview.textContent = text;
                    returnPolicyPreview.classList.toggle('d-none', text === '');
                }
            };

            Object.keys(receiptSettings).forEach((settingKey) => {
                receiptCheckbox(settingKey)?.addEventListener('change', renderReceiptPreview);
            });
            footerInput?.addEventListener('input', renderReceiptPreview);
            returnPolicyInput?.addEventListener('input', renderReceiptPreview);
            syncApplyValue();
            renderPreview();
            renderReceiptPreview();
        });
    </script>
@endpush
