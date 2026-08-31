<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShopPromotionStarterService
{
    public function createMissingSystemStartersForAllShops(): int
    {
        if (! $this->hasRequiredTables()) {
            return 0;
        }

        $created = 0;

        Shop::query()
            ->whereNull('deleted_at')
            ->each(function (Shop $shop) use (&$created): void {
                $created += $this->createMissingSystemStartersForShop($shop);
            });

        return $created;
    }

    public function createMissingSystemStartersForShop(Shop $shop): int
    {
        if (! $this->hasRequiredTables()) {
            return 0;
        }

        $created = 0;
        $templates = PromotionTemplate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($templates as $template) {
            $exists = Promotion::withTrashed()
                ->where('shop_id', $shop->getKey())
                ->where('promotion_template_id', $template->getKey())
                ->where('origin', Promotion::ORIGIN_SYSTEM)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::transaction(function () use ($shop, $template): void {
                $defaults = $this->starterDefaults($template);

                $promotion = Promotion::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'merchant_id' => $shop->merchant_id,
                    'shop_id' => $shop->getKey(),
                    'promotion_template_id' => $template->getKey(),
                    'name' => $defaults['name'],
                    'slug' => $this->uniqueSlug($shop, $defaults['name']),
                    'description' => $defaults['description'] ?? null,
                    'status' => Promotion::STATUS_INACTIVE,
                    'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
                    'origin' => Promotion::ORIGIN_SYSTEM,
                    'starts_at' => null,
                    'ends_at' => null,
                    'is_combinable' => false,
                    'priority' => 0,
                    'new_customer_only' => false,
                    'refund_policy_mode' => Promotion::POLICY_INHERIT,
                    'exchange_policy_mode' => Promotion::POLICY_INHERIT,
                ]);

                $promotion->rewards()->create([
                    'reward_type' => $template->reward_type,
                    ...($defaults['reward'] ?? []),
                ]);

                foreach ($defaults['conditions'] ?? [] as $condition) {
                    $promotion->conditions()->create($condition);
                }

                foreach ($defaults['targets'] ?? [] as $target) {
                    $promotion->targets()->create($target);
                }
            });

            $created++;
        }

        return $created;
    }

    private function starterDefaults(PromotionTemplate $template): array
    {
        return match ($template->reward_type) {
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT => [
                'name' => '10% OFF All Products',
                'reward' => ['value_percent' => '10.00'],
                'targets' => [$this->allEligibleTarget()],
            ],
            PromotionReward::TYPE_FIXED_DISCOUNT => [
                'name' => 'Rs. 100 OFF All Products',
                'reward' => ['value_amount' => '100.00'],
                'targets' => [$this->allEligibleTarget()],
            ],
            PromotionReward::TYPE_FIXED_PRICE => [
                'name' => 'Special Price Offer',
                'reward' => [],
                'targets' => [$this->allEligibleTarget()],
            ],
            PromotionReward::TYPE_FIXED_BUNDLE_PRICE => [
                'name' => 'Any X Items for Rs. Y',
                'reward' => [],
                'targets' => [$this->allEligibleTarget()],
            ],
            PromotionReward::TYPE_BUY_X_GET_Y_FREE => [
                'name' => 'Buy 1 Get 1 Free',
                'reward' => [
                    'buy_quantity' => 1,
                    'get_quantity' => 1,
                ],
            ],
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => [
                'name' => 'Buy X Get Y at Discount',
                'reward' => [],
            ],
            PromotionReward::TYPE_QUANTITY_DISCOUNT => [
                'name' => 'Buy 3+ Get 10% OFF',
                'reward' => [
                    'value_type' => 'percent',
                    'value_percent' => '10.00',
                ],
                'conditions' => [[
                    'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
                    'operator' => '>=',
                    'value_numeric' => '3.00',
                    'sort_order' => 10,
                ]],
                'targets' => [$this->allEligibleTarget()],
            ],
            PromotionReward::TYPE_TIER_PRICING => [
                'name' => 'Tier / Bulk Pricing',
                'reward' => [],
            ],
            PromotionReward::TYPE_FREE_GIFT => [
                'name' => 'Free Gift Offer',
                'reward' => [],
            ],
            default => [
                'name' => $template->name,
                'reward' => [],
            ],
        };
    }

    private function allEligibleTarget(): array
    {
        return [
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
            'sort_order' => 10,
        ];
    }

    private function uniqueSlug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'starter-offer';
        $slug = $base;
        $suffix = 2;

        while (Promotion::withTrashed()
            ->where('shop_id', $shop->getKey())
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function hasRequiredTables(): bool
    {
        foreach (['shops', 'promotion_templates', 'promotions', 'promotion_rewards', 'promotion_targets', 'promotion_conditions'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return Schema::hasColumn('promotions', 'origin');
    }
}
