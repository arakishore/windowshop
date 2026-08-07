<?php

namespace App\Services\Banner;

use App\Models\Banner;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Services\System\SystemSettingService;

class BannerLimitService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function limitPerShop(): int
    {
        return $this->settings->merchantBannerLimitPerShop();
    }

    public function usedSlots(MerchantProfile $merchant, Shop $shop, bool $lock = false): int
    {
        $query = Banner::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('shop_id', $shop->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->count();
    }

    public function remainingSlots(MerchantProfile $merchant, Shop $shop): int
    {
        return max(0, $this->limitPerShop() - $this->usedSlots($merchant, $shop));
    }

    public function canCreate(MerchantProfile $merchant, Shop $shop): bool
    {
        return $this->usedSlots($merchant, $shop) < $this->limitPerShop();
    }
}
