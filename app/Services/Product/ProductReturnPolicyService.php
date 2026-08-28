<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductReturnPolicy;
use App\Models\Shop;
use App\Services\Merchant\ShopSettingsService;

class ProductReturnPolicyService
{
    public function __construct(private readonly ShopSettingsService $shopSettings)
    {
    }

    /**
     * @param array{refund_allowed: ?bool, refund_window_days: ?int, exchange_allowed: ?bool, exchange_window_days: ?int} $policy
     */
    public function sync(Product $product, array $policy): ?ProductReturnPolicy
    {
        if ($this->isFullyInherited($policy)) {
            $product->returnPolicy()->delete();

            return null;
        }

        return ProductReturnPolicy::query()->updateOrCreate(
            ['product_id' => $product->getKey()],
            [
                'refund_allowed' => $policy['refund_allowed'],
                'refund_window_days' => $policy['refund_allowed'] === false ? 0 : $policy['refund_window_days'],
                'exchange_allowed' => $policy['exchange_allowed'],
                'exchange_window_days' => $policy['exchange_allowed'] === false ? 0 : $policy['exchange_window_days'],
            ],
        );
    }

    /**
     * @return array{refund_allowed: bool, refund_window_days: int, exchange_allowed: bool, exchange_window_days: int}
     */
    public function shopPolicy(Shop $shop): array
    {
        $refundAllowed = (bool) $this->shopSettings->get((int) $shop->getKey(), 'returns', 'refund_allowed', false);
        $exchangeAllowed = (bool) $this->shopSettings->get((int) $shop->getKey(), 'returns', 'exchange_allowed', true);

        return [
            'refund_allowed' => $refundAllowed,
            'refund_window_days' => $refundAllowed
                ? (int) $this->shopSettings->get((int) $shop->getKey(), 'returns', 'refund_window_days', 0)
                : 0,
            'exchange_allowed' => $exchangeAllowed,
            'exchange_window_days' => $exchangeAllowed
                ? (int) $this->shopSettings->get((int) $shop->getKey(), 'returns', 'exchange_window_days', 7)
                : 0,
        ];
    }

    /**
     * @param array{refund_allowed: ?bool, refund_window_days: ?int, exchange_allowed: ?bool, exchange_window_days: ?int} $policy
     */
    private function isFullyInherited(array $policy): bool
    {
        return $policy['refund_allowed'] === null
            && $policy['refund_window_days'] === null
            && $policy['exchange_allowed'] === null
            && $policy['exchange_window_days'] === null;
    }
}
