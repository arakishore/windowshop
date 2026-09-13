<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShopOfferProductService
{
    public function __construct(
        private readonly ShopPromotionPresenter $promotions,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function productIdsForShop(Shop $shop): array
    {
        $productIds = collect();

        $this->promotions->currentPromotionModels($shop)
            ->each(function (Promotion $promotion) use ($shop, $productIds): void {
                $productIds->push(...$this->productIdsForPromotion($promotion, $shop));
            });

        return $productIds
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function productIdsForPromotion(Promotion $promotion, Shop $shop): array
    {
        $reward = $promotion->rewards->first();

        if (! $reward instanceof PromotionReward) {
            return [];
        }

        $roles = $this->offerProductRoles($reward);
        $targets = $promotion->targets->whereIn('target_role', $roles)->values();

        if ($targets->isEmpty()) {
            return [];
        }

        if ($targets->contains(fn (PromotionTarget $target): bool => $target->target_type === PromotionTarget::TYPE_ALL)) {
            return Product::query()
                ->where('shop_id', $shop->getKey())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $productIds = collect();

        $productTargetIds = $targets
            ->where('target_type', PromotionTarget::TYPE_PRODUCT)
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($productTargetIds->isNotEmpty()) {
            $productIds->push(...Product::query()
                ->where('shop_id', $shop->getKey())
                ->whereIn('id', $productTargetIds->all())
                ->pluck('id')
                ->all());
        }

        $variantTargetIds = $targets
            ->where('target_type', PromotionTarget::TYPE_VARIANT)
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($variantTargetIds->isNotEmpty()) {
            $productIds->push(...ProductVariant::query()
                ->where('shop_id', $shop->getKey())
                ->whereIn('id', $variantTargetIds->all())
                ->pluck('product_id')
                ->all());
        }

        $categoryTargetIds = $targets
            ->where('target_type', PromotionTarget::TYPE_CATEGORY)
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($categoryTargetIds->isNotEmpty()) {
            $categoryIds = $this->categoryAndDescendantIds($categoryTargetIds->all());
            $productIds->push(...Product::query()
                ->where('shop_id', $shop->getKey())
                ->whereIn('product_category_id', $categoryIds)
                ->pluck('id')
                ->all());
        }

        $brandTargetIds = $targets
            ->where('target_type', PromotionTarget::TYPE_BRAND)
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($brandTargetIds->isNotEmpty()) {
            $productIds->push(...Product::query()
                ->where('shop_id', $shop->getKey())
                ->whereIn('brand_id', $brandTargetIds->all())
                ->pluck('id')
                ->all());
        }

        $collectionTargetIds = $targets
            ->where('target_type', PromotionTarget::TYPE_COLLECTION)
            ->pluck('target_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($collectionTargetIds->isNotEmpty()) {
            $productIds->push(...DB::table('collection_products')
                ->join('products', 'products.id', '=', 'collection_products.product_id')
                ->where('products.shop_id', $shop->getKey())
                ->whereIn('collection_products.collection_id', $collectionTargetIds->all())
                ->pluck('products.id')
                ->all());
        }

        return $productIds
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function offerProductRoles(PromotionReward $reward): array
    {
        return match ($reward->reward_type) {
            PromotionReward::TYPE_BUY_X_GET_Y_FREE,
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => [
                PromotionTarget::ROLE_BUY,
                PromotionTarget::ROLE_GET,
                PromotionTarget::ROLE_ELIGIBLE,
            ],
            default => [PromotionTarget::ROLE_ELIGIBLE],
        };
    }

    /**
     * @param array<int, int> $categoryIds
     * @return array<int, int>
     */
    private function categoryAndDescendantIds(array $categoryIds): array
    {
        return ProductCategory::query()
            ->where(function (Builder $query) use ($categoryIds): void {
                $query
                    ->whereIn('id', $categoryIds)
                    ->orWhereIn('parent_id', $categoryIds)
                    ->orWhereHas('parent', fn (Builder $query) => $query->whereIn('parent_id', $categoryIds));
            })
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
