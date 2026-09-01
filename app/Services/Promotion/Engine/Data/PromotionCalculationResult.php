<?php

namespace App\Services\Promotion\Engine\Data;

class PromotionCalculationResult
{
    /**
     * @param array<int, PromotionLineAdjustment> $lineAdjustments
     */
    public function __construct(
        public readonly int $shopId,
        public readonly array $lineAdjustments,
    ) {
    }

    public function line(int $variantId): ?PromotionLineAdjustment
    {
        return $this->lineAdjustments[$variantId] ?? null;
    }

    public function baseSubtotalCents(): int
    {
        return array_sum(array_map(
            fn (PromotionLineAdjustment $line): int => $line->baseLineSubtotalCents,
            $this->lineAdjustments,
        ));
    }

    public function promotionDiscountCents(): int
    {
        return array_sum(array_map(
            fn (PromotionLineAdjustment $line): int => $line->promotionDiscountCents,
            $this->lineAdjustments,
        ));
    }

    public function subtotalAfterPromotionsCents(): int
    {
        return array_sum(array_map(
            fn (PromotionLineAdjustment $line): int => $line->finalLineSubtotalCents,
            $this->lineAdjustments,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function appliedPromotions(): array
    {
        $applied = [];

        foreach ($this->lineAdjustments as $adjustment) {
            if (! $adjustment->hasPromotionParticipation()) {
                continue;
            }

            $metadata = $adjustment->winningPromotion?->toMetadata();
            if ($metadata !== null) {
                $existingDiscount = (float) ($applied[$metadata['id']]['discount_amount'] ?? 0);
                $currentDiscount = (float) $metadata['discount_amount'];

                if (! isset($applied[$metadata['id']]) || $currentDiscount > $existingDiscount) {
                    $applied[$metadata['id']] = $metadata;
                }
            }
        }

        return array_values($applied);
    }
}
