<?php

namespace Database\Seeders\MasterData;

use App\Models\PromotionReward;
use App\Models\PromotionTemplate;
use Illuminate\Database\Seeder;

class PromotionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ($this->templates() as $index => $template) {
            PromotionTemplate::query()->updateOrCreate(
                ['code' => $template['code']],
                [
                    ...$template,
                    'sort_order' => ($index + 1) * 10,
                    'status' => PromotionTemplate::STATUS_ACTIVE,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function templates(): array
    {
        return [
            [
                'code' => 'percentage_discount',
                'name' => 'Percentage Discount',
                'description' => 'Give customers a percentage discount on eligible products.',
                'example' => '20% OFF up to Rs. 300',
                'help_text' => 'Use for seasonal offers where the discount scales with the item price.',
                'reward_type' => PromotionReward::TYPE_PERCENTAGE_DISCOUNT,
                'required_fields' => ['value_percent'],
                'configurable_fields' => ['value_percent', 'max_discount_amount'],
            ],
            [
                'code' => 'fixed_discount',
                'name' => 'Fixed Amount Discount',
                'description' => 'Give customers a fixed rupee discount on eligible products.',
                'example' => 'Rs. 500 OFF',
                'help_text' => 'Use when the discount amount should stay the same regardless of item price.',
                'reward_type' => PromotionReward::TYPE_FIXED_DISCOUNT,
                'required_fields' => ['value_amount'],
                'configurable_fields' => ['value_amount'],
            ],
            [
                'code' => 'fixed_price',
                'name' => 'Special / Fixed Price',
                'description' => 'Sell eligible products at a fixed promotional price.',
                'example' => 'Promotion price Rs. 799',
                'help_text' => 'This does not change product variant selling prices; calculation will happen in the promotion engine.',
                'reward_type' => PromotionReward::TYPE_FIXED_PRICE,
                'required_fields' => ['value_amount'],
                'configurable_fields' => ['value_amount'],
            ],
            [
                'code' => 'fixed_bundle_price',
                'name' => 'Any X Items for Rs. Y',
                'description' => 'Offer a fixed total price when customers buy a required quantity of eligible items.',
                'example' => 'Any 10 for Rs. 5,000',
                'help_text' => 'Quantity is the number of eligible items required; bundle price is the total price for that group.',
                'reward_type' => PromotionReward::TYPE_FIXED_BUNDLE_PRICE,
                'required_fields' => ['bundle_quantity', 'bundle_price'],
                'configurable_fields' => ['bundle_quantity', 'bundle_price'],
            ],
            [
                'code' => 'buy_x_get_y_free',
                'name' => 'Buy X Get Y Free',
                'description' => 'Customers buy a set quantity and receive another quantity free.',
                'example' => 'Buy 1 Get 1 Free',
                'help_text' => 'Use separate buy and get targets when the purchased and free products differ.',
                'reward_type' => PromotionReward::TYPE_BUY_X_GET_Y_FREE,
                'required_fields' => ['buy_quantity', 'get_quantity'],
                'configurable_fields' => ['buy_quantity', 'get_quantity'],
            ],
            [
                'code' => 'buy_x_get_y_discount',
                'name' => 'Buy X Get Y at Discount',
                'description' => 'Customers buy a set quantity and receive another quantity at a discount.',
                'example' => 'Buy 2 Shirts, get 1 Trouser at 50% OFF',
                'help_text' => 'Use CUSTOMER BUYS and CUSTOMER GETS targets to keep the offer easy to configure.',
                'reward_type' => PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT,
                'required_fields' => ['buy_quantity', 'get_quantity', 'value_percent'],
                'configurable_fields' => ['buy_quantity', 'get_quantity', 'value_percent'],
            ],
            [
                'code' => 'quantity_discount',
                'name' => 'Quantity Discount',
                'description' => 'Give a discount when customers buy at least a configured quantity.',
                'example' => 'Buy 3+ and get 10% OFF',
                'help_text' => 'Use percentage or fixed discount value with a minimum quantity condition.',
                'reward_type' => PromotionReward::TYPE_QUANTITY_DISCOUNT,
                'required_fields' => ['minimum_quantity'],
                'configurable_fields' => ['minimum_quantity', 'value_type', 'value_amount', 'value_percent'],
            ],
            [
                'code' => 'tier_pricing',
                'name' => 'Tier / Bulk Pricing',
                'description' => 'Set per-item prices for quantity tiers.',
                'example' => '1 = Rs. 500 each, 3+ = Rs. 450 each, 10+ = Rs. 400 each',
                'help_text' => 'Define tiers as minimum quantity and unit price pairs.',
                'reward_type' => PromotionReward::TYPE_TIER_PRICING,
                'required_fields' => ['tier_config'],
                'configurable_fields' => ['tier_config'],
            ],
            [
                'code' => 'free_gift',
                'name' => 'Free Gift',
                'description' => 'Give a free product when customers meet the eligible purchase condition.',
                'example' => 'Spend Rs. 2,000 and get Product X free',
                'help_text' => 'The gift product is stored as a gift target; subtotal threshold is stored as a condition.',
                'reward_type' => PromotionReward::TYPE_FREE_GIFT,
                'required_fields' => ['minimum_eligible_subtotal', 'gift_target'],
                'configurable_fields' => ['minimum_eligible_subtotal', 'gift_product_ids'],
            ],
        ];
    }
}
