<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductReturnPolicy;
use App\Models\Shop;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;

class ProductReturnPolicyResolver
{
    public function __construct(
        private readonly ShopSettingsService $shopSettings,
        private readonly ShopSettingsInitializer $shopSettingsInitializer,
    ) {
    }

    /**
     * @return array{
     *     refund_allowed: bool,
     *     refund_window_days: int,
     *     exchange_allowed: bool,
     *     exchange_window_days: int,
     *     refund_source: string,
     *     exchange_source: string
     * }
     */
    public function resolve(Product $product): array
    {
        $shop = $this->shop($product);
        $policy = $this->productPolicy($product);
        $shopPolicy = $this->shopPolicy($shop);

        $refundAllowed = $policy?->refund_allowed ?? $shopPolicy['refund_allowed'];
        $refundWindowDays = $policy?->refund_window_days ?? $shopPolicy['refund_window_days'];
        $exchangeAllowed = $policy?->exchange_allowed ?? $shopPolicy['exchange_allowed'];
        $exchangeWindowDays = $policy?->exchange_window_days ?? $shopPolicy['exchange_window_days'];

        if (! $refundAllowed) {
            $refundWindowDays = 0;
        }

        if (! $exchangeAllowed) {
            $exchangeWindowDays = 0;
        }

        return [
            'refund_allowed' => (bool) $refundAllowed,
            'refund_window_days' => max(0, (int) $refundWindowDays),
            'exchange_allowed' => (bool) $exchangeAllowed,
            'exchange_window_days' => max(0, (int) $exchangeWindowDays),
            'refund_source' => $this->source($policy, ['refund_allowed', 'refund_window_days']),
            'exchange_source' => $this->source($policy, ['exchange_allowed', 'exchange_window_days']),
        ];
    }

    /**
     * @return array{refund_allowed: bool, refund_window_days: int, exchange_allowed: bool, exchange_window_days: int}
     */
    public function shopPolicy(Shop $shop): array
    {
        $refundAllowed = (bool) $this->shopSetting($shop, 'refund_allowed');
        $exchangeAllowed = (bool) $this->shopSetting($shop, 'exchange_allowed');

        return [
            'refund_allowed' => $refundAllowed,
            'refund_window_days' => $refundAllowed ? max(0, (int) $this->shopSetting($shop, 'refund_window_days')) : 0,
            'exchange_allowed' => $exchangeAllowed,
            'exchange_window_days' => $exchangeAllowed ? max(0, (int) $this->shopSetting($shop, 'exchange_window_days')) : 0,
        ];
    }

    private function shop(Product $product): Shop
    {
        $product->loadMissing('shop');

        return $product->shop;
    }

    private function productPolicy(Product $product): ?ProductReturnPolicy
    {
        $product->loadMissing('returnPolicy');

        return $product->returnPolicy;
    }

    private function shopSetting(Shop $shop, string $key): mixed
    {
        if ($shop->relationLoaded('settings')) {
            $setting = $shop->settings
                ->first(fn ($setting): bool => $setting->group === 'returns' && $setting->setting_key === $key);

            if ($setting !== null) {
                return $this->shopSettings->castSetting($setting);
            }
        }

        return $this->shopSettings->get(
            (int) $shop->getKey(),
            'returns',
            $key,
            $this->shopSettingDefault($key),
        );
    }

    private function shopSettingDefault(string $key): mixed
    {
        return $this->shopSettingsInitializer->defaults()['returns'][$key]['value'];
    }

    /**
     * @param array<int, string> $keys
     */
    private function source(?ProductReturnPolicy $policy, array $keys): string
    {
        if (! $policy) {
            return 'shop';
        }

        foreach ($keys as $key) {
            if ($policy->getAttribute($key) !== null) {
                return 'product';
            }
        }

        return 'shop';
    }
}
