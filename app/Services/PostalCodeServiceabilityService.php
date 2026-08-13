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

        $shop = PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->forShop($merchantId, $shopId)
            ->currentlyApplicable()
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($shop instanceof PostalCodeRestriction) {
            return [
                'serviceable' => false,
                'scope' => 'shop',
                'reason' => $shop->reason,
                'restriction' => $shop,
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
