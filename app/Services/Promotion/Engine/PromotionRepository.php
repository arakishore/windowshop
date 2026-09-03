<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\PromotionReward;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PromotionRepository
{
    private const SUPPORTED_REWARD_TYPES = [
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

    /**
     * @return Collection<int, Promotion>
     */
    public function automaticActiveForShop(int $shopId, CarbonInterface $effectiveAt): Collection
    {
        return Promotion::query()
            ->with(['template', 'rewards', 'targets', 'conditions'])
            ->activeNow($effectiveAt)
            ->where('shop_id', $shopId)
            ->where('activation_type', Promotion::ACTIVATION_AUTOMATIC)
            ->whereHas('rewards', fn ($query) => $query->whereIn('reward_type', self::SUPPORTED_REWARD_TYPES))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isSetupComplete())
            ->values();
    }

    /**
     * @param array<int, PromotionCoupon> $coupons
     * @return Collection<int, Promotion>
     */
    public function automaticAndActivatedCouponsForShop(int $shopId, CarbonInterface $effectiveAt, array $coupons = []): Collection
    {
        $automatic = $this->automaticActiveForShop($shopId, $effectiveAt);
        $couponPromotions = collect($coupons)
            ->filter(fn (PromotionCoupon $coupon): bool => (int) $coupon->shop_id === $shopId)
            ->map(fn (PromotionCoupon $coupon): ?Promotion => $coupon->promotion)
            ->filter(fn (?Promotion $promotion): bool => $promotion instanceof Promotion
                && (int) $promotion->shop_id === $shopId
                && $promotion->activation_type === Promotion::ACTIVATION_COUPON
                && $promotion->isSetupComplete())
            ->values();

        return $automatic
            ->concat($couponPromotions)
            ->unique(fn (Promotion $promotion): int => (int) $promotion->getKey())
            ->sortBy([
                ['priority', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
