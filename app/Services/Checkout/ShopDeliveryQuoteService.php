<?php

namespace App\Services\Checkout;

use App\Models\PostalCodeRestriction;
use App\Models\Shop;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;

class ShopDeliveryQuoteService
{
    public function __construct(
        private readonly ShopSettingsService $settings,
        private readonly ShopSettingsInitializer $initializer,
    ) {
    }

    /**
     * @return array{available: bool, charge: ?float, reason: ?string, estimated_min_days: ?int, estimated_max_days: ?int}
     */
    public function quote(Shop $shop, float|int|string $shopSubtotal, ?string $postalCode = null): array
    {
        $shopId = (int) $shop->getKey();
        $this->initializer->initialize($shopId);

        $estimateMin = $this->nullablePositiveInteger($this->settings->get($shopId, 'fulfillment', 'delivery_estimate_min_days'));
        $estimateMax = $this->nullablePositiveInteger($this->settings->get($shopId, 'fulfillment', 'delivery_estimate_max_days'));
        $base = [
            'available' => false,
            'charge' => null,
            'reason' => null,
            'estimated_min_days' => $estimateMin,
            'estimated_max_days' => $estimateMax,
        ];

        if (! (bool) $this->settings->get($shopId, 'fulfillment', 'delivery_enabled', true)) {
            return [
                ...$base,
                'reason' => 'Delivery is not enabled for this shop.',
            ];
        }

        $postalCode = trim((string) $postalCode);
        if ($postalCode === '') {
            return [
                ...$base,
                'reason' => 'Delivery postal code is required.',
            ];
        }

        $restriction = $this->restrictionFor($shop, $postalCode);
        if ($restriction instanceof PostalCodeRestriction) {
            return [
                ...$base,
                'reason' => $restriction->reason ?: 'Delivery is not available for this postal code.',
            ];
        }

        $subtotal = max(0, (float) $shopSubtotal);
        $minimum = $this->nullablePositiveMoney($this->settings->get($shopId, 'fulfillment', 'delivery_min_order_amount'));

        if ($minimum !== null && $subtotal < $minimum) {
            return [
                ...$base,
                'reason' => 'Minimum delivery order amount is not satisfied.',
            ];
        }

        $freeAbove = $this->nullablePositiveMoney($this->settings->get($shopId, 'fulfillment', 'free_delivery_above'));
        $flatCharge = max(0, (float) ($this->settings->get($shopId, 'fulfillment', 'delivery_flat_charge', 0) ?? 0));
        $charge = $freeAbove !== null && $subtotal >= $freeAbove ? 0.0 : $flatCharge;

        return [
            ...$base,
            'available' => true,
            'charge' => $charge,
            'reason' => null,
        ];
    }

    private function restrictionFor(Shop $shop, string $postalCode): ?PostalCodeRestriction
    {
        return PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->currentlyApplicable()
            ->where(function ($query) use ($shop): void {
                $query->where(fn ($query) => $query->whereNull('merchant_id')->whereNull('shop_id'))
                    ->orWhere(function ($query) use ($shop): void {
                        $query
                            ->where('merchant_id', $shop->merchant_id)
                            ->whereNull('shop_id');
                    })
                    ->orWhere(function ($query) use ($shop): void {
                        $query
                            ->where('merchant_id', $shop->merchant_id)
                            ->where('shop_id', $shop->getKey());
                    });
            })
            ->orderByDesc('shop_id')
            ->orderByDesc('merchant_id')
            ->first();
    }

    private function nullablePositiveMoney(mixed $value): ?float
    {
        if ($value === null || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return max(0, (int) $value);
    }
}
