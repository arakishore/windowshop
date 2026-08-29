<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderExchange;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductReturnPolicy;
use App\Models\ProductVariant;
use App\Models\ReturnReason;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Checkout\StorefrontCheckoutOrderService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Order\OrderCreationService;
use App\Services\Order\OrderExchangeService;
use App\Services\Order\OrderRefundService;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
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

    private function createOrder(User $user, Shop $shop, ProductVariant $variant, array $overrides = []): Order
    {
        $items = $overrides['items'] ?? [[
            'product_variant_id' => $variant->getKey(),
            'quantity' => $overrides['quantity'] ?? 1,
        ]];
        unset($overrides['items'], $overrides['quantity']);

        return app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 5000,
            'items' => $items,
            ...$overrides,
        ], $user)->load('items.order');
    }

    private function refund(Order $order): OrderRefund
    {
        $reason = ReturnReason::query()->create([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $order->merchant_id,
            'code' => 'reason-'.Str::random(6),
            'name' => 'Reason',
            'status' => 'active',
        ]);

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
