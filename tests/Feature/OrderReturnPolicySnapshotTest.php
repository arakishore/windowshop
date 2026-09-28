<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionCoupon;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\ProductReturnPolicy;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use App\Services\Checkout\StorefrontCheckoutOrderService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Order\OrderCreationService;
use App\Services\Order\OrderExchangeService;
use App\Services\Order\OrderRefundService;
use App\Services\Order\OrderReturnExchangeEligibilityService;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use ReflectionMethod;
use Tests\TestCase;

class OrderReturnPolicySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_storefront_order_snapshots_effective_shop_policy(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, true, 5, true, 7);

        $item = $this->createOrder($user, $shop, $variant, [
            'created_source' => Order::SOURCE_STOREFRONT,
        ])->items->first();

        $this->assertTrue($item->refund_allowed);
        $this->assertSame(5, $item->refund_window_days);
        $this->assertTrue($item->exchange_allowed);
        $this->assertSame(7, $item->exchange_window_days);
    }

    public function test_storefront_order_snapshots_product_override_and_partial_inheritance(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, false, 0, true, 7);
        ProductReturnPolicy::query()->create([
            'product_id' => $variant->product_id,
            'refund_allowed' => true,
            'refund_window_days' => 3,
            'exchange_allowed' => null,
            'exchange_window_days' => null,
        ]);

        $item = $this->createOrder($user, $shop, $variant, [
            'created_source' => Order::SOURCE_STOREFRONT,
        ])->items->first();

        $this->assertTrue($item->refund_allowed);
        $this->assertSame(3, $item->refund_window_days);
        $this->assertTrue($item->exchange_allowed);
        $this->assertSame(7, $item->exchange_window_days);
    }

    public function test_explicit_false_policy_snapshots_zero_windows(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, true, 10, true, 8);
        ProductReturnPolicy::query()->create([
            'product_id' => $variant->product_id,
            'refund_allowed' => false,
            'refund_window_days' => 10,
            'exchange_allowed' => false,
            'exchange_window_days' => 8,
        ]);

        $item = $this->createOrder($user, $shop, $variant)->items->first();

        $this->assertFalse($item->refund_allowed);
        $this->assertSame(0, $item->refund_window_days);
        $this->assertFalse($item->exchange_allowed);
        $this->assertSame(0, $item->exchange_window_days);
    }

    public function test_pos_order_receives_same_policy_snapshot_behavior(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, true, 4, false, 0);

        $item = $this->createOrder($user, $shop, $variant, [
            'created_source' => Order::SOURCE_POS,
        ])->items->first();

        $this->assertSame(Order::SOURCE_POS, $item->order->created_source);
        $this->assertTrue($item->refund_allowed);
        $this->assertSame(4, $item->refund_window_days);
        $this->assertFalse($item->exchange_allowed);
        $this->assertSame(0, $item->exchange_window_days);
    }

    public function test_policy_changes_after_purchase_do_not_mutate_existing_snapshot_but_new_orders_use_new_policy(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, false, 0, true, 7);

        $oldItem = $this->createOrder($user, $shop, $variant)->items->first();

        $this->setShopPolicy($shop, true, 2, true, 3);
        ProductReturnPolicy::query()->create([
            'product_id' => $variant->product_id,
            'refund_allowed' => true,
            'refund_window_days' => 6,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ]);

        $oldItem->refresh();
        $newItem = $this->createOrder($user, $shop, $variant)->items->first();

        $this->assertFalse($oldItem->refund_allowed);
        $this->assertSame(0, $oldItem->refund_window_days);
        $this->assertTrue($oldItem->exchange_allowed);
        $this->assertSame(7, $oldItem->exchange_window_days);

        $this->assertTrue($newItem->refund_allowed);
        $this->assertSame(6, $newItem->refund_window_days);
        $this->assertFalse($newItem->exchange_allowed);
        $this->assertSame(0, $newItem->exchange_window_days);
    }

    public function test_promotion_refund_and_exchange_policy_override_is_snapshotted_after_promotion_edit(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));

        try {
            [$user, $shop, $variant] = $this->fixture();
            $this->setShopPolicy($shop, false, 0, true, 7);
            $promotion = $this->promotion($shop, 'fixed_discount', [
                'status' => Promotion::STATUS_ACTIVE,
                'refund_policy_mode' => Promotion::POLICY_ALLOWED,
                'refund_window_days' => 3,
                'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
                'exchange_window_days' => 4,
            ], ['value_amount' => '10.00']);
            $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $variant->product_id);

            $order = $this->createOrder($user, $shop, $variant, [
                'created_source' => Order::SOURCE_STOREFRONT,
            ]);
            $item = $order->items->first();

            $promotion->forceFill([
                'refund_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
                'refund_window_days' => null,
                'exchange_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
                'exchange_window_days' => null,
            ])->save();

            $freshItem = $item->fresh();
            $eligibility = app(OrderReturnExchangeEligibilityService::class)
                ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-09-03 10:00:00'));
            $facts = $eligibility['items'][$freshItem->getKey()];

            $this->assertTrue($freshItem->refund_allowed);
            $this->assertSame(3, $freshItem->refund_window_days);
            $this->assertTrue($freshItem->exchange_allowed);
            $this->assertSame(4, $freshItem->exchange_window_days);
            $this->assertSame('promotion', $freshItem->metadata['return_exchange_policy']['refund_source']);
            $this->assertSame('promotion', $freshItem->metadata['return_exchange_policy']['exchange_source']);
            $this->assertTrue($facts['refund']['customer_eligible']);
            $this->assertTrue($facts['exchange']['customer_eligible']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_participating_item_does_not_inherit_promotion_policy_override(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();
        $this->setShopPolicy($shop, true, 10, true, 10);
        $participating = $this->variant($this->product($merchant, $shop, $root, $category, 'Participating Shirt'), 'Default', 150);
        $regular = $this->variant($this->product($merchant, $shop, $root, $category, 'Regular Shirt'), 'Default', 150);
        $promotion = $this->promotion($shop, 'fixed_discount', [
            'status' => Promotion::STATUS_ACTIVE,
            'refund_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
            'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
            'exchange_window_days' => 2,
        ], ['value_amount' => '10.00']);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $participating->product_id);

        $order = $this->createOrder($user, $shop, $participating, [
            'created_source' => Order::SOURCE_STOREFRONT,
            'items' => [
                ['product_variant_id' => $participating->getKey(), 'quantity' => 1],
                ['product_variant_id' => $regular->getKey(), 'quantity' => 1],
            ],
        ]);

        $participatingItem = $order->items->firstWhere('product_variant_id', $participating->getKey());
        $regularItem = $order->items->firstWhere('product_variant_id', $regular->getKey());

        $this->assertFalse($participatingItem->refund_allowed);
        $this->assertSame(0, $participatingItem->refund_window_days);
        $this->assertTrue($participatingItem->exchange_allowed);
        $this->assertSame(2, $participatingItem->exchange_window_days);
        $this->assertTrue($regularItem->refund_allowed);
        $this->assertSame(10, $regularItem->refund_window_days);
        $this->assertTrue($regularItem->exchange_allowed);
        $this->assertSame(10, $regularItem->exchange_window_days);
        $this->assertNull($regularItem->metadata);
    }

    public function test_coupon_promotion_policy_is_snapshotted(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, true, 10, true, 10);
        $coupon = $this->couponPromotion($shop, 'fixed_discount', 'RETURNS10', [
            'refund_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
            'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
            'exchange_window_days' => 5,
        ], ['value_amount' => '10.00']);

        $order = $this->createOrder($user, $shop, $variant, [
            'created_source' => Order::SOURCE_STOREFRONT,
            'applied_coupon_code' => 'RETURNS10',
        ]);
        $item = $order->items->first();

        $this->assertFalse($item->refund_allowed);
        $this->assertSame(0, $item->refund_window_days);
        $this->assertTrue($item->exchange_allowed);
        $this->assertSame(5, $item->exchange_window_days);
        $this->assertSame(Promotion::ACTIVATION_COUPON, $item->metadata['promotion']['activation_type']);
        $this->assertSame($coupon->getKey(), $item->metadata['promotion']['coupon_id']);
        $this->assertSame('RETURNS10', $item->metadata['promotion']['coupon_code']);
    }

    public function test_bogo_buy_get_history_survives_promotion_changes(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();
        $buy = $this->variant($this->product($merchant, $shop, $root, $category, 'Buy Shirt'), 'Default', 200);
        $get = $this->variant($this->product($merchant, $shop, $root, $category, 'Get Shirt'), 'Default', 150);
        $promotion = $this->promotion($shop, 'buy_x_get_y_free', [
            'status' => Promotion::STATUS_ACTIVE,
            'refund_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
            'exchange_policy_mode' => Promotion::POLICY_ALLOWED,
            'exchange_window_days' => 9,
        ], ['buy_quantity' => 1, 'get_quantity' => 1]);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $buy->product_id, PromotionTarget::ROLE_BUY);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $get->product_id, PromotionTarget::ROLE_GET);

        $order = $this->createOrder($user, $shop, $buy, [
            'created_source' => Order::SOURCE_STOREFRONT,
            'items' => [
                ['product_variant_id' => $buy->getKey(), 'quantity' => 1],
                ['product_variant_id' => $get->getKey(), 'quantity' => 1],
            ],
        ]);
        $promotion->forceFill(['name' => 'Edited BOGO'])->save();

        $buyItem = $order->items->firstWhere('product_variant_id', $buy->getKey())->fresh();
        $getItem = $order->items->firstWhere('product_variant_id', $get->getKey())->fresh();

        $this->assertSame($promotion->getKey(), $buyItem->metadata['promotion']['id']);
        $this->assertSame(['buy'], $buyItem->metadata['promotion']['details']['roles']);
        $this->assertSame(['get'], $getItem->metadata['promotion']['details']['roles']);
        $this->assertSame(1, $buyItem->metadata['promotion']['details']['participating_buy_quantity']);
        $this->assertSame(1, $getItem->metadata['promotion']['details']['rewarded_get_quantity']);
        $this->assertFalse($buyItem->refund_allowed);
        $this->assertTrue($getItem->exchange_allowed);
        $this->assertSame(9, $getItem->exchange_window_days);
    }

    public function test_free_gift_dependency_history_survives_promotion_changes(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();
        $qualifying = $this->variant($this->product($merchant, $shop, $root, $category, 'Qualifying Shirt'), 'Default', 250);
        $gift = $this->variant($this->product($merchant, $shop, $root, $category, 'Gift Socks'), 'Default', 75);
        $promotion = $this->promotion($shop, 'free_gift', [
            'status' => Promotion::STATUS_ACTIVE,
            'refund_policy_mode' => Promotion::POLICY_ALLOWED,
            'refund_window_days' => 1,
            'exchange_policy_mode' => Promotion::POLICY_NOT_ALLOWED,
        ]);
        $this->target($promotion, PromotionTarget::TYPE_PRODUCT, $qualifying->product_id);
        $this->target($promotion, PromotionTarget::TYPE_VARIANT, $gift->getKey(), PromotionTarget::ROLE_GIFT);
        $this->condition($promotion, PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL, '200.00');

        $order = $this->createOrder($user, $shop, $qualifying, [
            'created_source' => Order::SOURCE_STOREFRONT,
        ]);
        $promotion->conditions()->delete();

        $qualifyingItem = $order->items->firstWhere('product_variant_id', $qualifying->getKey())->fresh();
        $giftItem = $order->items->firstWhere('product_variant_id', $gift->getKey())->fresh();

        $this->assertNotNull($giftItem);
        $this->assertSame('qualifying', $qualifyingItem->metadata['promotion']['details']['role']);
        $this->assertSame($gift->getKey(), $qualifyingItem->metadata['promotion']['details']['gift_variant_id']);
        $this->assertSame('gift', $giftItem->metadata['promotion']['details']['role']);
        $this->assertSame($qualifying->getKey(), $giftItem->metadata['promotion']['details']['qualifying_lines'][0]['variant_id']);
        $this->assertTrue($giftItem->refund_allowed);
        $this->assertSame(1, $giftItem->refund_window_days);
        $this->assertFalse($giftItem->exchange_allowed);
    }

    public function test_product_price_changes_do_not_affect_historical_refund_values(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $this->setShopPolicy($shop, true, 7, true, 7);
        $order = $this->createOrder($user, $shop, $variant, ['quantity' => 2])->load('items');
        $item = $order->items->first();
        $variant->forceFill(['selling_price' => 999])->save();
        $reason = $this->returnReason($order);

        $refund = app(OrderRefundService::class)->create($order, [
            'return_reason_id' => $reason->getKey(),
            'refund_method' => Order::PAYMENT_METHOD_CASH,
            'items' => [
                $item->getKey() => ['quantity' => 1, 'do_not_restock' => true],
            ],
        ], $user);
        $refundItem = $refund->items->first();

        $this->assertSame('150.00', $refundItem->unit_price);
        $this->assertSame('150.00', $refundItem->line_total);
        $this->assertSame('150.00', $refund->refund_subtotal);
        $this->assertSame('150.00', $refund->refund_total);
    }

    public function test_exchange_replacement_preserves_original_policy_snapshot_after_policy_change(): void
    {
        [$user, $shop, $originalVariant, $replacementVariant] = $this->fixtureWithReplacement();
        $this->setShopPolicy($shop, false, 0, true, 7);
        $order = $this->createOrder($user, $shop, $originalVariant, ['quantity' => 1]);
        $originalItem = $order->items->first();

        $this->setShopPolicy($shop, false, 0, true, 3);

        $exchange = app(OrderExchangeService::class)->create($order, [
            'settlement_method' => Order::PAYMENT_METHOD_CASH,
            'returned_items' => [
                $originalItem->getKey() => ['quantity' => 1],
            ],
            'replacement_items' => [
                ['product_variant_id' => $replacementVariant->getKey(), 'quantity' => 1],
            ],
        ], $user);

        $replacementItem = $exchange->replacementOrder->items->first();

        $this->assertFalse($replacementItem->refund_allowed);
        $this->assertSame(0, $replacementItem->refund_window_days);
        $this->assertTrue($replacementItem->exchange_allowed);
        $this->assertSame(7, $replacementItem->exchange_window_days);
    }

    public function test_exchange_rejects_ambiguous_replacement_policy_when_returned_items_have_different_snapshots(): void
    {
        [$user, $shop, $firstVariant, $secondVariant, $replacementVariant] = $this->fixtureWithTwoOriginals();
        $order = $this->createOrder($user, $shop, $firstVariant, [
            'items' => [
                [
                    'product_variant_id' => $firstVariant->getKey(),
                    'quantity' => 1,
                    'policy_snapshot' => ['refund_allowed' => false, 'refund_window_days' => 0, 'exchange_allowed' => true, 'exchange_window_days' => 7],
                ],
                [
                    'product_variant_id' => $secondVariant->getKey(),
                    'quantity' => 1,
                    'policy_snapshot' => ['refund_allowed' => false, 'refund_window_days' => 0, 'exchange_allowed' => true, 'exchange_window_days' => 3],
                ],
            ],
        ]);

        $this->expectException(ValidationException::class);

        app(OrderExchangeService::class)->create($order, [
            'settlement_method' => Order::PAYMENT_METHOD_CASH,
            'returned_items' => $order->items
                ->mapWithKeys(fn (OrderItem $item): array => [$item->getKey() => ['quantity' => 1]])
                ->all(),
            'replacement_items' => [
                ['product_variant_id' => $replacementVariant->getKey(), 'quantity' => 1],
            ],
        ], $user);
    }

    public function test_storefront_checkout_item_mapping_does_not_accept_policy_snapshot_input(): void
    {
        $method = new ReflectionMethod(StorefrontCheckoutOrderService::class, 'orderItems');
        $method->setAccessible(true);

        $rows = $method->invoke(app(StorefrontCheckoutOrderService::class), [[
            'product_variant_id' => 123,
            'quantity_value' => 2,
            'policy_snapshot' => ['refund_allowed' => true, 'refund_window_days' => 99, 'exchange_allowed' => true, 'exchange_window_days' => 99],
        ]]);

        $this->assertSame([['product_variant_id' => 123, 'quantity' => 2]], $rows);
    }

    public function test_refundable_quantity_subtracts_previous_refunds_and_completed_exchanges(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $order = $this->createOrder($user, $shop, $variant, ['quantity' => 3]);
        $item = $order->items->first();
        $refund = $this->refund($order);
        $refund->items()->create([
            'order_item_id' => $item->getKey(),
            'product_variant_id' => $item->product_variant_id,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
            'line_tax' => '0.00',
            'line_total' => '150.00',
            'restocked' => true,
        ]);
        $exchange = $this->exchange($order);
        $exchange->items()->create([
            'order_item_id' => $item->getKey(),
            'product_variant_id' => $item->product_variant_id,
            'quantity' => 1,
            'unit_return_value' => $item->unit_price,
            'line_tax' => '0.00',
            'line_total' => '150.00',
            'restocked' => true,
        ]);

        $this->assertSame([
            $item->getKey() => 1,
        ], app(OrderRefundService::class)->refundableQuantities($order->load('items')));
        $this->assertSame([
            $item->getKey() => 1,
        ], app(OrderExchangeService::class)->exchangeableQuantities($order->load('items')));
    }

    public function test_cod_delivery_policy_eligibility_uses_order_item_snapshot_and_delivered_start_date(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'quantity' => 2,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt->copy()->addHour(),
        ]);
        $item = $order->items->first();
        $item->forceFill([
            'refund_allowed' => false,
            'refund_window_days' => 0,
            'exchange_allowed' => true,
            'exchange_window_days' => 7,
        ])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);

        $this->setShopPolicy($shop, true, 30, false, 0);
        ProductReturnPolicy::query()->create([
            'product_id' => $variant->product_id,
            'refund_allowed' => true,
            'refund_window_days' => 30,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ]);

        $eligibility = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-25 10:00:00'));
        $facts = $eligibility['items'][$item->getKey()];

        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_REFUND_NOT_ALLOWED, $facts['refund']['reason_code']);
        $this->assertFalse($facts['refund']['customer_eligible']);
        $this->assertNull($facts['refund']['window_expires_at']);
        $this->assertNull($facts['refund']['window_expires_label']);
        $this->assertFalse($facts['refund']['window_expired']);
        $this->assertSame(0, $facts['refund']['expired_by_days']);
        $this->assertSame('Exchange within 7 days', $facts['exchange']['policy_label']);
        $this->assertTrue($facts['exchange']['customer_eligible']);
        $this->assertNotNull($facts['exchange']['window_expires_at']);
        $this->assertSame($deliveredAt->toDateTimeString(), $eligibility['order']['eligibility_starts_at']->toDateTimeString());
        $this->assertTrue($eligibility['return_method']['shop_visit_allowed']);
        $this->assertFalse($eligibility['return_method']['pickup_allowed']);
        $this->assertTrue($eligibility['return_method']['pickup_policy_eligible']);
    }

    public function test_refund_and_exchange_deadlines_are_calculated_independently_when_both_are_allowed(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt,
        ]);
        $item = $order->items->first();
        $item->forceFill([
            'refund_allowed' => true,
            'refund_window_days' => 7,
            'exchange_allowed' => true,
            'exchange_window_days' => 7,
        ])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);

        $facts = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-25 10:00:00'))['items'][$item->getKey()];

        $this->assertTrue($facts['refund']['customer_eligible']);
        $this->assertTrue($facts['exchange']['customer_eligible']);
        $this->assertSame($deliveredAt->copy()->addDays(7)->toDateTimeString(), $facts['refund']['window_expires_at']->toDateTimeString());
        $this->assertSame($deliveredAt->copy()->addDays(7)->toDateTimeString(), $facts['exchange']['window_expires_at']->toDateTimeString());
    }

    public function test_allowed_refund_can_expire_without_affecting_exchange(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt,
        ]);
        $item = $order->items->first();
        $item->forceFill([
            'refund_allowed' => true,
            'refund_window_days' => 3,
            'exchange_allowed' => true,
            'exchange_window_days' => 15,
        ])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);

        $facts = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-25 10:00:00'))['items'][$item->getKey()];

        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_WINDOW_EXPIRED, $facts['refund']['reason_code']);
        $this->assertSame(2, $facts['refund']['expired_by_days']);
        $this->assertFalse($facts['refund']['customer_eligible']);
        $this->assertTrue($facts['exchange']['customer_eligible']);
        $this->assertFalse($facts['exchange']['window_expired']);
    }

    public function test_no_refund_and_no_exchange_do_not_create_deadlines_or_expiry(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt,
        ]);
        $item = $order->items->first();
        $item->forceFill([
            'refund_allowed' => false,
            'refund_window_days' => 0,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);

        $facts = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-25 10:00:00'))['items'][$item->getKey()];

        foreach (['refund', 'exchange'] as $type) {
            $this->assertFalse($facts[$type]['customer_eligible']);
            $this->assertNull($facts[$type]['window_expires_at']);
            $this->assertNull($facts[$type]['window_expires_label']);
            $this->assertFalse($facts[$type]['window_expired']);
            $this->assertSame(0, $facts[$type]['expired_by_days']);
        }
    }

    public function test_expired_cod_delivery_window_blocks_customer_self_service_but_keeps_shop_visit_guidance(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt,
        ]);
        $item = $order->items->first();
        $item->forceFill(['exchange_allowed' => true, 'exchange_window_days' => 7])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);

        $eligibility = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-29 10:00:00'));
        $exchange = $eligibility['items'][$item->getKey()]['exchange'];

        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_WINDOW_EXPIRED, $exchange['reason_code']);
        $this->assertSame(2, $exchange['expired_by_days']);
        $this->assertFalse($exchange['customer_eligible']);
        $this->assertTrue($eligibility['return_method']['shop_visit_allowed']);
        $this->assertFalse($eligibility['return_method']['pickup_allowed']);
        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_PICKUP_NOT_ALLOWED_EXPIRED, $eligibility['return_method']['pickup_reason']);
    }

    public function test_cash_at_shop_uses_pickup_completion_start_date_and_never_allows_customer_pickup_method(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $collectedAt = Carbon::parse('2026-08-25 12:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $collectedAt,
        ]);
        $item = $order->items->first();
        $item->forceFill(['exchange_allowed' => true, 'exchange_window_days' => 7])->save();
        $this->history($order, Order::STATUS_READY_FOR_PICKUP, Order::STATUS_COMPLETED, $collectedAt, [
            'action' => 'merchant_complete_pickup',
        ]);

        $eligibility = app(OrderReturnExchangeEligibilityService::class)
            ->forOrder($order->fresh(['items', 'statusHistories']), Carbon::parse('2026-08-27 12:00:00'));

        $this->assertTrue($eligibility['items'][$item->getKey()]['exchange']['customer_eligible']);
        $this->assertSame($collectedAt->toDateTimeString(), $eligibility['order']['eligibility_starts_at']->toDateTimeString());
        $this->assertTrue($eligibility['return_method']['shop_visit_allowed']);
        $this->assertFalse($eligibility['return_method']['pickup_allowed']);
        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_PICKUP_NOT_ALLOWED_CASH_AT_SHOP, $eligibility['return_method']['pickup_reason']);
    }

    public function test_no_remaining_quantity_blocks_customer_self_service_and_merchant_override_detects_policy_exceptions(): void
    {
        [$user, $shop, $variant] = $this->fixture();
        $deliveredAt = Carbon::parse('2026-08-20 10:00:00');
        $order = $this->createOrder($user, $shop, $variant, [
            'quantity' => 1,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'order_status' => Order::STATUS_COMPLETED,
            'completed_at' => $deliveredAt,
        ]);
        $item = $order->items->first();
        $item->forceFill([
            'refund_allowed' => true,
            'refund_window_days' => 7,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ])->save();
        $this->history($order, OrderStatus::CODE_OUT_FOR_DELIVERY, OrderStatus::CODE_DELIVERED, $deliveredAt);
        $refund = $this->refund($order);
        $refund->items()->create([
            'order_item_id' => $item->getKey(),
            'product_variant_id' => $item->product_variant_id,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
            'line_tax' => '0.00',
            'line_total' => '150.00',
            'restocked' => true,
        ]);

        $service = app(OrderReturnExchangeEligibilityService::class);
        Carbon::setTestNow(Carbon::parse('2026-08-22 10:00:00'));
        $eligibility = $service->forOrder($order->fresh(['items', 'statusHistories']));

        $this->assertSame(OrderReturnExchangeEligibilityService::REASON_NO_REMAINING_QUANTITY, $eligibility['items'][$item->getKey()]['refund']['reason_code']);
        $this->assertFalse($eligibility['items'][$item->getKey()]['refund']['customer_eligible']);
        $this->assertFalse($service->merchantOverrideRequiredForSelected($order->fresh(['items', 'statusHistories']), 'refund', [
            $item->getKey() => ['quantity' => 1],
        ]));
        $this->assertTrue($service->merchantOverrideRequiredForSelected($order->fresh(['items', 'statusHistories']), 'exchange', [
            $item->getKey() => ['quantity' => 1],
        ]));
        Carbon::setTestNow();
    }

    private function createOrder(User $user, Shop $shop, ProductVariant $variant, array $overrides = []): Order
    {
        $items = $overrides['items'] ?? [[
            'product_variant_id' => $variant->getKey(),
            'quantity' => $overrides['quantity'] ?? 1,
        ]];
        unset($overrides['items'], $overrides['quantity']);

        if (! array_key_exists('customer_id', $overrides)) {
            $mobile = '91'.random_int(10000000, 99999999);
            $customer = Customer::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'name' => 'Snapshot Customer',
                    'mobile_country_code' => '+91',
                    'mobile' => $mobile,
                    'mobile_normalized' => $mobile,
                    'email' => 'snapshot-customer-'.Str::random(8).'@example.test',
                    'status' => Customer::STATUS_ACTIVE,
                ],
            );
            $overrides['customer_id'] = $customer->getKey();
        }

        if (($overrides['fulfilment_type'] ?? Order::FULFILMENT_COUNTER) === Order::FULFILMENT_DELIVERY
            && ! array_key_exists('shipping_address_snapshot', $overrides)) {
            $overrides['shipping_address_snapshot'] = [
                'recipient_name' => 'Snapshot Customer',
                'mobile_country_code' => '+91',
                'mobile' => '9422945125',
                'address_line_1' => 'Snapshot Road',
                'address_line_2' => null,
                'landmark' => null,
                'city' => 'Nashik',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '422009',
            ];
        }

        return app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 5000,
            'items' => $items,
            ...$overrides,
        ], $user)->load('items.order');
    }

    private function refund(Order $order): OrderRefund
    {
        $reason = $this->returnReason($order);

        return OrderRefund::query()->create([
            'refund_number' => 'REFUND-'.Str::random(8),
            'order_id' => $order->getKey(),
            'merchant_id' => $order->merchant_id,
            'shop_id' => $order->shop_id,
            'return_reason_id' => $reason->getKey(),
            'reason_name' => $reason->name,
            'refund_method' => 'cash',
            'status' => OrderRefund::STATUS_COMPLETED,
        ]);
    }

    private function returnReason(Order $order): ReturnReason
    {
        return ReturnReason::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $order->merchant_id,
            'code' => 'reason-'.Str::random(6),
            'name' => 'Reason',
            'status' => 'active',
        ]);
    }

    private function promotion(Shop $shop, string $templateCode, array $overrides = [], array $reward = []): Promotion
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => $overrides['name'] ?? 'Policy Promotion '.Str::random(6),
            'slug' => Str::slug($overrides['name'] ?? 'policy-promotion-'.Str::random(6)),
            'status' => Promotion::STATUS_DRAFT,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ...$overrides,
        ]);
        $promotion->rewards()->create([
            'reward_type' => $template->reward_type,
            ...$reward,
        ]);

        return $promotion;
    }

    private function couponPromotion(Shop $shop, string $templateCode, string $code, array $overrides = [], array $reward = []): PromotionCoupon
    {
        $promotion = $this->promotion($shop, $templateCode, [
            'status' => Promotion::STATUS_ACTIVE,
            'activation_type' => Promotion::ACTIVATION_COUPON,
            ...$overrides,
        ], $reward);
        $this->target($promotion, PromotionTarget::TYPE_ALL);

        return $promotion->coupons()->create([
            'shop_id' => $shop->getKey(),
            'code' => $code,
            'status' => PromotionCoupon::STATUS_ACTIVE,
        ]);
    }

    private function target(Promotion $promotion, string $type, ?int $id = null, string $role = PromotionTarget::ROLE_ELIGIBLE): void
    {
        $promotion->targets()->create([
            'target_role' => $role,
            'target_type' => $type,
            'target_id' => $id,
            'sort_order' => 10,
        ]);
    }

    private function condition(Promotion $promotion, string $type, string $value): void
    {
        $promotion->conditions()->create([
            'condition_type' => $type,
            'operator' => '>=',
            'value_numeric' => $value,
            'sort_order' => 10,
        ]);
    }

    private function exchange(Order $order): OrderExchange
    {
        return OrderExchange::query()->create([
            'exchange_number' => 'EXCH-'.Str::random(8),
            'original_order_id' => $order->getKey(),
            'merchant_id' => $order->merchant_id,
            'shop_id' => $order->shop_id,
            'status' => OrderExchange::STATUS_COMPLETED,
        ]);
    }

    private function history(Order $order, ?string $fromStatus, string $toStatus, Carbon $createdAt, array $metadata = []): void
    {
        $order->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => 'Status changed',
            'metadata' => $metadata,
            'created_at' => $createdAt,
        ]);
    }

    private function setShopPolicy(Shop $shop, bool $refundAllowed, int $refundWindowDays, bool $exchangeAllowed, int $exchangeWindowDays): void
    {
        $settings = app(ShopSettingsService::class);
        $settings->setTyped($shop->getKey(), 'returns', 'refund_allowed', $refundAllowed, ShopSetting::TYPE_BOOLEAN);
        $settings->setTyped($shop->getKey(), 'returns', 'refund_window_days', $refundWindowDays, ShopSetting::TYPE_INTEGER);
        $settings->setTyped($shop->getKey(), 'returns', 'exchange_allowed', $exchangeAllowed, ShopSetting::TYPE_BOOLEAN);
        $settings->setTyped($shop->getKey(), 'returns', 'exchange_window_days', $exchangeWindowDays, ShopSetting::TYPE_INTEGER);
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductVariant}
     */
    private function fixture(): array
    {
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();
        $product = $this->product($merchant, $shop, $root, $category, 'Snapshot Shirt');

        return [$user, $shop, $this->variant($product, 'Default', 150)];
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductVariant, 3: ProductVariant}
     */
    private function fixtureWithReplacement(): array
    {
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();

        return [
            $user,
            $shop,
            $this->variant($this->product($merchant, $shop, $root, $category, 'Original Shirt'), 'Default', 150),
            $this->variant($this->product($merchant, $shop, $root, $category, 'Replacement Shirt'), 'Default', 150),
        ];
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductVariant, 3: ProductVariant, 4: ProductVariant}
     */
    private function fixtureWithTwoOriginals(): array
    {
        [$user, $merchant, $shop, $root, $category] = $this->baseFixture();

        return [
            $user,
            $shop,
            $this->variant($this->product($merchant, $shop, $root, $category, 'Original One'), 'Default', 150),
            $this->variant($this->product($merchant, $shop, $root, $category, 'Original Two'), 'Default', 150),
            $this->variant($this->product($merchant, $shop, $root, $category, 'Replacement'), 'Default', 150),
        ];
    }

    /**
     * @return array{0: User, 1: MerchantProfile, 2: Shop, 3: ProductCategory, 4: ProductCategory}
     */
    private function baseFixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Snapshot Merchant',
            'email' => 'snapshot-'.Str::random(8).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Snapshot Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);
        $root = ProductCategory::query()->create([
            'name' => 'Snapshot Root',
            'slug' => 'snapshot-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Snapshot Category',
            'slug' => 'snapshot-category-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Snapshot Shop',
            'slug' => 'snapshot-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return [$user, $merchant, $shop, $root, $category];
    }

    private function product(MerchantProfile $merchant, Shop $shop, ProductCategory $root, ProductCategory $category, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'availability_status_id' => ProductAvailabilityStatus::query()
                ->where('merchant_id', $merchant->getKey())
                ->where('code', ProductAvailabilityStatus::CODE_IN_STOCK)
                ->value('id'),
            'status' => 'active',
        ]);
    }

    private function variant(Product $product, string $name, int $price): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => 'SKU-'.Str::random(6),
            'barcode' => 'BAR-'.Str::random(6),
            'name' => $name,
            'mrp' => $price,
            'selling_price' => $price,
            'stock_quantity' => 20,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }
}
