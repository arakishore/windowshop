<?php

namespace App\Services\Merchant;

use App\Models\MerchantSetting;
use Illuminate\Support\Facades\DB;

class MerchantSettingsInitializer
{
    public function __construct(private readonly MerchantSettingsService $settings)
    {
    }

    /**
     * @return array<string, array<string, array{value: mixed, type: string}>>
     */
    public function defaults(): array
    {
        return [
            'pos' => [
                'cash_rounding.method' => ['value' => 'nearest', 'type' => MerchantSetting::TYPE_STRING],
                'cash_rounding.apply_to' => ['value' => 'cash', 'type' => MerchantSetting::TYPE_STRING],
                'product.tile_size' => ['value' => 'spacious', 'type' => MerchantSetting::TYPE_STRING],
                'cart.play_add_sound' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_shop_name' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_address' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_phone' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_gst_number' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_customer' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_cashier' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_order_number' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_barcode' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_qr_code' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.show_tax_breakdown' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.line_item.show_sku' => ['value' => false, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.line_item.show_hsn_code' => ['value' => false, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.line_item.show_hsn_summary' => ['value' => false, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'receipt.footer' => ['value' => 'Thank you for shopping with us.', 'type' => MerchantSetting::TYPE_STRING],
                'receipt.return_policy' => ['value' => '', 'type' => MerchantSetting::TYPE_STRING],
                'held_order.expiry_days' => ['value' => 30, 'type' => MerchantSetting::TYPE_INTEGER],
                'order.allow_order_discount' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'order.allow_item_discount' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
            ],
            'inventory' => [
                'allow_negative_stock' => ['value' => false, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'show_low_stock_warning' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'low_stock_default' => ['value' => 5, 'type' => MerchantSetting::TYPE_INTEGER],
            ],
            'product' => [
                'barcode.auto_generate' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
            ],
            'payment' => [
                'default_payment_method' => ['value' => 'cash', 'type' => MerchantSetting::TYPE_STRING],
                'allow_cash' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'allow_upi' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'allow_card' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
                'allow_credit' => ['value' => true, 'type' => MerchantSetting::TYPE_BOOLEAN],
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function obsoleteSettings(): array
    {
        return [
            'pos' => [
                'cart.auto_clear_after_sale',
                'cash_rounding.enabled',
                'cash_rounding.precision',
                'product.search.mode',
                'receipt.show_logo',
                'receipt.header_text',
            ],
            'order' => [
                'allow_item_discount',
                'allow_order_discount',
                'default_status',
            ],
            'product' => [
                'barcode.type',
                'default_visibility',
            ],
            'payment' => [
                'allow_bank_transfer',
            ],
        ];
    }

    public function initialize(int $merchantId): void
    {
        DB::transaction(function () use ($merchantId): void {
            $this->removeObsolete($merchantId);

            foreach ($this->defaults() as $group => $settings) {
                foreach ($settings as $key => $definition) {
                    if (! $this->settings->has($merchantId, $group, $key)) {
                        $this->settings->setTyped(
                            $merchantId,
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
     * @param iterable<int> $merchantIds
     */
    public function initializeMany(iterable $merchantIds): void
    {
        foreach ($merchantIds as $merchantId) {
            $this->initialize((int) $merchantId);
        }
    }

    public function removeObsolete(int $merchantId): void
    {
        foreach ($this->obsoleteSettings() as $group => $keys) {
            MerchantSetting::query()
                ->where('merchant_id', $merchantId)
                ->where('group', $group)
                ->whereIn('setting_key', $keys)
                ->delete();
        }
    }
}
