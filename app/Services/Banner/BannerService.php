<?php

namespace App\Services\Banner;

use App\Enums\BannerPosition;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;

class BannerService
{
    /**
     * @return Collection<int, Banner>
     */
    public function getMarketplaceBanners(BannerPosition|string $positionCode): Collection
    {
        $position = $this->position($positionCode);

        if ($position === null || ! $position->isAdmin()) {
            return new Collection;
        }

        return Banner::query()
            ->forMarketplace()
            ->forPosition($position)
            ->currentlyVisible()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, Banner>
     */
    public function getCategoryBanners(int $categoryId, BannerPosition|string $positionCode = BannerPosition::CATEGORY_TOP): Collection
    {
        return $this->getMarketplaceBanners($positionCode);
    }

    /**
     * @return Collection<int, Banner>
     */
    public function getStoreBanners(int $shopId, BannerPosition|string $positionCode): Collection
    {
        $position = $this->position($positionCode);

        if ($position === null || ! $position->isMerchant()) {
            return new Collection;
        }

        return Banner::query()
            ->forShop($shopId)
            ->forPosition($position)
            ->currentlyVisible()
            ->ordered()
            ->get();
    }

    private function position(BannerPosition|string $position): ?BannerPosition
    {
        return $position instanceof BannerPosition ? $position : BannerPosition::tryFrom($position);
    }
}
