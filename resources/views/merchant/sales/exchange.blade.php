{{-- Purpose: Processes a POS exchange with returned original lines and replacement items. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Exchange sale"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Sales History' => route('merchant.sales.index'), $order->order_number => route('merchant.sales.show', $order), 'Exchange sale' => null]"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string $value) use ($posCurrency): string {
            $amount = number_format((float) $value, (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? 'INR ');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
        $originalItems = $order->items->map(fn ($item): array => [
            'id' => $item->getKey(),
            'name' => $item->product_name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'max' => $exchangeableQuantities[$item->getKey()] ?? 0,
        ])->values();
        $variantOptions = $replacementVariants->map(fn ($variant): array => [
            'variant_id' => $variant->getKey(),
            'product_name' => $variant->product?->product_name ?? 'Product',
            'name' => $variant->product?->product_name ?? 'Product',
            'variant_name' => $variant->name ?: 'Default',
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => (float) $variant->selling_price,
            'stock' => (int) $variant->stock_quantity,
        ])->values();
        $showReplacementSearch = in_array($replacementSelector, ['search', 'both'], true);
        $showReplacementDropdown = in_array($replacementSelector, ['dropdown', 'both'], true);
    @endphp

    <form method="POST" action="{{ route('merchant.sales.exchange.process', $order) }}" class="js-exchange-form">
        @csrf

        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('merchant.sales.show', $order) }}" class="text-body"><i class="ph-arrow-left"></i></a>
                    <h3 class="mb-0">Exchange sale</h3>
                </div>
                <div class="text-muted">Original: {{ $order->order_number }}. Returned value uses the original sold line price after item discount.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exchangeHelpModal" aria-label="How exchange works">
                    <i class="ph-question me-1"></i>
                    Help
                </button>
                <button type="submit" class="btn btn-warning">
                    <i class="ph-swap me-2"></i>
                    Process exchange
                </button>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <div
            class="row g-3"
            data-search-url="{{ route('merchant.pos.search') }}"
            data-original-items='@json($originalItems)'
        >
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Return from original sale</h5>
                    </div>
                    <div class="card-body border-bottom">
                        <label for="exchange_return_scan" class="form-label">Scan old item</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ph-barcode"></i></span>
                            <input id="exchange_return_scan" type="search" class="form-control js-return-scan" placeholder="Scan barcode, SKU, or type old item">
                            <button type="button" class="btn btn-light js-return-scan-add">
                                <i class="ph-plus me-1"></i>
                                Add
                            </button>
                        </div>
                        <div class="form-text js-return-scan-message">Scanning old item increases its exchange quantity.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end" style="width: 180px;">Original prices</th>
                                    <th style="width: 220px;">Exchange qty</th>
                                    <th class="text-end">Return value</th>
                                    <th style="width: 190px;">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    @php
                                        $remaining = $exchangeableQuantities[$item->getKey()] ?? 0;
                                        $unitReturnValue = (float) $item->line_total / max(1, (int) $item->quantity);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            <code>{{ $item->sku ?: $item->barcode ?: '-' }}</code>
                                        </td>
                                        <td class="text-end">
                                            <div class="small text-muted">MRP {{ $money($item->unit_mrp) }}</div>
                                            <div class="small text-muted">Selling {{ $money($item->unit_price) }}</div>
                                            <div class="fw-semibold">Paid / item {{ $money($unitReturnValue) }}</div>
                                        </td>
                                        <td>
                                            <input
                                                name="returned_items[{{ $item->getKey() }}][quantity]"
                                                type="number"
                                                min="0"
                                                max="{{ $remaining }}"
                                                value="{{ old('returned_items.'.$item->getKey().'.quantity', 0) }}"
                                                class="form-control js-return-qty"
                                                data-item-id="{{ $item->getKey() }}"
                                                data-unit-return="{{ $unitReturnValue }}"
                                                @disabled($remaining < 1)
                                            >
                                            <div class="text-muted small text-end">of {{ $remaining }} exchangeable</div>
                                        </td>
                                        <td class="text-end fw-semibold js-return-line-total">{{ $money($remaining > 0 ? $unitReturnValue : 0) }}</td>
                                        <td>
                                            <label class="form-check mb-0">
                                                <input name="returned_items[{{ $item->getKey() }}][restock]" value="0" type="hidden">
                                                <input name="returned_items[{{ $item->getKey() }}][restock]" value="1" type="checkbox" class="form-check-input" @checked($remaining > 0) @disabled($remaining < 1)>
                                                <span class="form-check-label">Restock</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Replacement items</h5>
                    </div>
                    <div class="card-body border-bottom">
                        @if($showReplacementSearch)
                            <div class="{{ $showReplacementDropdown ? 'mb-3' : '' }}">
                                <label for="exchange_replacement_search" class="form-label">Search replacement product</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                                    <input id="exchange_replacement_search" type="search" class="form-control js-replacement-search" placeholder="Scan barcode, search SKU, or product name" autocomplete="off">
                                    <button type="button" class="btn btn-primary js-replacement-search-button">
                                        <i class="ph-barcode me-1"></i>
                                        Search
                                    </button>
                                </div>
                                <div class="form-text js-replacement-search-message">Barcode and exact SKU matches add automatically.</div>
                                <div class="list-group mt-2 d-none js-replacement-results"></div>
                            </div>
                        @endif
                        @if($showReplacementDropdown)
                            <div>
                                <label for="exchange_replacement_dropdown" class="form-label">Choose replacement product</label>
                                <div class="input-group">
                                    <select id="exchange_replacement_dropdown" class="form-select js-replacement-dropdown">
                                        <option value="">Choose replacement product</option>
                                        @foreach($variantOptions as $option)
                                            <option value="{{ $option['variant_id'] }}">
                                                {{ $option['product_name'] }} - {{ $option['variant_name'] }}{{ $option['sku'] ? ' ('.$option['sku'].')' : '' }} - {{ $money($option['price']) }} / {{ $option['stock'] }} stock
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-light js-replacement-dropdown-add">
                                        <i class="ph-plus me-1"></i>
                                        Add
                                    </button>
                                </div>
                                <div class="form-text">Useful for small shops without scanners. Large shops can switch this off in POS settings.</div>
                            </div>
                        @endif
                        @if(! $showReplacementSearch && ! $showReplacementDropdown)
                            <div class="text-muted">Replacement selection is not available.</div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Replacement product</th>
                                    <th style="width: 130px;">Qty</th>
                                    <th class="text-end">Unit price</th>
                                    <th class="text-end">Line total</th>
                                    <th class="text-center" style="width: 70px;">Remove</th>
                                </tr>
                            </thead>
                            <tbody class="js-replacement-body"></tbody>
                        </table>
                    </div>
                    <div class="card-body text-muted js-replacement-empty">No replacement items added.</div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Settlement</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="settlement_method" class="form-label">Settlement method</label>
                            <select id="settlement_method" name="settlement_method" class="form-select">
                                @foreach($paymentMethods as $method => $label)
                                    <option value="{{ $method }}" @selected(old('settlement_method', $order->payment_method) === $method)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exchange_notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                            <textarea id="exchange_notes" name="notes" rows="3" maxlength="500" class="form-control" placeholder="Reason, approval note, or item condition">{{ old('notes') }}</textarea>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Returned value</span>
                            <span class="fw-semibold js-returned-total">{{ $money(0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Replacement value</span>
                            <span class="fw-semibold js-replacement-total">{{ $money(0) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold js-difference-label">Difference</span>
                            <span class="fw-bold js-difference-total">{{ $money(0) }}</span>
                        </div>
                        <div class="text-muted small mt-2 js-settlement-help">Even exchange.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="modal fade" id="exchangeHelpModal" tabindex="-1" aria-labelledby="exchangeHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exchangeHelpModalLabel">How exchange works</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Process</div>
                        <ol class="mb-0 ps-3">
                            <li>Scan or add the old item from the original sale.</li>
                            <li>Set the exchange quantity and keep Restock checked only if the item returns to stock.</li>
                            <li>Add the replacement item by scan, SKU, name, or dropdown.</li>
                            <li>Collect extra, refund balance, or complete an even exchange.</li>
                        </ol>
                    </div>
                    <div class="border rounded p-3 bg-light">
                        <div class="fw-semibold mb-2">Example only</div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Old item MRP</span>
                            <span>{{ $money(1999) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Old item selling price</span>
                            <span>{{ $money(1499) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Paid / item after discount</span>
                            <span>{{ $money(1399) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Replacement item</span>
                            <span>{{ $money(1699) }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-semibold">
                            <span>Customer pays extra</span>
                            <span>{{ $money(300) }}</span>
                        </div>
                    </div>
                    <div class="text-muted small mt-3">
                        Exchange value always uses the original paid item value from the old bill, not today's product price.
                    </div>
                    <div class="mt-3">
                        <div class="fw-semibold mb-2">Do not restock if</div>
                        <ul class="mb-0 ps-3">
                            <li>The item is damaged or used.</li>
                            <li>The seal or packaging is broken.</li>
                            <li>The item is expiry-sensitive and cannot be resold.</li>
                            <li>The returned item condition is unclear.</li>
                            <li>Manager approval says it should stay out of stock.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: '{{ $posCurrency['currency'] ?? 'INR' }}' });
            const variants = @json($variantOptions);
            const root = document.querySelector('[data-search-url]');
            const searchUrl = root.dataset.searchUrl;
            const originalItems = JSON.parse(root.dataset.originalItems || '[]');
            const body = document.querySelector('.js-replacement-body');
            const replacementEmpty = document.querySelector('.js-replacement-empty');
            const replacementInput = document.querySelector('.js-replacement-search');
            const replacementResults = document.querySelector('.js-replacement-results');
            const replacementMessage = document.querySelector('.js-replacement-search-message');
            const replacementDropdown = document.querySelector('.js-replacement-dropdown');
            const returnInput = document.querySelector('.js-return-scan');
            const returnMessage = document.querySelector('.js-return-scan-message');
            let rowIndex = 0;

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = String(value ?? '');
                return div.innerHTML;
            }

            function addReplacementItem(item, quantity = 1) {
                const existing = body.querySelector(`[data-variant-id="${item.variant_id}"]`);
                if (existing) {
                    const input = existing.querySelector('.js-replacement-qty');
                    input.value = Math.min(Number(input.value || 0) + quantity, Number(item.stock || 0));
                    calculate();
                    return;
                }

                const tr = document.createElement('tr');
                tr.dataset.variantId = item.variant_id;
                tr.dataset.price = item.price;
                tr.innerHTML = `
                    <td>
                        <input type="hidden" name="replacement_items[${rowIndex}][product_variant_id]" value="${item.variant_id}">
                        <div class="fw-semibold">${escapeHtml(item.product_name || item.name || 'Product')}</div>
                        <div class="small text-muted">${escapeHtml(item.variant_name || 'Default')} ${item.sku ? ' / ' + escapeHtml(item.sku) : ''}</div>
                        <div class="small ${Number(item.stock || 0) > 0 ? 'text-success' : 'text-danger'}">${Number(item.stock || 0)} in stock</div>
                    </td>
                    <td>
                        <input name="replacement_items[${rowIndex}][quantity]" type="number" min="1" value="${quantity}" class="form-control js-replacement-qty">
                    </td>
                    <td class="text-end js-replacement-unit">${money.format(Number(item.price || 0))}</td>
                    <td class="text-end fw-semibold js-replacement-line">${money.format(Number(item.price || 0) * quantity)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-light btn-icon btn-sm js-remove-replacement" aria-label="Remove replacement item">
                            <i class="ph-x"></i>
                        </button>
                    </td>
                `;
                body.appendChild(tr);
                rowIndex += 1;
                if (replacementInput) {
                    replacementInput.value = '';
                }
                hideReplacementResults();
                calculate();
            }

            function hideReplacementResults() {
                if (!replacementResults) {
                    return;
                }
                replacementResults.classList.add('d-none');
                replacementResults.innerHTML = '';
            }

            function renderReplacementResults(items) {
                if (!replacementResults) {
                    return;
                }
                if (!items.length) {
                    replacementResults.innerHTML = '<div class="list-group-item text-muted">No replacement product found.</div>';
                    replacementResults.classList.remove('d-none');
                    return;
                }

                replacementResults.innerHTML = items.map((item) => `
                    <button type="button" class="list-group-item list-group-item-action js-replacement-result" data-item='${escapeHtml(JSON.stringify(item))}'>
                        <span class="d-flex justify-content-between gap-3">
                            <span>
                                <span class="fw-semibold d-block">${escapeHtml(item.product_name || item.name || 'Product')}</span>
                                <span class="small text-muted">${escapeHtml(item.variant_name || 'Default')} ${item.sku ? ' / ' + escapeHtml(item.sku) : ''}</span>
                            </span>
                            <span class="text-end text-nowrap">
                                <span class="fw-semibold d-block">${money.format(Number(item.price || 0))}</span>
                                <span class="small ${Number(item.stock || 0) > 0 ? 'text-success' : 'text-danger'}">${Number(item.stock || 0)} stock</span>
                            </span>
                        </span>
                    </button>
                `).join('');
                replacementResults.classList.remove('d-none');
            }

            async function searchReplacement(scannerMode = false) {
                if (!replacementInput || !replacementMessage) {
                    return;
                }
                const query = replacementInput.value.trim();
                if (!query) {
                    replacementMessage.textContent = 'Scan barcode, search SKU, or product name.';
                    return;
                }

                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('scanner_mode', scannerMode ? '1' : '0');
                replacementMessage.textContent = 'Searching...';

                try {
                    const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                    const payload = await response.json();

                    if (!response.ok || payload.status === 'conflict') {
                        replacementMessage.textContent = payload.message || 'Could not search replacement product.';
                        hideReplacementResults();
                        return;
                    }

                    if (payload.auto_add && payload.item) {
                        addReplacementItem(payload.item);
                        replacementMessage.textContent = 'Replacement item added.';
                        return;
                    }

                    const items = payload.items || [];
                    replacementMessage.textContent = items.length ? 'Select a replacement item.' : (payload.message || 'No replacement product found.');
                    renderReplacementResults(items);
                } catch (error) {
                    replacementMessage.textContent = 'Could not search replacement product.';
                }
            }

            function addDropdownReplacement() {
                if (!replacementDropdown || !replacementDropdown.value) {
                    return;
                }

                const item = variants.find((variant) => String(variant.variant_id) === String(replacementDropdown.value));
                if (item) {
                    addReplacementItem(item);
                    replacementDropdown.value = '';
                }
            }

            function addReturnedScan() {
                const query = returnInput.value.trim().toLowerCase();
                if (!query) {
                    returnMessage.textContent = 'Scan or type an old item barcode/SKU.';
                    return;
                }

                const match = originalItems.find((item) =>
                    String(item.barcode || '').toLowerCase() === query
                    || String(item.sku || '').toLowerCase() === query
                    || String(item.name || '').toLowerCase().includes(query)
                );

                if (!match) {
                    returnMessage.textContent = 'Old item not found in this original sale.';
                    return;
                }

                const input = document.querySelector(`.js-return-qty[data-item-id="${match.id}"]`);
                if (!input || input.disabled) {
                    returnMessage.textContent = 'This old item has no exchangeable quantity left.';
                    return;
                }

                const nextQty = Math.min(Number(input.max || match.max || 0), Number(input.value || 0) + 1);
                input.value = nextQty;
                returnInput.value = '';
                returnMessage.textContent = `${match.name} added to return.`;
                calculate();
            }

            function calculate() {
                let returnedTotal = 0;
                document.querySelectorAll('.js-return-qty').forEach((input) => {
                    const qty = Math.max(0, Number(input.value || 0));
                    const unit = Number(input.dataset.unitReturn || 0);
                    const total = qty * unit;
                    returnedTotal += total;
                    input.closest('tr').querySelector('.js-return-line-total').textContent = money.format(total);
                });

                let replacementTotal = 0;
                document.querySelectorAll('.js-replacement-body tr').forEach((row) => {
                    const qty = Math.max(0, Number(row.querySelector('.js-replacement-qty').value || 0));
                    const price = Number(row.dataset.price || 0);
                    const total = qty * price;
                    replacementTotal += total;
                    row.querySelector('.js-replacement-unit').textContent = money.format(price);
                    row.querySelector('.js-replacement-line').textContent = money.format(total);
                });

                const difference = replacementTotal - returnedTotal;
                document.querySelector('.js-returned-total').textContent = money.format(returnedTotal);
                document.querySelector('.js-replacement-total').textContent = money.format(replacementTotal);
                document.querySelector('.js-difference-total').textContent = money.format(Math.abs(difference));
                document.querySelector('.js-difference-label').textContent = difference > 0 ? 'Collect extra' : (difference < 0 ? 'Refund balance' : 'Difference');
                document.querySelector('.js-settlement-help').textContent = difference > 0
                    ? 'Customer pays the extra amount.'
                    : (difference < 0 ? 'Return the balance, or adjust credit if this was a credit sale.' : 'Even exchange.');
                replacementEmpty.classList.toggle('d-none', body.children.length > 0);
            }

            document.querySelector('.js-return-scan-add')?.addEventListener('click', addReturnedScan);
            document.querySelector('.js-replacement-search-button')?.addEventListener('click', () => searchReplacement(false));
            document.querySelector('.js-replacement-dropdown-add')?.addEventListener('click', addDropdownReplacement);
            returnInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addReturnedScan();
                }
            });
            replacementInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchReplacement(true);
                }
            });
            replacementDropdown?.addEventListener('change', addDropdownReplacement);
            document.addEventListener('input', (event) => {
                if (event.target.closest('.js-return-qty, .js-replacement-qty')) {
                    calculate();
                }
            });
            document.addEventListener('click', (event) => {
                const result = event.target.closest('.js-replacement-result');
                if (result) {
                    addReplacementItem(JSON.parse(result.dataset.item));
                    if (replacementMessage) {
                        replacementMessage.textContent = 'Replacement item added.';
                    }
                    return;
                }

                const remove = event.target.closest('.js-remove-replacement');
                if (remove) {
                    remove.closest('tr').remove();
                    calculate();
                }
            });

            calculate();
        });
    </script>
@endpush
