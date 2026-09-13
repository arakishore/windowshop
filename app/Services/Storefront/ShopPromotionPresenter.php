<?php

namespace App\Services\Storefront;

use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShopPromotionPresenter
{
    public function __construct(
        private readonly ProductPromotionPresenter $productPromotions,
    ) {
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function currentPromotionModels(Shop $shop, ?int $limit = null): Collection
    {
        $query = Promotion::query()
            ->with([
                'template:id,name,reward_type',
                'rewards',
                'targets',
                'conditions',
                'coupons' => fn ($query) => $this->activeCouponScope($query),
            ])
            ->activeNow(now())
            ->where('shop_id', $shop->getKey())
            ->where(function (Builder $query): void {
                $query
                    ->where('activation_type', Promotion::ACTIVATION_AUTOMATIC)
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('activation_type', Promotion::ACTIVATION_COUPON)
                            ->whereHas('coupons', fn (Builder $query) => $this->activeCouponScope($query));
                    });
            })
            ->whereHas('rewards', fn (Builder $query) => $query->whereIn('reward_type', $this->supportedRewardTypes()))
            ->orderByDesc('priority')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit(max(1, $limit) * 2);
        }

        $promotions = $query
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isSetupComplete());

        if ($limit !== null) {
            $promotions = $promotions->take(max(1, $limit));
        }

        return $promotions->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function currentForShop(Shop $shop, ?int $limit = null): Collection
    {
        return $this->currentPromotionModels($shop, $limit)
            ->map(fn (Promotion $promotion): array => $this->card($promotion, $shop))
            ->values();
    }

    /**
     * @param iterable<int, int|string> $shopIds
     * @return array<int, int>
     */
    public function currentOfferShopIds(iterable $shopIds): array
    {
        $shopIds = collect($shopIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($shopIds->isEmpty()) {
            return [];
        }

        return Promotion::query()
            ->with([
                'template:id,name,reward_type',
                'rewards',
                'targets',
                'conditions',
                'coupons' => fn ($query) => $this->activeCouponScope($query),
            ])
            ->activeNow(now())
            ->whereIn('shop_id', $shopIds->all())
            ->where(function (Builder $query): void {
                $query
                    ->where('activation_type', Promotion::ACTIVATION_AUTOMATIC)
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('activation_type', Promotion::ACTIVATION_COUPON)
                            ->whereHas('coupons', fn (Builder $query) => $this->activeCouponScope($query));
                    });
            })
            ->whereHas('rewards', fn (Builder $query) => $query->whereIn('reward_type', $this->supportedRewardTypes()))
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isSetupComplete())
            ->pluck('shop_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function activeCouponScope($query)
    {
        $now = now();

        return $query
            ->where('status', PromotionCoupon::STATUS_ACTIVE)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Promotion $promotion, ?Shop $shop = null): array
    {
        $display = $this->productPromotions->forPromotion($promotion);
        $coupon = $promotion->activation_type === Promotion::ACTIVATION_COUPON
            ? $promotion->coupons->first()
            : null;

        return [
            'identifier' => $promotion->uuid,
            'label' => $display['promotion_label'],
            'icon' => $display['promotion_icon'],
            'name' => $this->displayName($promotion, $coupon),
            'description' => $this->description($promotion),
            'scope' => $this->scopeLabel($promotion),
            'activation_type' => $promotion->activation_type,
            'ends_at' => $promotion->ends_at,
            'products_url' => $shop instanceof Shop
                ? route('storefront.stores.offers', [
                    'slug' => $shop->slug,
                    'promotion' => $promotion->uuid,
                ])
                : null,
        ];
    }

    private function displayName(Promotion $promotion, ?PromotionCoupon $coupon): string
    {
        $name = trim((string) $promotion->name);

        if ($coupon instanceof PromotionCoupon && $coupon->code !== '') {
            $name = trim(str_ireplace((string) $coupon->code, '', $name));
            $name = preg_replace('/\s+/', ' ', trim($name, " \t\n\r\0\x0B-_:|")) ?: '';
        }

        if ($name !== '') {
            return $name;
        }

        return $promotion->template?->name ?: 'Shop offer';
    }

    private function description(Promotion $promotion): ?string
    {
        $description = trim((string) $promotion->description);

        if ($description !== '') {
            return $description;
        }

        return null;
    }

    private function scopeLabel(Promotion $promotion): string
    {
        $reward = $promotion->rewards->first();
        $roles = $reward instanceof PromotionReward && in_array($reward->reward_type, [
            PromotionReward::TYPE_BUY_X_GET_Y_FREE,
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT,
        ], true)
            ? [PromotionTarget::ROLE_BUY, PromotionTarget::ROLE_GET]
            : [PromotionTarget::ROLE_ELIGIBLE];

        $targets = $promotion->targets->whereIn('target_role', $roles);

        if ($targets->contains(fn (PromotionTarget $target): bool => $target->target_type === PromotionTarget::TYPE_ALL)) {
            return 'All products';
        }

        if ($targets->isEmpty()) {
            return 'Selected products';
        }

        $types = $targets->pluck('target_type')->unique()->values();

        if ($types->count() === 1) {
            return match ($types->first()) {
                PromotionTarget::TYPE_CATEGORY => 'Selected categories',
                PromotionTarget::TYPE_BRAND => 'Selected brands',
                PromotionTarget::TYPE_COLLECTION => 'Selected collections',
                PromotionTarget::TYPE_PRODUCT,
                PromotionTarget::TYPE_VARIANT => 'Selected products',
                default => 'Selected products',
            };
        }

        return 'Selected products';
    }

    /**
     * @return array<int, string>
     */
    private function supportedRewardTypes(): array
    {
        return [
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT,
            PromotionReward::TYPE_FIXED_DISCOUNT,
            PromotionReward::TYPE_FIXED_PRICE,
            PromotionReward::TYPE_QUANTITY_DISCOUNT,
            PromotionReward::TYPE_FIXED_BUNDLE_PRICE,
            PromotionReward::TYPE_BUY_X_GET_Y_FREE,
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT,
            PromotionReward::TYPE_TIER_PRICING,
            PromotionReward::TYPE_FREE_GIFT,
        ];
    }
}
