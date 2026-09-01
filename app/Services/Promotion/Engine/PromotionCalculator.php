<?php

namespace App\Services\Promotion\Engine;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
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
        $lineAdjustments = [];

        foreach ($rows as $variantId => $row) {
            $variant = $variants[$variantId] ?? null;
            if (! $variant instanceof ProductVariant || ! $variant->product instanceof Product) {
                continue;
            }

            $line = $this->lineInput($variant, (string) $row['quantity']);
            $baseLineSubtotalCents = $this->lineSubtotalCents($line);
            $candidates = $this->candidates($promotions, $line, $customer);
            $winner = $this->resolver->winningPromotion($candidates);
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
     * @return array<int, array{promotion: Promotion, discount_cents: int}>
     */
    private function candidates(Collection $promotions, PromotionLineInput $line, ?Customer $customer): array
    {
        $candidates = [];

        foreach ($promotions as $promotion) {
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

            $candidates[] = [
                'promotion' => $promotion,
                'discount_cents' => $discountCents,
            ];
        }

        return $candidates;
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
            ];
        }, $candidates);
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
