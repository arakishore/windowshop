<?php

namespace App\Services\Merchant;

use App\Models\ShopSetting;
use Illuminate\Support\Facades\DB;

class ShopSettingsInitializer
{
    public function __construct(private readonly ShopSettingsService $settings)
    {
    }

    /**
     * @return array<string, array<string, array{value: mixed, type: string}>>
     */
    public function defaults(): array
    {
        return [
            'payment' => [
                'cod_enabled' => ['value' => false, 'type' => ShopSetting::TYPE_BOOLEAN],
                'cod_min_order_amount' => ['value' => null, 'type' => ShopSetting::TYPE_DECIMAL],
                'cod_max_order_amount' => ['value' => null, 'type' => ShopSetting::TYPE_DECIMAL],
                'cash_at_shop_enabled' => ['value' => true, 'type' => ShopSetting::TYPE_BOOLEAN],
                'merchant_upi_enabled' => ['value' => false, 'type' => ShopSetting::TYPE_BOOLEAN],
                'merchant_upi_id' => ['value' => null, 'type' => ShopSetting::TYPE_STRING],
                'merchant_upi_payee_name' => ['value' => null, 'type' => ShopSetting::TYPE_STRING],
                'merchant_upi_qr_path' => ['value' => null, 'type' => ShopSetting::TYPE_STRING],
                'online_payment_enabled' => ['value' => false, 'type' => ShopSetting::TYPE_BOOLEAN],
            ],
            'fulfillment' => [
                'delivery_enabled' => ['value' => true, 'type' => ShopSetting::TYPE_BOOLEAN],
                'delivery_min_order_amount' => ['value' => null, 'type' => ShopSetting::TYPE_DECIMAL],
                'delivery_flat_charge' => ['value' => 0, 'type' => ShopSetting::TYPE_DECIMAL],
                'free_delivery_above' => ['value' => null, 'type' => ShopSetting::TYPE_DECIMAL],
                'delivery_estimate_min_days' => ['value' => null, 'type' => ShopSetting::TYPE_INTEGER],
                'delivery_estimate_max_days' => ['value' => null, 'type' => ShopSetting::TYPE_INTEGER],
                'pickup_enabled' => ['value' => true, 'type' => ShopSetting::TYPE_BOOLEAN],
                'pickup_instructions' => ['value' => null, 'type' => ShopSetting::TYPE_STRING],
            ],
        ];
    }

    public function initialize(int $shopId): void
    {
        DB::transaction(function () use ($shopId): void {
            foreach ($this->defaults() as $group => $settings) {
                foreach ($settings as $key => $definition) {
                    if (! $this->settings->has($shopId, $group, $key)) {
                        $this->settings->setTyped(
                            $shopId,
                            $group,
                            $key,
                            $definition['value'],
                            $definition['type'],
                        );
                    }
                }
            }
        });
    }

    /**
     * @param iterable<int> $shopIds
     */
    public function initializeMany(iterable $shopIds): void
    {
        foreach ($shopIds as $shopId) {
            $this->initialize((int) $shopId);
        }
    }
}
