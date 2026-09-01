<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Services\Promotion\Engine\Data\AppliedPromotion;

class PromotionCombinationResolver
{
    /**
     * @param array<int, array{promotion: Promotion, discount_cents: int}> $candidates
     */
    public function winningPromotion(array $candidates): ?AppliedPromotion
    {
        $winner = collect($candidates)
            ->filter(fn (array $candidate): bool => (int) $candidate['discount_cents'] > 0)
            ->sort(function (array $left, array $right): int {
                $discount = (int) $right['discount_cents'] <=> (int) $left['discount_cents'];
                if ($discount !== 0) {
                    return $discount;
                }

                $priority = (int) $right['promotion']->priority <=> (int) $left['promotion']->priority;
                if ($priority !== 0) {
                    return $priority;
                }

                return (int) $left['promotion']->getKey() <=> (int) $right['promotion']->getKey();
            })
            ->first();

        if (! is_array($winner)) {
            return null;
        }

        $promotion = $winner['promotion'];

        return new AppliedPromotion(
            promotionId: (int) $promotion->getKey(),
            promotionName: (string) $promotion->name,
            promotionSlug: $promotion->slug,
            templateCode: (string) $promotion->template?->code,
            rewardType: (string) $promotion->rewards->first()?->reward_type,
            priority: (int) $promotion->priority,
            discountCents: (int) $winner['discount_cents'],
        );
    }
}
