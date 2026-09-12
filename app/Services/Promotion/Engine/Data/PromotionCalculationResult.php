<?php

namespace App\Services\Promotion\Engine\Data;

class PromotionCalculationResult
{
    /**
     * @param array<int, PromotionLineAdjustment> $lineAdjustments
     * @param array<int, GeneratedPromotionGift> $generatedGifts
     */
    public function __construct(
        public readonly int $shopId,
        public readonly array $lineAdjustments,
        public readonly array $generatedGifts = [],
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
        )) + array_sum(array_map(
            fn (GeneratedPromotionGift $gift): int => $gift->baseLineSubtotalCents,
            $this->generatedGifts,
        ));
    }

    public function promotionDiscountCents(): int
    {
        return array_sum(array_map(
            fn (PromotionLineAdjustment $line): int => $line->promotionDiscountCents,
            $this->lineAdjustments,
        )) + array_sum(array_map(
            fn (GeneratedPromotionGift $gift): int => $gift->promotionDiscountCents,
            $this->generatedGifts,
        ));
    }

    public function subtotalAfterPromotionsCents(): int
    {
        return array_sum(array_map(
            fn (PromotionLineAdjustment $line): int => $line->finalLineSubtotalCents,
            $this->lineAdjustments,
        )) + array_sum(array_map(
            fn (GeneratedPromotionGift $gift): int => $gift->finalLineSubtotalCents,
            $this->generatedGifts,
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

        foreach ($this->generatedGifts as $gift) {
            $metadata = $gift->promotion->toMetadata();
            $existingDiscount = (float) ($applied[$metadata['id']]['discount_amount'] ?? 0);
            $currentDiscount = (float) $metadata['discount_amount'];

            if (! isset($applied[$metadata['id']]) || $currentDiscount > $existingDiscount) {
                $applied[$metadata['id']] = $metadata;
            }
        }

        return array_values($applied);
    }
}
