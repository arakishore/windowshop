<?php

namespace App\Services\Merchant;

use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerchantShopContextService
{
    public function activeMerchantForUser(User $user): ?MerchantProfile
    {
        return $user->merchantProfile()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('verification_status', '!=', 'suspended')
            ->first();
    }

    public function merchantRoleId(): ?int
    {
        $roleId = DB::table('auth_roles')
            ->where('slug', 'merchant')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');

        return $roleId === null ? null : (int) $roleId;
    }

    /**
     * @return Collection<int, Shop>
     */
    public function activeShops(MerchantProfile $merchant): Collection
    {
        return $merchant->shops()
            ->with('city')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    public function resolveActiveShop(Collection $shops, mixed $sessionShopId): ?Shop
    {
        if ($shops->isEmpty()) {
            return null;
        }

        $activeShop = $shops->firstWhere('id', (int) $sessionShopId);

        return $activeShop instanceof Shop ? $activeShop : $shops->first();
    }

    public function label(?Shop $shop): string
    {
        if ($shop === null) {
            return 'No active shop';
        }

        return $shop->name.($shop->city?->name ? ' - '.$shop->city->name : '');
    }
}
