<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Models\PromotionCondition;
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
            PromotionReward::TYPE_QUANTITY_DISCOUNT => $this->quantityDiscountCents($promotion, $baseUnitCents, $quantityUnits, $reward),
            PromotionReward::TYPE_TIER_PRICING => $this->tierPricingDiscountCents($baseUnitCents, $quantityUnits, $reward),
            default => 0,
        };

        return max(0, min($discountCents, $baseLineSubtotalCents));
    }

    /**
     * @param array<int, PromotionLineInput> $lines
     * @return array<int, array{discount_cents: int, details: array<string, mixed>}>
     */
    public function fixedBundleAllocations(Promotion $promotion, array $lines): array
    {
        $reward = $promotion->rewards->first();
        if (! $reward instanceof PromotionReward
            || $reward->reward_type !== PromotionReward::TYPE_FIXED_BUNDLE_PRICE
            || (int) ($reward->bundle_quantity ?? 0) < 1
            || (float) ($reward->bundle_price ?? 0) <= 0
        ) {
            return [];
        }

        $bundleQuantity = (int) $reward->bundle_quantity;
        $bundlePriceCents = $this->moneyToCents((string) $reward->bundle_price);
        $units = $this->expandedWholeUnits($lines);
        $bundleCount = intdiv(count($units), $bundleQuantity);

        if ($bundleCount < 1) {
            return [];
        }

        $participatingUnits = array_slice($units, 0, $bundleCount * $bundleQuantity);
        $participatingBaseCents = array_sum(array_column($participatingUnits, 'unit_cents'));
        $bundleTotalCents = $bundleCount * $bundlePriceCents;
        $discountCents = max(0, $participatingBaseCents - $bundleTotalCents);

        if ($discountCents <= 0) {
            return [];
        }

        $baseByVariant = [];
        $quantityByVariant = [];
        foreach ($participatingUnits as $unit) {
            $baseByVariant[$unit['variant_id']] = ($baseByVariant[$unit['variant_id']] ?? 0) + $unit['unit_cents'];
            $quantityByVariant[$unit['variant_id']] = ($quantityByVariant[$unit['variant_id']] ?? 0) + 1;
        }

        $allocations = $this->allocateByBaseValue($discountCents, $baseByVariant);
        $result = [];

        foreach ($allocations as $variantId => $allocatedCents) {
            $result[$variantId] = [
                'discount_cents' => $allocatedCents,
                'details' => [
                    'bundle_quantity' => $bundleQuantity,
                    'bundle_price' => $this->moneyFromCents($bundlePriceCents),
                    'completed_bundles' => $bundleCount,
                    'participating_quantity' => $quantityByVariant[$variantId] ?? 0,
                    'participating_variant_ids' => array_values(array_unique(array_column($participatingUnits, 'variant_id'))),
                    'allocation_method' => 'proportional_by_participating_base_value',
                    'unit_selection' => 'highest_priced_eligible_whole_units',
                ],
            ];
        }

        return $result;
    }

    private function quantityDiscountCents(Promotion $promotion, int $baseUnitCents, int $quantityUnits, PromotionReward $reward): int
    {
        $minimum = $promotion->conditions
            ->first(fn (PromotionCondition $condition): bool => $condition->condition_type === PromotionCondition::TYPE_MINIMUM_QUANTITY);

        if (! $minimum instanceof PromotionCondition || $quantityUnits < $this->quantityToUnits((string) $minimum->value_numeric)) {
            return 0;
        }

        if ($reward->value_type === 'amount') {
            return intdiv(($this->moneyToCents((string) $reward->value_amount) * $quantityUnits) + 500, 1000);
        }

        return $this->percentageDiscountCents(intdiv(($baseUnitCents * $quantityUnits) + 500, 1000), $reward);
    }

    private function tierPricingDiscountCents(int $baseUnitCents, int $quantityUnits, PromotionReward $reward): int
    {
        $tier = $this->matchedTier($reward->tier_config, $quantityUnits);
        if ($tier === null) {
            return 0;
        }

        $tierUnitCents = $this->moneyToCents((string) $tier['unit_price']);
        if ($tierUnitCents >= $baseUnitCents) {
            return 0;
        }

        return intdiv((($baseUnitCents - $tierUnitCents) * $quantityUnits) + 500, 1000);
    }

    /**
     * @param array<int, mixed>|null $tiers
     * @return array{min_quantity: int, unit_price: mixed}|null
     */
    private function matchedTier(?array $tiers, int $quantityUnits): ?array
    {
        if (! is_array($tiers)) {
            return null;
        }

        return collect($tiers)
            ->filter(fn ($tier): bool => is_array($tier)
                && (int) ($tier['min_quantity'] ?? 0) > 0
                && is_numeric($tier['unit_price'] ?? null)
                && (float) $tier['unit_price'] > 0
                && $quantityUnits >= $this->quantityToUnits((string) $tier['min_quantity']))
            ->sortByDesc(fn (array $tier): int => (int) $tier['min_quantity'])
            ->first();
    }

    /**
     * @param array<int, PromotionLineInput> $lines
     * @return array<int, array{variant_id: int, unit_cents: int}>
     */
    private function expandedWholeUnits(array $lines): array
    {
        $units = [];

        foreach ($lines as $line) {
            $wholeUnits = intdiv($this->quantityToUnits($line->quantity), 1000);
            $unitCents = $this->moneyToCents($line->baseUnitPrice);

            for ($index = 0; $index < $wholeUnits; $index++) {
                $units[] = [
                    'variant_id' => $line->variantId,
                    'unit_cents' => $unitCents,
                ];
            }
        }

        usort($units, fn (array $left, array $right): int => ($right['unit_cents'] <=> $left['unit_cents'])
            ?: ($left['variant_id'] <=> $right['variant_id']));

        return $units;
    }

    /**
     * @param array<int, int> $baseByVariant
     * @return array<int, int>
     */
    private function allocateByBaseValue(int $discountCents, array $baseByVariant): array
    {
        $totalBase = array_sum($baseByVariant);
        if ($discountCents <= 0 || $totalBase <= 0) {
            return [];
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($baseByVariant as $variantId => $baseCents) {
            $raw = $discountCents * $baseCents;
            $share = intdiv($raw, $totalBase);
            $allocations[$variantId] = $share;
            $remainders[$variantId] = $raw % $totalBase;
            $allocated += $share;
        }

        $remaining = $discountCents - $allocated;
        $remainderRows = [];

        foreach ($remainders as $variantId => $remainder) {
            $remainderRows[] = [
                'variant_id' => (int) $variantId,
                'remainder' => $remainder,
            ];
        }

        usort($remainderRows, fn (array $left, array $right): int => ($right['remainder'] <=> $left['remainder'])
            ?: ($left['variant_id'] <=> $right['variant_id']));

        foreach ($remainderRows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $allocations[$row['variant_id']]++;
            $remaining--;
        }

        ksort($allocations);

        return $allocations;
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

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
