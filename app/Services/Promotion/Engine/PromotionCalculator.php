<?php

namespace App\Services\Promotion\Engine;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\Shop;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use App\Services\Promotion\Engine\Data\PromotionLineAdjustment;
use App\Services\Promotion\Engine\Data\PromotionLineInput;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PromotionCalculator
{
    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly PromotionTargetMatcher $targets,
        private readonly PromotionConditionEvaluator $conditions,
        private readonly PromotionRewardCalculator $rewards,
        private readonly PromotionCombinationResolver $resolver,
    ) {
    }

    /**
     * @param array<int, array{product_variant_id?: int, variant_id?: int, quantity?: mixed}> $rows
     */
    public function calculateForShop(Shop $shop, array $rows, ?Customer $customer = null, ?CarbonInterface $effectiveAt = null): PromotionCalculationResult
    {
        $rows = $this->aggregateRows($rows);
        $variants = $this->variantsForRows($shop, $rows);

        return $this->calculateForVariantRows($shop, $rows, $variants, $customer, $effectiveAt);
    }

    /**
     * @param array<int, array{quantity: int|float|string}> $rows
     * @param array<int, ProductVariant> $variants
     */
    public function calculateForVariantRows(Shop $shop, array $rows, array $variants, ?Customer $customer = null, ?CarbonInterface $effectiveAt = null): PromotionCalculationResult
    {
        $effectiveAt ??= now();
        $promotions = $this->promotions->automaticActiveForShop((int) $shop->getKey(), $effectiveAt);
        $lines = $this->lineInputs($rows, $variants);
        $candidatesByVariant = $this->candidatesByVariant($promotions, $lines, $customer);
        $lineAdjustments = [];

        foreach ($lines as $line) {
            $baseLineSubtotalCents = $this->lineSubtotalCents($line);
            $candidates = $candidatesByVariant[$line->variantId] ?? [];
            $winner = $this->resolver->winningPromotion($candidates)
                ?? $this->resolver->participationPromotion($candidates);
            $discountCents = $winner?->discountCents ?? 0;

            $lineAdjustments[$line->variantId] = new PromotionLineAdjustment(
                line: $line,
                baseLineSubtotalCents: $baseLineSubtotalCents,
                promotionDiscountCents: $discountCents,
                finalLineSubtotalCents: max(0, $baseLineSubtotalCents - $discountCents),
                winningPromotion: $winner,
                eligiblePromotions: $this->eligiblePromotionMetadata($candidates),
            );
        }

        return new PromotionCalculationResult((int) $shop->getKey(), $lineAdjustments);
    }

    /**
     * @param Collection<int, Promotion> $promotions
     * @param array<int, PromotionLineInput> $lines
     * @return array<int, array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>>
     */
    private function candidatesByVariant(Collection $promotions, array $lines, ?Customer $customer): array
    {
        $candidates = [];
        $bundlePromotions = [];
        $bogoPromotions = [];

        foreach ($promotions as $promotion) {
            $rewardType = (string) $promotion->rewards->first()?->reward_type;

            if ($rewardType === PromotionReward::TYPE_FIXED_BUNDLE_PRICE) {
                $bundlePromotions[] = $promotion;
                continue;
            }

            if (in_array($rewardType, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)) {
                $bogoPromotions[] = $promotion;
                continue;
            }

            foreach ($lines as $line) {
                if (! $this->targets->matches($promotion, $line, PromotionTarget::ROLE_ELIGIBLE)) {
                    continue;
                }

                if (! $this->conditions->passes($promotion, $line, $customer)) {
                    continue;
                }

                $discountCents = $this->rewards->discountCents($promotion, $line);
                if ($discountCents <= 0) {
                    continue;
                }

                $candidates[$line->variantId][] = [
                    'promotion' => $promotion,
                    'discount_cents' => $discountCents,
                    'details' => $this->linePromotionDetails($promotion, $line),
                ];
            }
        }

        foreach ($bundlePromotions as $promotion) {
            foreach ($this->fixedBundleCandidates($promotion, $lines, $customer, $candidates) as $variantId => $candidate) {
                $candidates[$variantId][] = $candidate;
            }
        }

        foreach ($bogoPromotions as $promotion) {
            foreach ($this->buyXGetYCandidates($promotion, $lines, $customer, $candidates) as $variantId => $candidate) {
                $candidates[$variantId][] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, PromotionLineInput> $lines
     * @param array<int, array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>> $existingCandidates
     * @return array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>
     */
    private function fixedBundleCandidates(Promotion $promotion, array $lines, ?Customer $customer, array $existingCandidates): array
    {
        $eligibleLines = [];

        foreach ($lines as $line) {
            if (! $this->targets->matches($promotion, $line, PromotionTarget::ROLE_ELIGIBLE)) {
                continue;
            }

            if (! $this->conditions->passes($promotion, $line, $customer)) {
                continue;
            }

            $eligibleLines[$line->variantId] = $line;
        }

        $allocations = $this->rewards->fixedBundleAllocations($promotion, $eligibleLines);
        $candidates = [];

        foreach ($allocations as $variantId => $allocation) {
            if ((int) $allocation['discount_cents'] <= 0) {
                continue;
            }

            $candidates[$variantId] = [
                'promotion' => $promotion,
                'discount_cents' => (int) $allocation['discount_cents'],
                'details' => $allocation['details'] ?? [],
            ];
        }

        foreach ($candidates as $variantId => $candidate) {
            $winner = $this->resolver->winningPromotion([
                ...($existingCandidates[$variantId] ?? []),
                $candidate,
            ]);

            if ($winner?->promotionId !== (int) $promotion->getKey()) {
                return [];
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, PromotionLineInput> $lines
     * @param array<int, array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>> $existingCandidates
     * @return array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>
     */
    private function buyXGetYCandidates(Promotion $promotion, array $lines, ?Customer $customer, array $existingCandidates): array
    {
        $reward = $promotion->rewards->first();
        $buyQuantity = (int) ($reward?->buy_quantity ?? 0);
        $getQuantity = (int) ($reward?->get_quantity ?? 0);

        if (! $reward instanceof PromotionReward
            || ! in_array($reward->reward_type, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)
            || $buyQuantity < 1
            || $getQuantity < 1
        ) {
            return [];
        }

        $eligibleLines = [];
        $buyUnits = [];
        $getUnits = [];

        foreach ($lines as $line) {
            if (! $this->conditions->passes($promotion, $line, $customer)) {
                continue;
            }

            $eligibleLines[$line->variantId] = $line;
            $lineUnits = $this->expandedBogoUnits($line);

            if ($this->targets->matches($promotion, $line, PromotionTarget::ROLE_BUY)) {
                $buyUnits = [...$buyUnits, ...$lineUnits];
            }

            if ($this->targets->matches($promotion, $line, PromotionTarget::ROLE_GET)) {
                $getUnits = [...$getUnits, ...$lineUnits];
            }
        }

        $allocation = $this->buyXGetYAllocation($buyUnits, $getUnits, $buyQuantity, $getQuantity);
        if ($allocation === null) {
            return [];
        }

        $candidates = $this->buyXGetYCandidatesFromAllocation($promotion, $reward, $allocation);
        if ($candidates === []) {
            return [];
        }

        foreach ($candidates as $variantId => $candidate) {
            if (! isset($eligibleLines[$variantId])) {
                return [];
            }

            if (! $this->groupCandidateCanSurvive($promotion, $candidate, $existingCandidates[$variantId] ?? [])) {
                return $this->buyXGetYReducedCandidates($promotion, $reward, $buyUnits, $getUnits, $buyQuantity, $getQuantity, $existingCandidates, (int) $allocation['completed_groups'] - 1);
            }
        }

        return $candidates;
    }

    /**
     * @param array<int, array<string, mixed>> $buyUnits
     * @param array<int, array<string, mixed>> $getUnits
     * @param array<int, array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>> $existingCandidates
     * @return array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>
     */
    private function buyXGetYReducedCandidates(Promotion $promotion, PromotionReward $reward, array $buyUnits, array $getUnits, int $buyQuantity, int $getQuantity, array $existingCandidates, int $maxGroups): array
    {
        for ($groups = $maxGroups; $groups >= 1; $groups--) {
            $allocation = $this->buyXGetYAllocation($buyUnits, $getUnits, $buyQuantity, $getQuantity, $groups);
            if ($allocation === null) {
                continue;
            }

            $candidates = $this->buyXGetYCandidatesFromAllocation($promotion, $reward, $allocation);
            if ($candidates === []) {
                continue;
            }

            foreach ($candidates as $variantId => $candidate) {
                if (! $this->groupCandidateCanSurvive($promotion, $candidate, $existingCandidates[$variantId] ?? [])) {
                    continue 2;
                }
            }

            return $candidates;
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $buyUnits
     * @param array<int, array<string, mixed>> $getUnits
     * @return array{completed_groups: int, buy_quantity: int, get_quantity: int, buy_units: array<int, array<string, mixed>>, get_units: array<int, array<string, mixed>>, pool_type: string}|null
     */
    private function buyXGetYAllocation(array $buyUnits, array $getUnits, int $buyQuantity, int $getQuantity, ?int $maxGroups = null): ?array
    {
        $buyByKey = $this->unitsByKey($buyUnits);
        $getByKey = $this->unitsByKey($getUnits);

        if ($buyByKey === [] || $getByKey === []) {
            return null;
        }

        $overlapKeys = array_intersect(array_keys($buyByKey), array_keys($getByKey));
        $poolType = match (true) {
            count($overlapKeys) === 0 => 'different',
            count($overlapKeys) === count($buyByKey) && count($overlapKeys) === count($getByKey) => 'same',
            default => 'partial_overlap',
        };
        $theoreticalGroups = match ($poolType) {
            'same' => intdiv(count($getByKey), $buyQuantity + $getQuantity),
            default => min(intdiv(count($buyByKey), $buyQuantity), intdiv(count($getByKey), $getQuantity)),
        };

        for ($groups = min($maxGroups ?? $theoreticalGroups, $theoreticalGroups); $groups >= 1; $groups--) {
            $freeUnits = $this->buyXGetYRewardedGetUnits($buyByKey, $getByKey, $poolType, $groups * $buyQuantity, $groups * $getQuantity);
            if ($freeUnits === []) {
                continue;
            }

            $freeKeys = array_fill_keys(array_column($freeUnits, 'unit_key'), true);
            $availableBuyUnits = array_values(array_filter(
                array_values($buyByKey),
                fn (array $unit): bool => ! isset($freeKeys[$unit['unit_key']])
            ));

            if (count($availableBuyUnits) < $groups * $buyQuantity) {
                continue;
            }

            $consumedBuyUnits = array_slice($this->sortedBuyUnits($availableBuyUnits), 0, $groups * $buyQuantity);

            return [
                'completed_groups' => $groups,
                'buy_quantity' => $buyQuantity,
                'get_quantity' => $getQuantity,
                'buy_units' => $consumedBuyUnits,
                'get_units' => $freeUnits,
                'pool_type' => $poolType,
            ];
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $buyByKey
     * @param array<string, array<string, mixed>> $getByKey
     * @return array<int, array<string, mixed>>
     */
    private function buyXGetYRewardedGetUnits(array $buyByKey, array $getByKey, string $poolType, int $requiredBuyUnits, int $requiredGetUnits): array
    {
        if ($requiredGetUnits < 1) {
            return [];
        }

        if ($poolType !== 'partial_overlap') {
            $units = array_slice($this->sortedGetUnits(array_values($getByKey)), 0, $requiredGetUnits);

            return count($units) === $requiredGetUnits ? $units : [];
        }

        $remainingBuyCapacity = count($buyByKey) - $requiredBuyUnits;
        if ($remainingBuyCapacity < 0) {
            return [];
        }

        $selected = [];
        $selectedOverlap = 0;

        foreach ($this->sortedGetUnits(array_values($getByKey)) as $unit) {
            $overlapsBuy = isset($buyByKey[(string) $unit['unit_key']]);

            if ($overlapsBuy && $selectedOverlap >= $remainingBuyCapacity) {
                continue;
            }

            $selected[] = $unit;
            $selectedOverlap += $overlapsBuy ? 1 : 0;

            if (count($selected) === $requiredGetUnits) {
                return $selected;
            }
        }

        return [];
    }

    /**
     * @param array{completed_groups: int, buy_quantity: int, get_quantity: int, buy_units: array<int, array<string, mixed>>, get_units: array<int, array<string, mixed>>, pool_type: string} $allocation
     * @return array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}>
     */
    private function buyXGetYCandidatesFromAllocation(Promotion $promotion, PromotionReward $reward, array $allocation): array
    {
        $buyByVariant = $this->summarizeBogoUnits($allocation['buy_units']);
        $getByVariant = $this->summarizeBogoUnits($allocation['get_units'], $reward);
        $totalRewardDiscountCents = array_sum(array_column($getByVariant, 'discount_cents'));

        if ($totalRewardDiscountCents <= 0) {
            return [];
        }

        $variantIds = array_values(array_unique([
            ...array_keys($buyByVariant),
            ...array_keys($getByVariant),
        ]));
        $candidates = [];

        foreach ($variantIds as $variantId) {
            $buy = $buyByVariant[$variantId] ?? ['quantity' => 0, 'discount_cents' => 0, 'units' => []];
            $get = $getByVariant[$variantId] ?? ['quantity' => 0, 'discount_cents' => 0, 'units' => []];
            $roles = [];

            if ($buy['quantity'] > 0) {
                $roles[] = 'buy';
            }

            if ($get['quantity'] > 0) {
                $roles[] = 'get';
            }

            $candidates[$variantId] = [
                'promotion' => $promotion,
                'discount_cents' => (int) $get['discount_cents'],
                'details' => [
                    'reward_type' => $reward->reward_type,
                    'roles' => $roles,
                    'role' => count($roles) === 1 ? $roles[0] : 'buy_get',
                    'buy_quantity' => (int) $allocation['buy_quantity'],
                    'get_quantity' => (int) $allocation['get_quantity'],
                    'completed_groups' => (int) $allocation['completed_groups'],
                    'pool_type' => $allocation['pool_type'],
                    'participating_buy_quantity' => (int) $buy['quantity'],
                    'participating_get_quantity' => (int) $get['quantity'],
                    'rewarded_get_quantity' => (int) $get['quantity'],
                    'participating_quantity' => (int) $buy['quantity'] + (int) $get['quantity'],
                    'free_quantity' => $reward->reward_type === PromotionReward::TYPE_BUY_X_GET_Y_FREE ? (int) $get['quantity'] : 0,
                    'reward_value' => $this->buyXGetYRewardValue($reward),
                    'reward_unit' => $this->buyXGetYRewardUnit($reward),
                    'free_unit_value' => $reward->reward_type === PromotionReward::TYPE_BUY_X_GET_Y_FREE && $get['quantity'] > 0 ? $this->moneyFromCents(intdiv((int) $get['discount_cents'], (int) $get['quantity'])) : '0.00',
                    'promotion_discount' => $this->moneyFromCents((int) $get['discount_cents']),
                    'group_discount' => $this->moneyFromCents($totalRewardDiscountCents),
                    'group_discount_cents' => $totalRewardDiscountCents,
                    'buy_units' => $buy['units'],
                    'get_units' => $get['units'],
                    'consumed_buy_units' => $buy['units'],
                    'rewarded_get_units' => $get['units'],
                    'unit_selection' => 'cheapest_eligible_get_whole_units',
                    'quantity_rule' => 'whole_units_only_fractional_remainder_base_price',
                ],
            ];
        }

        ksort($candidates);

        return $candidates;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array{quantity: int, discount_cents: int, units: array<int, array<string, mixed>>}>
     */
    private function summarizeBogoUnits(array $units, ?PromotionReward $reward = null): array
    {
        $summary = [];

        foreach ($units as $unit) {
            $variantId = (int) $unit['variant_id'];
            $summary[$variantId] ??= ['quantity' => 0, 'discount_cents' => 0, 'units' => []];
            $summary[$variantId]['quantity']++;
            $unitDiscountCents = $reward instanceof PromotionReward
                ? $this->buyXGetYUnitDiscountCents((int) $unit['unit_cents'], $reward)
                : (int) $unit['unit_cents'];
            $summary[$variantId]['discount_cents'] += $unitDiscountCents;
            $summary[$variantId]['units'][] = [
                'variant_id' => $variantId,
                'product_id' => (int) $unit['product_id'],
                'unit_index' => (int) $unit['unit_index'],
                'unit_price' => $this->moneyFromCents((int) $unit['unit_cents']),
                'discount_amount' => $this->moneyFromCents($unitDiscountCents),
            ];
        }

        ksort($summary);

        return $summary;
    }

    private function buyXGetYUnitDiscountCents(int $unitCents, PromotionReward $reward): int
    {
        if ($reward->reward_type === PromotionReward::TYPE_BUY_X_GET_Y_FREE) {
            return $unitCents;
        }

        if ($reward->reward_type !== PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT) {
            return 0;
        }

        $percentUnits = (int) round(((float) $reward->value_percent) * 100);
        if ($percentUnits <= 0) {
            return 0;
        }

        return max(0, min($unitCents, intdiv(($unitCents * $percentUnits) + 5000, 10000)));
    }

    private function buyXGetYRewardValue(PromotionReward $reward): ?string
    {
        return match ($reward->reward_type) {
            PromotionReward::TYPE_BUY_X_GET_Y_FREE => '100.00',
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => $reward->value_percent ? (string) $reward->value_percent : null,
            default => null,
        };
    }

    private function buyXGetYRewardUnit(PromotionReward $reward): string
    {
        return match ($reward->reward_type) {
            PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => 'percent',
            default => 'unknown',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @return array<string, array<string, mixed>>
     */
    private function unitsByKey(array $units): array
    {
        $byKey = [];

        foreach ($units as $unit) {
            $byKey[(string) $unit['unit_key']] = $unit;
        }

        ksort($byKey);

        return $byKey;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expandedBogoUnits(PromotionLineInput $line): array
    {
        $units = [];
        $wholeUnits = intdiv($this->quantityToUnits($line->quantity), 1000);
        $unitCents = $this->moneyToCents($line->baseUnitPrice);

        for ($index = 0; $index < $wholeUnits; $index++) {
            $units[] = [
                'unit_key' => $line->variantId.':'.$index,
                'variant_id' => $line->variantId,
                'product_id' => $line->productId,
                'unit_index' => $index,
                'unit_cents' => $unitCents,
            ];
        }

        return $units;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<string, mixed>>
     */
    private function sortedGetUnits(array $units): array
    {
        usort($units, fn (array $left, array $right): int => ((int) $left['unit_cents'] <=> (int) $right['unit_cents'])
            ?: ((int) $left['variant_id'] <=> (int) $right['variant_id'])
            ?: ((int) $left['unit_index'] <=> (int) $right['unit_index']));

        return $units;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<string, mixed>>
     */
    private function sortedBuyUnits(array $units): array
    {
        usort($units, fn (array $left, array $right): int => ((int) $right['unit_cents'] <=> (int) $left['unit_cents'])
            ?: ((int) $left['variant_id'] <=> (int) $right['variant_id'])
            ?: ((int) $left['unit_index'] <=> (int) $right['unit_index']));

        return $units;
    }

    /**
     * @param array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>} $candidate
     * @param array<int, array{promotion: Promotion, discount_cents: int, details?: array<string, mixed>}> $existingCandidates
     */
    private function groupCandidateCanSurvive(Promotion $promotion, array $candidate, array $existingCandidates): bool
    {
        if ((int) $candidate['discount_cents'] <= 0) {
            return $this->resolver->winningPromotion($existingCandidates) === null;
        }

        $winner = $this->resolver->winningPromotion([...$existingCandidates, $candidate]);

        return $winner?->promotionId === (int) $promotion->getKey();
    }

    /**
     * @param array<int, array{promotion: Promotion, discount_cents: int}> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function eligiblePromotionMetadata(array $candidates): array
    {
        return array_map(function (array $candidate): array {
            /** @var Promotion $promotion */
            $promotion = $candidate['promotion'];

            return [
                'id' => (int) $promotion->getKey(),
                'name' => (string) $promotion->name,
                'slug' => $promotion->slug,
                'template_code' => (string) $promotion->template?->code,
                'reward_type' => (string) $promotion->rewards->first()?->reward_type,
                'priority' => (int) $promotion->priority,
                'discount_amount' => $this->moneyFromCents((int) $candidate['discount_cents']),
                'details' => $candidate['details'] ?? [],
            ];
        }, $candidates);
    }

    /**
     * @param array<int, array{quantity: int|float|string}> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, PromotionLineInput>
     */
    private function lineInputs(array $rows, array $variants): array
    {
        $lines = [];

        foreach ($rows as $variantId => $row) {
            $variant = $variants[$variantId] ?? null;
            if (! $variant instanceof ProductVariant || ! $variant->product instanceof Product) {
                continue;
            }

            $lines[(int) $variantId] = $this->lineInput($variant, (string) $row['quantity']);
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function linePromotionDetails(Promotion $promotion, PromotionLineInput $line): array
    {
        $reward = $promotion->rewards->first();

        return match ((string) $reward?->reward_type) {
            PromotionReward::TYPE_QUANTITY_DISCOUNT => [
                'eligible_quantity' => $line->quantity,
                'minimum_quantity' => (string) $promotion->conditions
                    ->first(fn ($condition): bool => $condition->condition_type === PromotionCondition::TYPE_MINIMUM_QUANTITY)?->value_numeric,
                'value_type' => $reward?->value_type,
                'value_amount' => $reward?->value_amount ? (string) $reward->value_amount : null,
                'value_percent' => $reward?->value_percent ? (string) $reward->value_percent : null,
            ],
            PromotionReward::TYPE_TIER_PRICING => [
                'eligible_quantity' => $line->quantity,
                'tier_model' => 'volume',
                'tier_config' => $reward?->tier_config,
            ],
            default => [],
        };
    }

    private function lineInput(ProductVariant $variant, string $quantity): PromotionLineInput
    {
        $product = $variant->product;

        return new PromotionLineInput(
            variantId: (int) $variant->getKey(),
            productId: (int) $variant->product_id,
            shopId: (int) $variant->shop_id,
            quantity: $quantity,
            baseUnitPrice: $this->money($variant->selling_price),
            categoryIds: $this->categoryIds($product),
            brandId: $product?->brand_id ? (int) $product->brand_id : null,
            collectionIds: $product?->collections->pluck('id')->map(fn ($id): int => (int) $id)->all() ?? [],
        );
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(?Product $product): array
    {
        if (! $product instanceof Product) {
            return [];
        }

        $ids = collect([
            $product->product_category_id ? (int) $product->product_category_id : null,
            $product->root_product_category_id ? (int) $product->root_product_category_id : null,
        ])->filter()->values();

        $category = $product->category;
        $visited = [];

        while ($category instanceof ProductCategory && ! in_array((int) $category->getKey(), $visited, true)) {
            $visited[] = (int) $category->getKey();
            $ids->push((int) $category->getKey());
            $category = $category->parent;
        }

        return $ids->unique()->values()->all();
    }

    /**
     * @param array<int, array{product_variant_id?: int, variant_id?: int, quantity?: mixed}> $rows
     * @return array<int, array{quantity: string}>
     */
    private function aggregateRows(array $rows): array
    {
        $aggregated = [];

        foreach ($rows as $row) {
            $variantId = (int) ($row['product_variant_id'] ?? $row['variant_id'] ?? 0);
            $quantity = (float) ($row['quantity'] ?? 0);

            if ($variantId < 1 || $quantity <= 0) {
                continue;
            }

            if (! isset($aggregated[$variantId])) {
                $aggregated[$variantId] = ['quantity' => 0.0];
            }

            $aggregated[$variantId]['quantity'] += $quantity;
        }

        return array_map(fn (array $row): array => [
            'quantity' => $this->quantity($row['quantity']),
        ], $aggregated);
    }

    /**
     * @param array<int, array{quantity: string}> $rows
     * @return array<int, ProductVariant>
     */
    private function variantsForRows(Shop $shop, array $rows): array
    {
        return ProductVariant::query()
            ->with([
                'product.category.parent.parent',
                'product.brand',
                'product.collections' => fn ($query) => $query
                    ->where('collections.shop_id', $shop->getKey())
                    ->whereNull('collections.deleted_at'),
            ])
            ->where('shop_id', $shop->getKey())
            ->whereIn('id', array_keys($rows))
            ->get()
            ->keyBy(fn (ProductVariant $variant): int => (int) $variant->getKey())
            ->all();
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

    private function money(float|string|int|null $value): string
    {
        return number_format(round((float) ($value ?? 0), 2), 2, '.', '');
    }

    private function quantity(float|string|int $value): string
    {
        return number_format(round((float) $value, 3), 3, '.', '');
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
