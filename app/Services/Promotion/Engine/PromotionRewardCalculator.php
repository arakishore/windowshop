<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Services\Promotion\Engine\Data\PromotionLineInput;

class PromotionRewardCalculator
{
    public function discountCents(Promotion $promotion, PromotionLineInput $line): int
    {
        $reward = $promotion->rewards->first();
        if (! $reward instanceof PromotionReward) {
            return 0;
        }

        $baseLineSubtotalCents = $this->lineSubtotalCents($line);
        $quantityUnits = $this->quantityToUnits($line->quantity);
        $baseUnitCents = $this->moneyToCents($line->baseUnitPrice);

        $discountCents = match ($reward->reward_type) {
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT => $this->percentageDiscountCents($baseLineSubtotalCents, $reward),
            PromotionReward::TYPE_FIXED_DISCOUNT => intdiv(($this->moneyToCents((string) $reward->value_amount) * $quantityUnits) + 500, 1000),
            PromotionReward::TYPE_FIXED_PRICE => $this->fixedPriceDiscountCents($baseUnitCents, $quantityUnits, $reward),
            default => 0,
        };

        return max(0, min($discountCents, $baseLineSubtotalCents));
    }

    private function percentageDiscountCents(int $baseLineSubtotalCents, PromotionReward $reward): int
    {
        $percentUnits = (int) round(((float) $reward->value_percent) * 100);
        $discountCents = intdiv(($baseLineSubtotalCents * $percentUnits) + 5000, 10000);

        if ($reward->max_discount_amount !== null && (float) $reward->max_discount_amount > 0) {
            $discountCents = min($discountCents, $this->moneyToCents((string) $reward->max_discount_amount));
        }

        return $discountCents;
    }

    private function fixedPriceDiscountCents(int $baseUnitCents, int $quantityUnits, PromotionReward $reward): int
    {
        $fixedUnitCents = $this->moneyToCents((string) $reward->value_amount);
        $unitDiscountCents = max(0, $baseUnitCents - $fixedUnitCents);

        return intdiv(($unitDiscountCents * $quantityUnits) + 500, 1000);
    }

    private function lineSubtotalCents(PromotionLineInput $line): int
    {
        return intdiv(($this->quantityToUnits($line->quantity) * $this->moneyToCents($line->baseUnitPrice)) + 500, 1000);
    }

    private function quantityToUnits(string $quantity): int
    {
        return (int) round(((float) $quantity) * 1000);
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
