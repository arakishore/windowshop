<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Services\Promotion\Engine\Data\AppliedPromotion;

class PromotionCombinationResolver
{
    /**
     * @param array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}> $candidates
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

        return $this->appliedPromotionFromCandidate($winner);
    }

    /**
     * @param array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}> $candidates
     */
    public function participationPromotion(array $candidates): ?AppliedPromotion
    {
        $winner = collect($candidates)
            ->filter(fn (array $candidate): bool => (int) $candidate['discount_cents'] === 0)
            ->sort(function (array $left, array $right): int {
                $discount = (int) ($right['details']['group_discount_cents'] ?? 0) <=> (int) ($left['details']['group_discount_cents'] ?? 0);
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

        return $this->appliedPromotionFromCandidate($winner);
    }

    /**
     * @param array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>} $winner
     */
    private function appliedPromotionFromCandidate(array $winner): AppliedPromotion
    {
        $promotion = $winner['promotion'];

        return new AppliedPromotion(
            promotionId: (int) $promotion->getKey(),
            promotionName: (string) $promotion->name,
            promotionSlug: $promotion->slug,
            templateCode: (string) $promotion->template?->code,
            rewardType: (string) $promotion->rewards->first()?->reward_type,
            priority: (int) $promotion->priority,
            discountCents: (int) $winner['discount_cents'],
            details: $winner['details'] ?? [],
        );
    }
}
