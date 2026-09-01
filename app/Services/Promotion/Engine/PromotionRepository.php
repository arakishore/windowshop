<?php

namespace App\Services\Promotion\Engine;

use App\Models\Promotion;
use App\Models\PromotionReward;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PromotionRepository
{
    private const SUPPORTED_REWARD_TYPES = [
        PromotionReward::TYPE_PERCENTAGE_DISCOUNT,
        PromotionReward::TYPE_FIXED_DISCOUNT,
        PromotionReward::TYPE_FIXED_PRICE,
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
}
