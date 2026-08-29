<?php

namespace App\Services;

use App\Models\PostalCodeRestriction;

class PostalCodeServiceabilityService
{
    /**
     * @return array{serviceable: bool, scope: ?string, reason: ?string, restriction: ?PostalCodeRestriction}
     */
    public function check(string $postalCode, int $merchantId, int $shopId): array
    {
        $postalCode = trim($postalCode);

        $global = PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->global()
            ->currentlyApplicable()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($global instanceof PostalCodeRestriction) {
            return [
                'serviceable' => false,
                'scope' => 'global',
                'reason' => $global->reason,
                'restriction' => $global,
            ];
        }

        $merchantOrShop = PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->currentlyApplicable()
            ->where(function ($query) use ($merchantId, $shopId): void {
                $query->where(function ($query) use ($merchantId): void {
                    $query->where('merchant_id', $merchantId)->whereNull('shop_id');
                })->orWhere(function ($query) use ($merchantId, $shopId): void {
                    $query->where('merchant_id', $merchantId)->where('shop_id', $shopId);
                });
            })
            ->orderByDesc('shop_id')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($merchantOrShop instanceof PostalCodeRestriction) {
            return [
                'serviceable' => false,
                'scope' => $merchantOrShop->shop_id === null ? 'merchant' : 'shop',
                'reason' => $merchantOrShop->reason,
                'restriction' => $merchantOrShop,
            ];
        }

        return [
            'serviceable' => true,
            'scope' => null,
            'reason' => null,
            'restriction' => null,
        ];
    }
}
