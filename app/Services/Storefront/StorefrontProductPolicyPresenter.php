<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\ShopSettingsInitializer;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Product\ProductReturnPolicyResolver;

class StorefrontProductPolicyPresenter
{
    public function __construct(
        private readonly ProductReturnPolicyResolver $returnPolicyResolver,
        private readonly AdminSettingsService $adminSettings,
        private readonly ShopSettingsService $shopSettings,
        private readonly ShopSettingsInitializer $shopSettingsInitializer,
    ) {
    }

    /**
     * @return array{refund: string, exchange: string, lines: array<int, string>, inline: string}
     */
    public function returnExchange(Product $product): array
    {
        $policy = $this->returnPolicyResolver->resolve($product);
        $refund = $this->policyLabel('Refund', $policy['refund_allowed'], $policy['refund_window_days']);
        $exchange = $this->policyLabel('Exchange', $policy['exchange_allowed'], $policy['exchange_window_days']);

        return [
            'refund' => $refund,
            'exchange' => $exchange,
            'lines' => [$refund, $exchange],
            'inline' => $refund.' · '.$exchange,
        ];
    }

    public function deliveryScope(Product $product): string
    {
        $product->loadMissing('shop');
        $shop = $product->shop;

        if (! $shop instanceof Shop) {
            return 'Local Area Only';
        }

        $scope = (string) $this->shopSetting(
            $shop,
            'fulfillment',
            'delivery_scope',
            $this->shopSettingsInitializer->defaults()['fulfillment']['delivery_scope']['value'],
        );

        return match ($scope) {
            'nationwide' => 'Ships Across India',
            default => 'Local Area Only',
        };
    }

    public function deliveryMinimumMessage(Product $product): ?string
    {
        $amount = $this->deliveryMinimumAmount($product);

        if ($amount === null) {
            return null;
        }

        return 'Minimum delivery order: '.$this->money($amount).'.';
    }

    public function deliveryMinimumAmount(Product $product): ?float
    {
        $product->loadMissing('shop');
        $shop = $product->shop;

        if (! $shop instanceof Shop) {
            return null;
        }

        $amount = (float) ($this->shopSetting($shop, 'fulfillment', 'delivery_min_order_amount', null) ?? 0);

        return $amount > 0 ? $amount : null;
    }

    private function policyLabel(string $type, bool $allowed, int $windowDays): string
    {
        if (! $allowed) {
            return 'No '.$type;
        }

        return $type.' within '.$windowDays.' '.$this->dayLabel($windowDays);
    }

    private function dayLabel(int $windowDays): string
    {
        return $windowDays === 1 ? 'day' : 'days';
    }

    private function shopSetting(Shop $shop, string $group, string $key, mixed $default): mixed
    {
        if ($shop->relationLoaded('settings')) {
            $setting = $shop->settings
                ->first(fn ($setting): bool => $setting->group === $group && $setting->setting_key === $key);

            if ($setting !== null) {
                return $this->shopSettings->castSetting($setting);
            }
        }

        return $this->shopSettings->get((int) $shop->getKey(), $group, $key, $default);
    }

    private function money(float|int|string $amount): string
    {
        $currency = $this->adminSettings->currencyConfig();
        $formatted = number_format(
            (float) $amount,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$formatted
            : $formatted.' '.$symbol;
    }
}
