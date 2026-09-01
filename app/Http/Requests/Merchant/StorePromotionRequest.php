<?php

namespace App\Http\Requests\Merchant;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tiers = collect($this->input('tier_config', []))
            ->filter(fn ($tier): bool => is_array($tier)
                && (($tier['min_quantity'] ?? '') !== '' || ($tier['unit_price'] ?? '') !== ''))
            ->values()
            ->all();

        $this->merge([
            'tier_config' => $tiers === [] ? null : $tiers,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'promotion_template_id' => ['required', 'integer', Rule::exists('promotion_templates', 'id')->where('status', 'active')],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Promotion::STATUS_DRAFT, Promotion::STATUS_ACTIVE, Promotion::STATUS_INACTIVE])],
            'activation_type' => ['required', Rule::in([Promotion::ACTIVATION_AUTOMATIC, Promotion::ACTIVATION_COUPON])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_combinable' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'total_usage_limit' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'per_customer_usage_limit' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'new_customer_only' => ['nullable', 'boolean'],
            'refund_policy_mode' => ['required', Rule::in([Promotion::POLICY_INHERIT, Promotion::POLICY_ALLOWED, Promotion::POLICY_NOT_ALLOWED])],
            'refund_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'exchange_policy_mode' => ['required', Rule::in([Promotion::POLICY_INHERIT, Promotion::POLICY_ALLOWED, Promotion::POLICY_NOT_ALLOWED])],
            'exchange_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'target_scope' => ['nullable', Rule::in(['all', 'products', 'categories', 'brands', 'collections'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer'],
            'buy_target_scope' => ['nullable', Rule::in(['all', 'products', 'categories', 'brands', 'collections'])],
            'buy_product_ids' => ['nullable', 'array'],
            'buy_product_ids.*' => ['integer'],
            'buy_category_ids' => ['nullable', 'array'],
            'buy_category_ids.*' => ['integer'],
            'buy_brand_ids' => ['nullable', 'array'],
            'buy_brand_ids.*' => ['integer'],
            'buy_collection_ids' => ['nullable', 'array'],
            'buy_collection_ids.*' => ['integer'],
            'get_target_scope' => ['nullable', Rule::in(['all', 'products', 'categories', 'brands', 'collections'])],
            'get_product_ids' => ['nullable', 'array'],
            'get_product_ids.*' => ['integer'],
            'get_category_ids' => ['nullable', 'array'],
            'get_category_ids.*' => ['integer'],
            'get_brand_ids' => ['nullable', 'array'],
            'get_brand_ids.*' => ['integer'],
            'get_collection_ids' => ['nullable', 'array'],
            'get_collection_ids.*' => ['integer'],
            'gift_product_id' => ['nullable', 'integer'],
            'gift_variant_id' => ['nullable', 'integer'],
            'gift_product_ids' => ['nullable', 'array'],
            'gift_product_ids.*' => ['integer'],

            'value_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'value_percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'buy_quantity' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'get_quantity' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'bundle_quantity' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'bundle_price' => ['nullable', 'numeric', 'gt:0'],
            'minimum_quantity' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'minimum_eligible_subtotal' => ['nullable', 'numeric', 'gt:0'],
            'tier_config' => ['nullable', 'array'],
            'tier_config.*.min_quantity' => ['required_with:tier_config', 'integer', 'min:1', 'max:999999'],
            'tier_config.*.unit_price' => ['required_with:tier_config', 'numeric', 'gt:0'],

            'coupon_code' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'coupon_status' => ['nullable', Rule::in(['active', 'inactive'])],
            'coupon_starts_at' => ['nullable', 'date'],
            'coupon_ends_at' => ['nullable', 'date', 'after:coupon_starts_at'],
            'coupon_usage_limit' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'coupon_per_customer_usage_limit' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('refund_policy_mode') === Promotion::POLICY_ALLOWED && $this->input('refund_window_days') === null) {
                $validator->errors()->add('refund_window_days', 'Refund days are required when refunds are allowed.');
            }

            if ($this->input('exchange_policy_mode') === Promotion::POLICY_ALLOWED && $this->input('exchange_window_days') === null) {
                $validator->errors()->add('exchange_window_days', 'Exchange days are required when exchanges are allowed.');
            }

            if ($this->input('activation_type') === Promotion::ACTIVATION_COUPON && trim((string) $this->input('coupon_code')) === '') {
                $validator->errors()->add('coupon_code', 'Coupon code is required for coupon offers.');
            }
        });
    }
}
