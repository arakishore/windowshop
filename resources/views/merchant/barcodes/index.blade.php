{{-- Purpose: Merchant barcode label selection and print setup. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Barcode Labels"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Barcode Labels' => null]"
        :action-url="route('merchant.products.index')"
        action-label="Back to Products"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex flex-column flex-xl-row gap-3 justify-content-xl-between align-items-xl-end">
                <form method="GET" action="{{ route('merchant.barcodes.labels.index') }}" class="row g-2 align-items-end flex-fill">
                    <div class="col-lg-6">
                        <label for="q" class="form-label">Search product, variant, SKU or barcode</label>
                        <input id="q" name="q" value="{{ $filters['q'] }}" class="form-control" autocomplete="off">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-magnifying-glass me-2"></i>
                            Search
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('merchant.barcodes.generate-missing') }}">
                    @csrf
                    <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    <button type="submit" class="btn btn-light border">
                        <i class="ph-barcode me-2"></i>
                        Generate Missing Barcodes
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('merchant.barcodes.labels.print') }}" target="_blank">
            @csrf
            <input type="hidden" name="q" value="{{ $filters['q'] }}">
            <div class="card-body border-bottom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="template" class="form-label">Label Template</label>
                        <select id="template" name="template" class="form-select">
                            @foreach(collect($templates)->groupBy('group') as $group => $groupTemplates)
                                <optgroup label="{{ $group }}">
                                    @foreach($groupTemplates as $key => $template)
                                        <option value="{{ $key }}">{{ $template['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label d-block">Label Content</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($contentOptions as $key => $enabled)
                                <label class="form-check mb-0">
                                    <input type="checkbox" name="options[{{ $key }}]" value="1" class="form-check-input" @checked($enabled)>
                                    <span class="form-check-label">{{ \Illuminate\Support\Str::headline($key) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="bulk_label_quantity" class="form-label">Label Quantity</label>
                        <div class="input-group">
                            <input id="bulk_label_quantity" name="bulk_quantity" type="number" min="0" max="500" step="1" value="1" class="form-control js-bulk-label-quantity">
                            <button type="button" class="btn btn-light border js-apply-label-quantity">
                                Apply to All
                            </button>
                        </div>
                    </div>
                    <div class="col-md-auto d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-printer me-2"></i>
                            Print Preview
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Stock</th>
                            <th style="width: 150px;">Print Labels</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($variants as $variant)
                            <tr>
                                <td class="fw-semibold">{{ $variant->product?->product_name }}</td>
                                <td>{{ $variant->name ?: 'Default' }}</td>
                                <td>{{ $variant->sku ?: '-' }}</td>
                                <td>
                                    @if($variant->barcode)
                                        <code>{{ $variant->barcode }}</code>
                                    @else
                                        <span class="badge bg-warning bg-opacity-10 text-warning">Missing</span>
                                    @endif
                                </td>
                                <td class="text-end">INR {{ number_format((float) $variant->selling_price, 2) }}</td>
                                <td class="text-end">{{ number_format((int) $variant->stock_quantity) }}</td>
                                <td>
                                    <input type="hidden" name="variants[{{ $variant->id }}][selected]" value="1">
                                    <input type="hidden" name="variant_ids[]" value="{{ $variant->id }}">
                                    <input
                                        type="number"
                                        min="0"
                                        max="500"
                                        step="1"
                                        name="variants[{{ $variant->id }}][quantity]"
                                        value="0"
                                        placeholder="0"
                                        class="form-control form-control-sm text-end js-label-quantity"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No variants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="text-muted">Quantity means how many labels to print, not inventory quantity.</span>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-printer me-2"></i>
                    Print Preview
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bulkQuantity = document.querySelector('.js-bulk-label-quantity');
            const applyButton = document.querySelector('.js-apply-label-quantity');
            const quantityInputs = Array.from(document.querySelectorAll('.js-label-quantity'));

            applyButton?.addEventListener('click', () => {
                const quantity = Math.max(0, Math.min(500, Number.parseInt(bulkQuantity?.value || '0', 10) || 0));

                quantityInputs
                    .filter((input) => !input.disabled)
                    .forEach((input) => {
                        input.value = String(quantity);
                    });
            });
        });
    </script>
@endpush
