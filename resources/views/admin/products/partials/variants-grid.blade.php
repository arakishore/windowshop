<div class="card-body">
    @php
        $variantRows = $variantRows ?? $product->variants;
        $examples = array_slice($variantPreview['combinations'] ?? [], 0, 4);
    $hasSelectedVariantAttributes = ($variantPreview['selected_variant_value_count'] ?? 0) > 0;
    $hasGeneratedVariants = $product->variants->contains(fn ($variant) => $variant->attributes->isNotEmpty());
    $variantStatuses = ['active' => 'Active', 'inactive' => 'Inactive'];
    $productRoutePrefix = $productRoutePrefix ?? 'admin';
@endphp

    @error('variants')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('bulk')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('variant_ids')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('default_variant_id')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="alert alert-light border d-flex flex-wrap gap-2 align-items-center mb-3">
        <span>Basic Information <span class="text-success fw-semibold">✓</span></span>
        <span class="text-muted">/</span>
        <span>Attributes <span class="{{ $hasSelectedVariantAttributes ? 'text-success' : 'text-warning' }} fw-semibold">{{ $hasSelectedVariantAttributes ? '✓' : '!' }}</span></span>
        <span class="text-muted">/</span>
        <span>Variants & Inventory <span class="{{ $product->variants->isNotEmpty() ? 'text-success' : 'text-warning' }} fw-semibold">{{ $product->variants->isNotEmpty() ? '✓' : 'Action Required' }}</span></span>
    </div>

    @if(! $hasSelectedVariantAttributes)
        <div class="alert alert-info border">
            <div class="fw-semibold mb-1">This product currently has one sellable item.</div>
            <div>Add variant attributes in the Attributes tab if this product is available in multiple options.</div>
            <a href="{{ route($productRoutePrefix.'.products.edit', ['product' => $product, 'tab' => 'attributes']) }}" class="btn btn-sm btn-primary mt-2">
                <i class="ph-sliders-horizontal me-1"></i>
                Go to Attributes
            </a>
        </div>
    @elseif(($variantPreview['new_count'] ?? 0) > 0)
        <div class="border rounded bg-white p-3 mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-lg-between gap-3">
                <div>
                    <h6 class="fw-semibold mb-1">{{ $variantPreview['new_count'] }} variants can be generated.</h6>
                    <div class="text-muted">Generated variants start with stock quantity 0. Pricing is copied from the current default item for V1.</div>

                    @if($examples !== [])
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach($examples as $combination)
                                <li>{{ $combination['name'] }}</li>
                            @endforeach
                        </ul>
                        <div class="text-muted small mt-1">Showing {{ count($examples) }} of {{ $variantPreview['total'] }} combinations.</div>
                    @endif

                    @if(($variantPreview['stale_existing_count'] ?? 0) > 0)
                        <div class="text-warning mt-2">Some existing variants no longer match the currently selected attributes.</div>
                    @endif
                </div>

                @if(($variantPreview['total'] ?? 0) <= ($variantPreview['limit'] ?? 100))
                    <form method="POST" action="{{ route($productRoutePrefix.'.products.variants.generate', $product) }}" class="align-self-lg-start">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-git-branch me-2"></i>
                            Generate Variants
                        </button>
                    </form>
                @else
                    <div class="alert alert-danger mb-0 align-self-lg-start">
                        This selection would create {{ $variantPreview['total'] }} variants. The maximum allowed is {{ $variantPreview['limit'] }}.
                    </div>
                @endif
            </div>
        </div>
    @elseif($hasGeneratedVariants && ($variantPreview['stale_existing_count'] ?? 0) > 0)
        <div class="alert alert-warning">
            Some existing variants no longer match the currently selected attributes. They are kept unchanged for now.
        </div>
    @endif

    <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-3 align-items-xl-end mb-3">
        <form method="GET" action="{{ route($productRoutePrefix.'.products.edit', ['product' => $product, 'tab' => 'variants']) }}" class="row g-2 align-items-end flex-fill">
            <input type="hidden" name="tab" value="variants">
            <div class="col-md-3">
                <label class="form-label" for="variant_search">Search</label>
                <input id="variant_search" name="variant_search" type="text" value="{{ $variantFilters['search'] ?? '' }}" class="form-control" placeholder="Variant, SKU or barcode">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="variant_status">Status</label>
                <select id="variant_status" name="variant_status" class="form-select">
                    <option value="">All</option>
                    @foreach($variantStatuses as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}" @selected(($variantFilters['status'] ?? '') === $statusValue)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            @foreach($variantFilterOptions as $option)
                <div class="col-md-2">
                    <label class="form-label" for="variant_attribute_{{ $option['group_id'] }}">{{ $option['group_name'] }}</label>
                    <select id="variant_attribute_{{ $option['group_id'] }}" name="variant_attributes[{{ $option['group_id'] }}]" class="form-select">
                        <option value="">All</option>
                        @foreach($option['values'] as $value)
                            <option value="{{ $value['id'] }}" @selected((string) (($variantFilters['attributes'][$option['group_id']] ?? '') ?: '') === (string) $value['id'])>{{ $value['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-auto">
                <button type="submit" class="btn btn-light border">
                    <i class="ph-funnel me-2"></i>
                    Filter
                </button>
            </div>
        </form>

        @if($productRoutePrefix === 'merchant')
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('merchant.products.barcodes.generate', $product) }}">
                    @csrf
                    <button type="submit" class="btn btn-light border">
                        <i class="ph-barcode me-2"></i>
                        Generate Missing Barcodes
                    </button>
                </form>
                <a href="{{ route('merchant.barcodes.labels.index', ['q' => $product->product_name]) }}" class="btn btn-primary">
                    <i class="ph-printer me-2"></i>
                    Print Labels
                </a>
            </div>
        @endif
    </div>

    <div class="border rounded p-3 mb-3">
        <h6 class="fw-semibold mb-3">
            Bulk Update
            <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="Enter only the fields you want to change. Blank fields are ignored. Apply to Selected updates checked rows; Apply to All updates every variant for this product."></i>
        </h6>
        <form id="bulk-variant-form" method="POST" action="{{ route($productRoutePrefix.'.products.variants.bulk-update', $product) }}">
            @csrf
            @method('PUT')

            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label" for="bulk_mrp">MRP</label>
                    <input id="bulk_mrp" name="changes[mrp]" type="number" min="0" step="0.01" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk_selling_price">Selling Price</label>
                    <input id="bulk_selling_price" name="changes[selling_price]" type="number" min="0" step="0.01" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk_cost_price">
                        Cost Price (Optional)
                        <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="Used for profit and margin reports. Leave blank if not required."></i>
                    </label>
                    <input id="bulk_cost_price" name="changes[cost_price]" type="number" min="0" step="0.01" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk_stock_quantity">Stock Quantity</label>
                    <input id="bulk_stock_quantity" name="changes[stock_quantity]" type="number" min="0" step="1" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk_low_stock_threshold">Low Stock</label>
                    <input id="bulk_low_stock_threshold" name="changes[low_stock_threshold]" type="number" min="0" step="1" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="bulk_status">Status</label>
                    <select id="bulk_status" name="changes[status]" class="form-select">
                        <option value="">No change</option>
                        @foreach($variantStatuses as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="submit" name="scope" value="selected" class="btn btn-light border">
                    <i class="ph-check-square me-2"></i>
                    Apply to Selected
                </button>
                <button type="submit" name="scope" value="all" class="btn btn-primary">
                    <i class="ph-list-checks me-2"></i>
                    Apply to All
                </button>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route($productRoutePrefix.'.products.variants.update', $product) }}">
        @csrf
        @method('PUT')

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input js-select-all-variants" aria-label="Select all variants">
                        </th>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th class="text-end">MRP <span class="text-danger">*</span></th>
                        <th class="text-end">Selling Price <span class="text-danger">*</span></th>
                        <th class="text-end">
                            Cost Price <span class="text-muted fw-normal">(Optional)</span>
                            <i class="ph-info ms-1 text-muted" data-bs-popup="tooltip" title="Used for profit and margin reports. Leave blank if not required."></i>
                        </th>
                        <th class="text-end">Stock</th>
                        <th class="text-end">Low Stock</th>
                        <th>Default</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($variantRows as $variant)
                        <tr>
                            <td>
                                <input form="bulk-variant-form" type="checkbox" name="variant_ids[]" value="{{ $variant->id }}" class="form-check-input js-variant-checkbox" aria-label="Select {{ $variant->name }}">
                            </td>
                            <td class="fw-semibold">{{ $variant->name ?: $product->product_name }}</td>
                            <td>
                                <input name="variants[{{ $variant->id }}][sku]" value="{{ old("variants.{$variant->id}.sku", $variant->sku) }}" class="form-control form-control-sm">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][barcode]" value="{{ old("variants.{$variant->id}.barcode", $variant->barcode) }}" maxlength="100" class="form-control form-control-sm">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][mrp]" type="number" min="0" step="0.01" value="{{ old("variants.{$variant->id}.mrp", $variant->mrp) }}" class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][selling_price]" type="number" min="0" step="0.01" value="{{ old("variants.{$variant->id}.selling_price", $variant->selling_price) }}" class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][cost_price]" type="number" min="0" step="0.01" value="{{ old("variants.{$variant->id}.cost_price", $variant->cost_price) }}" class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][stock_quantity]" type="number" min="0" step="1" value="{{ old("variants.{$variant->id}.stock_quantity", $variant->stock_quantity) }}" class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input name="variants[{{ $variant->id }}][low_stock_threshold]" type="number" min="0" step="1" value="{{ old("variants.{$variant->id}.low_stock_threshold", $variant->low_stock_threshold) }}" class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input
                                    type="radio"
                                    name="default_variant_id"
                                    value="{{ $variant->getKey() }}"
                                    class="form-check-input"
                                    aria-label="Set {{ $variant->name ?: $product->product_name }} as default variant"
                                    @checked((int) old('default_variant_id', $product->variants->firstWhere('is_default', true)?->getKey()) === (int) $variant->getKey())
                                    @disabled($variant->status !== 'active')
                                >
                            </td>
                            <td>
                                <select name="variants[{{ $variant->id }}][status]" class="form-select form-select-sm">
                                    @foreach($variantStatuses as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" @selected(old("variants.{$variant->id}.status", $variant->status) === $statusValue)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No variants match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="ph-floppy-disk me-2"></i>
                Save Changes
            </button>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.querySelector('.js-select-all-variants');
            const checkboxes = Array.from(document.querySelectorAll('.js-variant-checkbox'));

            if (!selectAll) {
                return;
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            });
        });
    </script>
@endpush
