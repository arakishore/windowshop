<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Services\Promotion\Engine\Data\PromotionLineInput;

class PromotionTargetMatcher
{
    public function matches(Promotion $promotion, PromotionLineInput $line, string $role = PromotionTarget::ROLE_ELIGIBLE): bool
    {
        if ((int) $promotion->shop_id !== $line->shopId) {
            return false;
        }

        $targets = $promotion->targets
            ->where('target_role', $role)
            ->values();

        if ($targets->isEmpty()) {
            return false;
        }

        return $targets->contains(function (PromotionTarget $target) use ($line): bool {
            return match ($target->target_type) {
                PromotionTarget::TYPE_ALL => true,
                PromotionTarget::TYPE_PRODUCT => (int) $target->target_id === $line->productId,
                PromotionTarget::TYPE_VARIANT => (int) $target->target_id === $line->variantId,
                PromotionTarget::TYPE_CATEGORY => in_array((int) $target->target_id, $line->categoryIds, true),
                PromotionTarget::TYPE_BRAND => $line->brandId !== null && (int) $target->target_id === $line->brandId,
                PromotionTarget::TYPE_COLLECTION => in_array((int) $target->target_id, $line->collectionIds, true),
                default => false,
            };
        });
    }
}
