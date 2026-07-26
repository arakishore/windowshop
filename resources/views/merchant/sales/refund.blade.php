{{-- Purpose: Processes a refund for a completed POS sale. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Refund sale"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Sales History' => route('merchant.sales.index'), $order->order_number => route('merchant.sales.show', $order), 'Refund sale' => null]"
    />
@endsection

@section('content')
    @php
        $money = static function (float|int|string $value) use ($posCurrency): string {
            $amount = number_format((float) $value, (int) ($posCurrency['decimal_places'] ?? 2), (string) ($posCurrency['decimal_separator'] ?? '.'), (string) ($posCurrency['thousands_separator'] ?? ','));
            $symbol = (string) ($posCurrency['symbol'] ?? '₹');
            return ($posCurrency['symbol_position'] ?? 'before') === 'before' ? $symbol.$amount : $amount.' '.$symbol;
        };
    @endphp

    <form method="POST" action="{{ route('merchant.sales.refund.process', $order) }}" class="js-refund-form">
        @csrf
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('merchant.sales.show', $order) }}" class="text-body"><i class="ph-arrow-left"></i></a>
                    <h3 class="mb-0">Refund sale</h3>
                </div>
                <div class="text-muted">Pick the lines + quantities to refund. Stock follows the selected reason by default, then can be changed per line.</div>
            </div>
            <button type="submit" class="btn btn-warning">
                <i class="ph-arrow-counter-clockwise me-2"></i>
                Process refund
            </button>
        </div>

        <div class="row g-3">
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Lines</h5></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th style="width: 280px;">Refund qty</th>
                                    <th class="text-end">Unit price</th>
                                    <th class="text-end">Line total</th>
                                    <th style="width: 220px;">Restock this line</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    @php
                                        $remaining = $refundableQuantities[$item->getKey()] ?? 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            <code>{{ $item->sku ?: $item->barcode ?: '-' }}</code>
                                        </td>
                                        <td>
                                            <input
                                                name="items[{{ $item->getKey() }}][quantity]"
                                                type="number"
                                                min="0"
                                                max="{{ $remaining }}"
                                                value="{{ old("items.{$item->getKey()}.quantity", $remaining) }}"
                                                class="form-control js-refund-qty"
                                                data-unit="{{ (float) $item->line_total / max(1, (int) $item->quantity) }}"
                                                @disabled($remaining < 1)
                                            >
                                            <div class="text-muted small text-end">of {{ $remaining }} returnable</div>
                                            @error("items.{$item->getKey()}.quantity")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="text-end">{{ $money($item->unit_price) }}</td>
                                        <td class="text-end fw-semibold js-line-total">{{ $money($remaining * ((float) $item->line_total / max(1, (int) $item->quantity))) }}</td>
                                        <td>
                                            <label class="form-check">
                                                <input name="items[{{ $item->getKey() }}][do_not_restock]" value="1" type="checkbox" class="form-check-input js-do-not-restock" @disabled($remaining < 1)>
                                                <span class="form-check-label">do NOT restock</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Refund sale</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="return_reason_id" class="form-label">Reason <span class="text-danger">*</span></label>
                            <select id="return_reason_id" name="return_reason_id" class="form-select js-return-reason" required>
                                <option value="">Pick a reason</option>
                                @foreach($returnReasons as $reason)
                                    <option value="{{ $reason->getKey() }}" data-restock="{{ $reason->restock_by_default ? 1 : 0 }}" @selected(old('return_reason_id') == $reason->getKey())>{{ $reason->name }}</option>
                                @endforeach
                            </select>
                            @error('return_reason_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="refund_method" class="form-label">Refund to</label>
                            <select id="refund_method" name="refund_method" class="form-select">
                                @foreach($paymentMethods as $method => $label)
                                    <option value="{{ $method }}" @selected(old('refund_method', $order->payment_method) === $method)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="refund_notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                            <textarea id="refund_notes" name="notes" rows="3" maxlength="500" class="form-control @error('notes') is-invalid @enderror" placeholder="Manager note, customer comment, or item condition">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr>
                        <h6>Totals</h6>
                        <div class="d-flex justify-content-between text-muted">
                            <span>Refund subtotal</span>
                            <span class="js-refund-subtotal">{{ $money(0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted">
                            <span>Refund tax</span>
                            <span>{{ $money(0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold mt-3">
                            <span>Refund total</span>
                            <span class="js-refund-total">{{ $money(0) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: '{{ $posCurrency['currency'] ?? 'INR' }}' });
            const form = document.querySelector('.js-refund-form');
            const reason = document.querySelector('.js-return-reason');
            const checkboxes = Array.from(document.querySelectorAll('.js-do-not-restock'));
            let confirmedSubmit = false;

            function applyReasonDefault() {
                const selected = reason.options[reason.selectedIndex];
                if (!selected || !selected.value) {
                    return;
                }
                const restock = selected.dataset.restock === '1';
                checkboxes.forEach((checkbox) => {
                    if (!checkbox.disabled) {
                        checkbox.checked = !restock;
                    }
                });
            }

            function updateTotals() {
                let total = 0;
                document.querySelectorAll('.js-refund-qty').forEach((input) => {
                    const rowTotal = Math.max(0, Number(input.value || 0)) * Number(input.dataset.unit || 0);
                    total += rowTotal;
                    const cell = input.closest('tr').querySelector('.js-line-total');
                    if (cell) {
                        cell.textContent = money.format(rowTotal);
                    }
                });
                document.querySelector('.js-refund-subtotal').textContent = money.format(total);
                document.querySelector('.js-refund-total').textContent = money.format(total);
            }

            reason?.addEventListener('change', applyReasonDefault);
            document.addEventListener('input', (event) => {
                if (event.target.closest('.js-refund-qty')) {
                    updateTotals();
                }
            });
            form?.addEventListener('submit', (event) => {
                if (confirmedSubmit) {
                    return;
                }

                event.preventDefault();
                const message = 'Process this refund?<br><br>This will update stock/payment status and cannot be undone.';

                if (typeof bootbox === 'undefined') {
                    if (window.confirm('Process this refund?\n\nThis will update stock/payment status and cannot be undone.')) {
                        confirmedSubmit = true;
                        form.submit();
                    }
                    return;
                }

                bootbox.confirm({
                    title: 'Process refund?',
                    message,
                    centerVertical: true,
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-light',
                        },
                        confirm: {
                            label: 'Process refund',
                            className: 'btn-warning',
                        },
                    },
                    callback: (confirmed) => {
                        if (confirmed) {
                            confirmedSubmit = true;
                            form.submit();
                        }
                    },
                });
            });
            applyReasonDefault();
            updateTotals();
        });
    </script>
@endpush
