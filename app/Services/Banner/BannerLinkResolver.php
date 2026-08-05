<?php

namespace App\Services\Banner;

use App\Enums\BannerLinkType;
use App\Models\Banner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class BannerLinkResolver
{
    public function resolve(Banner $banner): ?string
    {
        $type = $banner->link_type instanceof BannerLinkType
            ? $banner->link_type
            : BannerLinkType::tryFrom((string) $banner->link_type);

        if ($type === null || $type === BannerLinkType::NONE || blank($banner->link_value)) {
            return null;
        }

        return match ($type) {
            BannerLinkType::CUSTOM_URL => $this->customUrl((string) $banner->link_value),
            BannerLinkType::PRODUCT => URL::to('/products/'.$banner->link_value),
            BannerLinkType::CATEGORY => URL::to('/categories/'.$banner->link_value),
            BannerLinkType::BRAND => URL::to('/brands/'.$banner->link_value),
            BannerLinkType::SHOP => URL::to('/shops/'.$banner->link_value),
            BannerLinkType::PROMOTION => null,
            BannerLinkType::NONE => null,
        };
    }

    private function customUrl(string $value): ?string
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return URL::to($value);
        }

        return null;
    }

    public function targetExists(BannerLinkType $type, ?string $value, ?int $merchantId = null, ?int $shopId = null): bool
    {
        if ($type === BannerLinkType::NONE) {
            return blank($value);
        }

        if (blank($value)) {
            return false;
        }

        if ($type === BannerLinkType::CUSTOM_URL) {
            return $this->customUrl((string) $value) !== null;
        }

        $table = match ($type) {
            BannerLinkType::PRODUCT => 'products',
            BannerLinkType::CATEGORY => 'product_categories',
            BannerLinkType::BRAND => 'brands',
            BannerLinkType::SHOP => 'shops',
            BannerLinkType::PROMOTION => 'promotions',
            BannerLinkType::CUSTOM_URL,
            BannerLinkType::NONE => null,
        };

        if ($table === null || ! Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->where('id', (int) $value)
            ->whereNull('deleted_at')
            ->when($merchantId !== null && in_array($type, [BannerLinkType::PRODUCT, BannerLinkType::SHOP], true), fn ($query) => $query->where('merchant_id', $merchantId))
            ->when($shopId !== null && $type === BannerLinkType::PRODUCT, fn ($query) => $query->where('shop_id', $shopId))
            ->exists();
    }
}
