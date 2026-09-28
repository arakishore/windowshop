<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentStatus;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Order\ExpireUncollectedPickupsService;
use App\Services\Order\OrderActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ExpireUncollectedPickupsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_collection_windows_and_never_values_use_first_ready_for_pickup_history(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $neverOrders = [];
            foreach (['zero' => '0', 'blank' => '', 'null' => null, 'missing' => null, 'invalid' => '36'] as $label => $value) {
                $shop = $this->shop("Never {$label}");
                if ($label !== 'missing') {
                    $this->rawExpiry($shop, $value);
                } else {
                    $this->deleteExpiry($shop);
                }
                $neverOrders[] = $this->readyOrder($shop, now()->subDays(10));
            }

            $twelveShop = $this->shop('Twelve Hours');
            $this->expiry($twelveShop, 12);
            $beforeTwelve = $this->readyOrder($twelveShop, now()->subHours(12)->addSecond());
            $atTwelve = $this->readyOrder($twelveShop, now()->subHours(12));

            $ordersAtDeadline = [];
            foreach ([24, 48, 72] as $hours) {
                $shop = $this->shop("{$hours} Hours");
                $this->expiry($shop, $hours);
                $ordersAtDeadline[] = $this->readyOrder($shop, now()->subHours($hours));
            }

            $oldOrderRecentReady = $this->readyOrder($twelveShop, now()->subHour(), now()->subDays(5));
            $multiple = $this->readyOrder($twelveShop, now()->subHours(13));
            $this->readyHistory($multiple, now()->subHour());

            app(ExpireUncollectedPickupsService::class)->run();

            foreach ([...$neverOrders, $beforeTwelve, $oldOrderRecentReady] as $open) {
                $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $open->fresh()->order_status);
            }
            foreach ([$atTwelve, ...$ordersAtDeadline, $multiple] as $expired) {
                $this->assertSame(Order::STATUS_CANCELLED, $expired->fresh()->order_status);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_only_storefront_cash_at_shop_pickups_currently_ready_with_history_expire(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Eligibility');
            $this->expiry($shop, 12);
            $old = now()->subDay();
            $eligible = $this->readyOrder($shop, $old);
            $missingHistory = $this->order($shop);
            $pending = $this->readyOrder($shop, $old, null, ['order_status' => Order::STATUS_PENDING]);
            $confirmed = $this->readyOrder($shop, $old, null, ['order_status' => Order::STATUS_CONFIRMED]);
            $processing = $this->readyOrder($shop, $old, null, ['order_status' => Order::STATUS_PROCESSING]);
            $completed = $this->readyOrder($shop, $old, null, ['order_status' => Order::STATUS_COMPLETED]);
            $cancelled = $this->readyOrder($shop, $old, null, ['order_status' => Order::STATUS_CANCELLED]);
            $delivery = $this->readyOrder($shop, $old, null, ['fulfilment_type' => Order::FULFILMENT_DELIVERY]);
            $cod = $this->readyOrder($shop, $old, null, ['payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY]);
            $upi = $this->readyOrder($shop, $old, null, ['payment_method' => StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI]);
            $pos = $this->readyOrder($shop, $old, null, ['created_source' => Order::SOURCE_POS]);

            app(ExpireUncollectedPickupsService::class)->run();

            $this->assertSame(Order::STATUS_CANCELLED, $eligible->fresh()->order_status);
            foreach ([$missingHistory, $pending, $confirmed, $processing, $completed, $cancelled, $delivery, $cod, $upi, $pos] as $untouched) {
                $this->assertSame($untouched->order_status, $untouched->fresh()->order_status);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expiry_restores_inventory_records_activity_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Inventory');
            $this->expiry($shop, 12);
            [$productId, $variantId] = $this->productVariant($shop, 8);
            $readyAt = now()->subHours(12);
            $order = $this->readyOrder($shop, $readyAt);
            $order->items()->create([
                'product_id' => $productId, 'product_variant_id' => $variantId, 'product_name' => 'Pickup Product',
                'variant_name' => 'Default', 'quantity' => 2, 'unit_price' => 50, 'line_subtotal' => 100, 'line_total' => 100,
            ]);

            Artisan::call('orders:expire-uncollected-pickups');
            $this->assertStringContainsString('Orders expired: 1', Artisan::output());
            Artisan::call('orders:expire-uncollected-pickups');

            $order->refresh();
            $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
            $this->assertSame(PaymentStatus::CODE_PENDING, $order->payment_status);
            $this->assertSame('0.00', $order->amount_paid);
            $this->assertSame(10, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
            $this->assertSame(1, $order->statusHistories()->where('metadata->action', OrderStatusHistory::ACTION_PICKUP_COLLECTION_EXPIRED)->count());

            $history = $order->statusHistories()->latest('id')->firstOrFail();
            $this->assertNull($history->changed_by);
            $this->assertTrue($history->metadata['automatic']);
            $this->assertSame(12, $history->metadata['expiry_hours']);
            $this->assertSame($readyAt->toISOString(), $history->metadata['ready_for_pickup_at']);
            $this->assertArrayHasKey('expired_at', $history->metadata);
            $presenter = app(OrderActivityPresenter::class);
            $this->assertSame('Pickup Collection Window Expired', $presenter->title($history));
            $this->assertStringContainsString('automatically cancelled', $presenter->merchantDescription($order, $history));
            $this->assertSame('The pickup collection window expired before the order was collected.', $presenter->customerDescription($order, $history));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_final_locked_recheck_skips_order_no_longer_ready_for_pickup(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Race');
            $this->expiry($shop, 12);
            $order = $this->readyOrder($shop, now()->subDay());
            $order->forceFill(['order_status' => Order::STATUS_COMPLETED, 'payment_status' => Order::PAYMENT_PAID, 'amount_paid' => 100])->save();

            $this->assertFalse(app(ExpireUncollectedPickupsService::class)->expire($order->getKey()));
            $this->assertSame(Order::STATUS_COMPLETED, $order->fresh()->order_status);
            $this->assertSame(0, $order->statusHistories()->where('metadata->action', OrderStatusHistory::ACTION_PICKUP_COLLECTION_EXPIRED)->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    private function expiry(Shop $shop, int $hours): void
    {
        app(ShopSettingsService::class)->setTyped($shop->getKey(), 'payment', 'cash_at_shop_pickup_expiry_hours', $hours, ShopSetting::TYPE_INTEGER);
    }

    private function rawExpiry(Shop $shop, ?string $value): void
    {
        $this->deleteExpiry($shop);
        DB::table('shop_settings')->insert([
            'shop_id' => $shop->getKey(), 'group' => 'payment', 'setting_key' => 'cash_at_shop_pickup_expiry_hours',
            'setting_value' => $value, 'setting_type' => ShopSetting::TYPE_INTEGER, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function deleteExpiry(Shop $shop): void
    {
        ShopSetting::query()->where('shop_id', $shop->getKey())->where('group', 'payment')->where('setting_key', 'cash_at_shop_pickup_expiry_hours')->delete();
    }

    private function readyOrder(Shop $shop, Carbon $readyAt, ?Carbon $createdAt = null, array $overrides = []): Order
    {
        $order = $this->order($shop, array_merge(['order_status' => Order::STATUS_READY_FOR_PICKUP, 'created_at' => $createdAt ?? now()->subDays(2)], $overrides));
        $this->readyHistory($order, $readyAt);

        return $order;
    }

    private function readyHistory(Order $order, Carbon $at): void
    {
        $order->statusHistories()->create([
            'from_status' => Order::STATUS_PROCESSING, 'to_status' => Order::STATUS_READY_FOR_PICKUP,
            'notes' => 'Order is ready for customer pickup.', 'metadata' => ['action' => 'merchant_mark_ready_for_pickup'], 'created_at' => $at,
        ]);
    }

    private function shop(string $name): Shop
    {
        $userId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'email' => Str::slug($name).Str::random(4).'@example.test',
            'password' => bcrypt('password'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $merchantId = DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(), 'user_id' => $userId, 'business_name' => $name,
            'verification_status' => 'approved', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchantId, 'root_product_category_id' => $categoryId,
            'name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5), 'address_line_1' => 'Main Road', 'status' => 'active',
        ]);
    }

    private function order(Shop $shop, array $overrides = []): Order
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);
        $order = Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(10)), 'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(), 'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_PICKUP, 'order_status' => Order::STATUS_READY_FOR_PICKUP,
            'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            'payment_status' => PaymentStatus::CODE_PENDING, 'currency_code' => 'INR',
            'subtotal' => 100, 'grand_total' => 100, 'amount_paid' => 0,
        ], $overrides));
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $order->refresh();
    }

    /** @return array{int, int} */
    private function productVariant(Shop $shop, int $stock): array
    {
        $productId = DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(), 'merchant_id' => $shop->merchant_id, 'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id, 'product_category_id' => $shop->root_product_category_id,
            'product_name' => 'Pickup Product', 'slug' => 'pickup-product-'.Str::random(5), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(), 'product_id' => $productId, 'shop_id' => $shop->getKey(),
            'sku' => 'PICK-'.Str::upper(Str::random(5)), 'name' => 'Default', 'mrp' => 50, 'selling_price' => 50,
            'stock_quantity' => $stock, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$productId, $variantId];
    }
}
