{{-- Purpose: Shared promotion form for merchant offer create/edit. --}}
@php
    $promotion->loadMissing(['rewards', 'conditions', 'targets', 'coupons']);
    $reward = $promotion->rewards->first();
    $coupon = $promotion->coupons->first();
    $targetsByRole = $promotion->targets->groupBy('target_role');
    $targetIds = fn (string $role, string $type) => $targetsByRole->get($role, collect())->where('target_type', $type)->pluck('target_id')->all();
    $scopeFor = function (string $role, string $default = 'all') use ($targetsByRole) {
        $targets = $targetsByRole->get($role, collect());
        if ($targets->isEmpty()) {
            return $default;
        }
        $type = $targets->first()->target_type;
        return match ($type) {
            'product' => 'products',
            'category' => 'categories',
            'brand' => 'brands',
            'collection' => 'collections',
            default => 'all',
        };
    };
    $isEdit = $promotion->exists;
    $currentTemplate = $isEdit
        ? $promotion->template
        : $templates->firstWhere('id', (int) old('promotion_template_id', $promotion->promotion_template_id ?: $templates->first()?->id));
    $templateId = $isEdit ? $promotion->promotion_template_id : old('promotion_template_id', $promotion->promotion_template_id ?: $templates->first()?->id);
@endphp

<div class="row g-3 js-promotion-form" data-current-template-code="{{ $isEdit ? $currentTemplate?->code : '' }}">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ $isEdit ? 'Offer Type' : 'Choose Offer Type' }}</h5>
            </div>
            <div class="card-body">
                @if($isEdit)
                    <div class="border rounded p-3 bg-light bg-opacity-50">
                        <div class="fw-semibold">{{ $currentTemplate?->name ?? 'Offer Type' }}</div>
                        @if($currentTemplate?->description)
                            <div class="text-muted small mt-1">{{ $currentTemplate->description }}</div>
                        @endif
                        @if($currentTemplate?->example)
                            <div class="small mt-2"><span class="text-muted">Example:</span> {{ $currentTemplate->example }}</div>
                        @endif
                        <div class="form-text mt-2">To use a different offer type, create a new offer.</div>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($templates as $template)
                            <div class="col-md-6">
                                <label class="border rounded p-3 d-block h-100 js-template-card">
                                    <div class="d-flex gap-2 align-items-start">
                                        <input type="radio" name="promotion_template_id" value="{{ $template->id }}" class="form-check-input mt-1 js-template-input" data-template-code="{{ $template->code }}" @checked((string) $templateId === (string) $template->id) required>
                                        <span>
                                            <span class="fw-semibold d-block">{{ $template->name }}</span>
                                            <span class="text-muted small d-block">{{ $template->description }}</span>
                                            @if($template->example)
                                                <span class="badge bg-primary bg-opacity-10 text-primary mt-2 text-wrap">{{ $template->example }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('promotion_template_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Offer Details</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $promotion->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach($statuses as $value => $status)
                            <option value="{{ $value }}" @selected(old('status', $promotion->status) === $value)>{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="3" class="form-control">{{ old('description', $promotion->description) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="starts_at">Starts At</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $promotion->starts_at?->format('Y-m-d\TH:i')) }}" class="form-control">
                    <div class="form-text">Leave blank to start immediately.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Ends At</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $promotion->ends_at?->format('Y-m-d\TH:i')) }}" class="form-control @error('ends_at') is-invalid @enderror">
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Leave blank for no expiry.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Applies To</h5></div>
            <div class="card-body row g-3">
                <div class="js-target-block js-eligible-targets">
                    @include('merchant.promotions.partials.targets', ['role' => 'eligible', 'prefix' => '', 'scope' => old('target_scope', $scopeFor('eligible'))])
                </div>
                <div class="js-target-block js-buy-targets">
                    @include('merchant.promotions.partials.targets', ['role' => 'buy', 'prefix' => 'buy_', 'scope' => old('buy_target_scope', $scopeFor('buy'))])
                </div>
                <div class="js-target-block js-get-targets">
                    @include('merchant.promotions.partials.targets', ['role' => 'get', 'prefix' => 'get_', 'scope' => old('get_target_scope', $scopeFor('get'))])
                </div>
                <div class="col-12 js-field js-field-gift-products">
                    <h6 class="fw-semibold mb-2">Free Gift</h6>
                    <label class="form-label" for="gift_product_ids">Gift Product</label>
                    <select id="gift_product_ids" name="gift_product_ids[]" class="form-select" multiple size="4">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(in_array($product->id, old('gift_product_ids', $targetIds('gift', 'product')), true))>{{ $product->product_name }}</option>
                        @endforeach
                    </select>
                    @error('gift_product_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Offer Rules</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-4 js-field js-field-value-percent">
                    <label class="form-label" for="value_percent">Discount Percentage</label>
                    <input id="value_percent" name="value_percent" type="number" step="0.01" min="0" value="{{ old('value_percent', $reward?->value_percent) }}" class="form-control @error('value_percent') is-invalid @enderror">
                    @error('value_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-max-discount">
                    <label class="form-label" for="max_discount_amount">Maximum Discount</label>
                    <input id="max_discount_amount" name="max_discount_amount" type="number" step="0.01" min="0" value="{{ old('max_discount_amount', $reward?->max_discount_amount) }}" class="form-control">
                    <div class="form-text">Optional.</div>
                </div>
                <div class="col-md-4 js-field js-field-value-amount">
                    <label class="form-label js-value-amount-label" for="value_amount">Discount Amount</label>
                    <input id="value_amount" name="value_amount" type="number" step="0.01" min="0" value="{{ old('value_amount', $reward?->value_amount) }}" class="form-control @error('value_amount') is-invalid @enderror">
                    @error('value_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-value-type">
                    <label class="form-label" for="value_type">Discount Type</label>
                    <select id="value_type" name="value_type" class="form-select">
                        <option value="">Choose</option>
                        <option value="percent" @selected(old('value_type', $reward?->value_type) === 'percent')>Percentage</option>
                        <option value="amount" @selected(old('value_type', $reward?->value_type) === 'amount')>Amount</option>
                    </select>
                </div>
                <div class="col-md-4 js-field js-field-buy-quantity">
                    <label class="form-label" for="buy_quantity">Buy Quantity</label>
                    <input id="buy_quantity" name="buy_quantity" type="number" min="1" value="{{ old('buy_quantity', $reward?->buy_quantity) }}" class="form-control @error('buy_quantity') is-invalid @enderror">
                    @error('buy_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-get-quantity">
                    <label class="form-label" for="get_quantity">Get Quantity</label>
                    <input id="get_quantity" name="get_quantity" type="number" min="1" value="{{ old('get_quantity', $reward?->get_quantity) }}" class="form-control @error('get_quantity') is-invalid @enderror">
                    @error('get_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-bundle-quantity">
                    <label class="form-label" for="bundle_quantity">Bundle Quantity</label>
                    <input id="bundle_quantity" name="bundle_quantity" type="number" min="1" value="{{ old('bundle_quantity', $reward?->bundle_quantity) }}" class="form-control @error('bundle_quantity') is-invalid @enderror">
                    @error('bundle_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-bundle-price">
                    <label class="form-label" for="bundle_price">Bundle Price</label>
                    <input id="bundle_price" name="bundle_price" type="number" step="0.01" min="0" value="{{ old('bundle_price', $reward?->bundle_price) }}" class="form-control @error('bundle_price') is-invalid @enderror">
                    @error('bundle_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 js-field js-field-minimum-quantity">
                    <label class="form-label" for="minimum_quantity">Minimum Quantity</label>
                    <input id="minimum_quantity" name="minimum_quantity" type="number" min="1" value="{{ old('minimum_quantity', $promotion->conditions->where('condition_type', 'minimum_quantity')->first()?->value_numeric) }}" class="form-control">
                </div>
                <div class="col-md-4 js-field js-field-minimum-subtotal">
                    <label class="form-label" for="minimum_eligible_subtotal">Minimum Eligible Subtotal</label>
                    <input id="minimum_eligible_subtotal" name="minimum_eligible_subtotal" type="number" step="0.01" min="0" value="{{ old('minimum_eligible_subtotal', $promotion->conditions->where('condition_type', 'minimum_eligible_subtotal')->first()?->value_numeric) }}" class="form-control">
                </div>
                <div class="col-12 js-field js-field-tier-config">
                    <label class="form-label">Tier Pricing</label>
                    @for($i = 0; $i < 4; $i++)
                        <div class="input-group mb-2 js-tier-row">
                            <span class="input-group-text">Quantity</span>
                            <input name="tier_config[{{ $i }}][min_quantity]" type="number" min="1" value="{{ old('tier_config.'.$i.'.min_quantity', $reward?->tier_config[$i]['min_quantity'] ?? '') }}" class="form-control">
                            <span class="input-group-text">Price</span>
                            <input name="tier_config[{{ $i }}][unit_price]" type="number" step="0.01" min="0" value="{{ old('tier_config.'.$i.'.unit_price', $reward?->tier_config[$i]['unit_price'] ?? '') }}" class="form-control">
                        </div>
                    @endfor
                    @error('tier_config')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="alert alert-light border mb-0">
                        <div class="fw-semibold mb-1">Offer Preview</div>
                        <div class="small js-offer-preview-text">Choose an offer type and enter rules to preview this offer.</div>
                        <div class="small text-muted mt-1">This preview is informational only and is not used for promotion calculation.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Activation & Usage</h5></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="activation_type">Activation Type</label>
                    <select id="activation_type" name="activation_type" class="form-select">
                        @foreach($activationTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('activation_type', $promotion->activation_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 js-coupon-field">
                    <label class="form-label" for="coupon_code">Coupon Code</label>
                    <input id="coupon_code" name="coupon_code" value="{{ old('coupon_code', $coupon?->code) }}" class="form-control @error('coupon_code') is-invalid @enderror">
                    @error('coupon_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <input type="hidden" name="coupon_status" value="active">
                <div class="col-12">
                    <label class="form-label" for="total_usage_limit">Total Usage Limit</label>
                    <input id="total_usage_limit" name="total_usage_limit" type="number" min="1" value="{{ old('total_usage_limit', $promotion->total_usage_limit) }}" class="form-control">
                    <div class="form-text">Leave blank for unlimited.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="per_customer_usage_limit">Per-customer Usage Limit</label>
                    <input id="per_customer_usage_limit" name="per_customer_usage_limit" type="number" min="1" value="{{ old('per_customer_usage_limit', $promotion->per_customer_usage_limit) }}" class="form-control">
                    <div class="form-text">Leave blank for unlimited.</div>
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="new_customer_only" value="1" class="form-check-input" @checked(old('new_customer_only', $promotion->new_customer_only))>
                        <span class="form-check-label">New customer only</span>
                    </label>
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="is_combinable" value="1" class="form-check-input" @checked(old('is_combinable', $promotion->is_combinable))>
                        <span class="form-check-label">Can this offer be combined with other offers?</span>
                    </label>
                </div>
                <input type="hidden" name="priority" value="{{ old('priority', $promotion->priority ?? 0) }}">
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Refund / Exchange Policy</h5></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="refund_policy_mode">Refund</label>
                    <select id="refund_policy_mode" name="refund_policy_mode" class="form-select">
                        @foreach($policyModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('refund_policy_mode', $promotion->refund_policy_mode) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 js-policy-window js-refund-window-wrapper">
                    <label class="form-label" for="refund_window_days">Refund Window</label>
                    <div class="input-group">
                        <input id="refund_window_days" name="refund_window_days" type="number" min="0" value="{{ old('refund_window_days', $promotion->refund_window_days) }}" class="form-control @error('refund_window_days') is-invalid @enderror">
                        <span class="input-group-text">days</span>
                    </div>
                    @error('refund_window_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="exchange_policy_mode">Exchange</label>
                    <select id="exchange_policy_mode" name="exchange_policy_mode" class="form-select">
                        @foreach($policyModes as $value => $label)
                            <option value="{{ $value }}" @selected(old('exchange_policy_mode', $promotion->exchange_policy_mode) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 js-policy-window js-exchange-window-wrapper">
                    <label class="form-label" for="exchange_window_days">Exchange Window</label>
                    <div class="input-group">
                        <input id="exchange_window_days" name="exchange_window_days" type="number" min="0" value="{{ old('exchange_window_days', $promotion->exchange_window_days) }}" class="form-control @error('exchange_window_days') is-invalid @enderror">
                        <span class="input-group-text">days</span>
                    </div>
                    @error('exchange_window_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('merchant.promotions.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ph-floppy-disk me-2"></i>
                Save Offer
            </button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.js-promotion-form');
            const templateInputs = Array.from(document.querySelectorAll('.js-template-input'));
            const templateCards = Array.from(document.querySelectorAll('.js-template-card'));
            const activationType = document.querySelector('#activation_type');
            const couponField = document.querySelector('.js-coupon-field');
            const refundMode = document.querySelector('#refund_policy_mode');
            const refundWindow = document.querySelector('.js-refund-window-wrapper');
            const exchangeMode = document.querySelector('#exchange_policy_mode');
            const exchangeWindow = document.querySelector('.js-exchange-window-wrapper');
            const valueType = document.querySelector('#value_type');
            const valueAmountLabel = document.querySelector('.js-value-amount-label');
            const previewText = document.querySelector('.js-offer-preview-text');
            const fieldMap = {
                valuePercent: document.querySelector('.js-field-value-percent'),
                maxDiscount: document.querySelector('.js-field-max-discount'),
                valueAmount: document.querySelector('.js-field-value-amount'),
                valueType: document.querySelector('.js-field-value-type'),
                buyQuantity: document.querySelector('.js-field-buy-quantity'),
                getQuantity: document.querySelector('.js-field-get-quantity'),
                bundleQuantity: document.querySelector('.js-field-bundle-quantity'),
                bundlePrice: document.querySelector('.js-field-bundle-price'),
                minimumQuantity: document.querySelector('.js-field-minimum-quantity'),
                minimumSubtotal: document.querySelector('.js-field-minimum-subtotal'),
                tierConfig: document.querySelector('.js-field-tier-config'),
                giftProducts: document.querySelector('.js-field-gift-products'),
            };
            const eligibleTargets = document.querySelector('.js-eligible-targets');
            const buyTargets = document.querySelector('.js-buy-targets');
            const getTargets = document.querySelector('.js-get-targets');

            function selectedCode() {
                return templateInputs.find((input) => input.checked)?.dataset.templateCode || form?.dataset.currentTemplateCode || '';
            }

            function setVisible(element, visible) {
                if (!element) {
                    return;
                }

                element.classList.toggle('d-none', !visible);
                element.querySelectorAll('input, select, textarea').forEach((input) => {
                    input.disabled = !visible;
                });
            }

            function updateTemplate() {
                const code = selectedCode();
                const quantityType = valueType?.value || '';

                Object.values(fieldMap).forEach((field) => setVisible(field, false));
                setVisible(eligibleTargets, !['buy_x_get_y_free', 'buy_x_get_y_discount'].includes(code));
                setVisible(buyTargets, ['buy_x_get_y_free', 'buy_x_get_y_discount'].includes(code));
                setVisible(getTargets, ['buy_x_get_y_free', 'buy_x_get_y_discount'].includes(code));

                setVisible(fieldMap.valuePercent, ['percentage_discount', 'buy_x_get_y_discount'].includes(code) || (code === 'quantity_discount' && quantityType === 'percent'));
                setVisible(fieldMap.maxDiscount, code === 'percentage_discount');
                setVisible(fieldMap.valueAmount, ['fixed_discount', 'fixed_price'].includes(code) || (code === 'quantity_discount' && quantityType === 'amount'));
                setVisible(fieldMap.valueType, code === 'quantity_discount');
                setVisible(fieldMap.buyQuantity, ['buy_x_get_y_free', 'buy_x_get_y_discount'].includes(code));
                setVisible(fieldMap.getQuantity, ['buy_x_get_y_free', 'buy_x_get_y_discount'].includes(code));
                setVisible(fieldMap.bundleQuantity, code === 'fixed_bundle_price');
                setVisible(fieldMap.bundlePrice, code === 'fixed_bundle_price');
                setVisible(fieldMap.minimumQuantity, code === 'quantity_discount');
                setVisible(fieldMap.minimumSubtotal, code === 'free_gift');
                setVisible(fieldMap.tierConfig, code === 'tier_pricing');
                setVisible(fieldMap.giftProducts, code === 'free_gift');

                if (valueAmountLabel) {
                    valueAmountLabel.textContent = code === 'fixed_price' ? 'Promotional Fixed Price' : 'Discount Amount';
                }

                templateCards.forEach((card) => {
                    const selected = card.querySelector('.js-template-input')?.checked;
                    card.classList.toggle('border-primary', selected);
                    card.classList.toggle('bg-primary', selected);
                    card.classList.toggle('bg-opacity-10', selected);
                });

                updateTargets();
                updatePreview();
            }

            function updateTargets() {
                document.querySelectorAll('.js-target-row').forEach((row) => {
                    const scope = row.querySelector('.js-target-scope')?.value || 'all';
                    row.querySelectorAll('.js-target-selector').forEach((selector) => {
                        setVisible(selector, selector.dataset.targetSelector === scope);
                    });
                });
            }

            function updateActivation() {
                const coupon = activationType?.value === 'coupon';
                setVisible(couponField, coupon);
                if (!coupon) {
                    const couponInput = couponField?.querySelector('input');
                    if (couponInput) {
                        couponInput.value = '';
                    }
                }
            }

            function updatePolicies() {
                setVisible(refundWindow, refundMode?.value === 'allowed');
                setVisible(exchangeWindow, exchangeMode?.value === 'allowed');

                if (refundMode?.value !== 'allowed') {
                    const input = refundWindow?.querySelector('input');
                    if (input) {
                        input.value = '';
                    }
                }

                if (exchangeMode?.value !== 'allowed') {
                    const input = exchangeWindow?.querySelector('input');
                    if (input) {
                        input.value = '';
                    }
                }
            }

            function money(value) {
                const amount = Number(value || 0);
                return 'Rs. ' + amount.toLocaleString('en-IN', { maximumFractionDigits: 2 });
            }

            function tierSummary() {
                const tiers = Array.from(document.querySelectorAll('.js-tier-row')).map((row) => {
                    const qty = row.querySelector('input[name$="[min_quantity]"]')?.value;
                    const price = row.querySelector('input[name$="[unit_price]"]')?.value;
                    return qty && price ? `${qty}+ items: ${money(price)} each` : null;
                }).filter(Boolean);

                return tiers.length ? tiers.join(' | ') : 'Enter quantity and price tiers to preview this offer.';
            }

            function updatePreview() {
                if (!previewText) {
                    return;
                }

                const code = selectedCode();
                const percent = document.querySelector('#value_percent')?.value;
                const maxDiscount = document.querySelector('#max_discount_amount')?.value;
                const amount = document.querySelector('#value_amount')?.value;
                const buy = document.querySelector('#buy_quantity')?.value;
                const get = document.querySelector('#get_quantity')?.value;
                const bundleQty = document.querySelector('#bundle_quantity')?.value;
                const bundlePrice = document.querySelector('#bundle_price')?.value;
                const minQty = document.querySelector('#minimum_quantity')?.value;
                const minSubtotal = document.querySelector('#minimum_eligible_subtotal')?.value;

                const previews = {
                    percentage_discount: percent
                        ? `If an eligible item costs Rs. 2,000: ${percent}% discount is ${money(2000 * Number(percent) / 100)}${maxDiscount ? `, capped at ${money(maxDiscount)}.` : '.'}`
                        : 'Enter a discount percentage to preview this offer.',
                    fixed_discount: amount
                        ? `Eligible amount Rs. 2,000, discount ${money(amount)}, amount after offer ${money(Math.max(0, 2000 - Number(amount)))}.`
                        : 'Enter a discount amount to preview this offer.',
                    fixed_price: amount
                        ? `Eligible products will use promotional fixed price ${money(amount)}.`
                        : 'Enter a promotional fixed price to preview this offer.',
                    fixed_bundle_price: bundleQty && bundlePrice
                        ? `Customer can choose any ${bundleQty} eligible item(s) for ${money(bundlePrice)}.`
                        : 'Enter bundle quantity and bundle price to preview this offer.',
                    buy_x_get_y_free: buy && get
                        ? `Buy ${buy} eligible item(s) and get ${get} eligible item(s) free.`
                        : 'Enter buy and get quantities to preview this offer.',
                    buy_x_get_y_discount: buy && get && percent
                        ? `Buy ${buy} eligible item(s) and get ${get} eligible item(s) at ${percent}% OFF.`
                        : 'Enter buy quantity, get quantity, and discount percentage to preview this offer.',
                    quantity_discount: minQty && (percent || amount)
                        ? `Buy ${minQty} or more eligible item(s) and receive ${valueType?.value === 'amount' ? money(amount) : `${percent}%`} OFF.`
                        : 'Enter minimum quantity and discount value to preview this offer.',
                    tier_pricing: tierSummary(),
                    free_gift: minSubtotal
                        ? `Spend ${money(minSubtotal)} on eligible products and receive the selected gift product free.`
                        : 'Enter minimum eligible subtotal to preview this offer.',
                };

                previewText.textContent = previews[code] || 'Choose an offer type and enter rules to preview this offer.';
            }

            templateInputs.forEach((input) => input.addEventListener('change', updateTemplate));
            document.querySelectorAll('.js-target-scope').forEach((input) => input.addEventListener('change', updateTargets));
            activationType?.addEventListener('change', updateActivation);
            refundMode?.addEventListener('change', updatePolicies);
            exchangeMode?.addEventListener('change', updatePolicies);
            valueType?.addEventListener('change', updateTemplate);
            document.addEventListener('input', updatePreview);

            updateTemplate();
            updateActivation();
            updatePolicies();
        });
    </script>
@endpush
