<?php

namespace App\Http\Requests\Merchant;

use App\Models\Promotion;
use App\Models\PromotionTarget;

class UpdatePromotionRequest extends StorePromotionRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $promotion = $this->route('promotion');

        if (! $promotion instanceof Promotion) {
            return;
        }

        $promotion->loadMissing('targets');

        $this->mergeMissingTargetIds($promotion, 'target_scope', 'product_ids', PromotionTarget::ROLE_ELIGIBLE, PromotionTarget::TYPE_PRODUCT);
        $this->mergeMissingTargetIds($promotion, 'target_scope', 'category_ids', PromotionTarget::ROLE_ELIGIBLE, PromotionTarget::TYPE_CATEGORY);
        $this->mergeMissingTargetIds($promotion, 'target_scope', 'brand_ids', PromotionTarget::ROLE_ELIGIBLE, PromotionTarget::TYPE_BRAND);
        $this->mergeMissingTargetIds($promotion, 'target_scope', 'collection_ids', PromotionTarget::ROLE_ELIGIBLE, PromotionTarget::TYPE_COLLECTION);
        $this->mergeMissingTargetIds($promotion, 'buy_target_scope', 'buy_product_ids', PromotionTarget::ROLE_BUY, PromotionTarget::TYPE_PRODUCT);
        $this->mergeMissingTargetIds($promotion, 'get_target_scope', 'get_product_ids', PromotionTarget::ROLE_GET, PromotionTarget::TYPE_PRODUCT);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['promotion_template_id']);

        return $rules;
    }

    private function mergeMissingTargetIds(Promotion $promotion, string $scopeField, string $idsField, string $role, string $type): void
    {
        if ($this->filled($idsField)) {
            return;
        }

        $expectedScope = match ($type) {
            PromotionTarget::TYPE_PRODUCT => 'products',
            PromotionTarget::TYPE_CATEGORY => 'categories',
            PromotionTarget::TYPE_BRAND => 'brands',
            PromotionTarget::TYPE_COLLECTION => 'collections',
            default => null,
        };

        if ($expectedScope === null || $this->input($scopeField) !== $expectedScope) {
            return;
        }

        $ids = $promotion->targets
            ->where('target_role', $role)
            ->where('target_type', $type)
            ->pluck('target_id')
            ->filter()
            ->values()
            ->all();

        if ($ids !== []) {
            $this->merge([$idsField => $ids]);
        }
    }
}
