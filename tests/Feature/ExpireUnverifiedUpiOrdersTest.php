<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentStatus;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Order\ExpireUnverifiedUpiOrdersService;
use App\Services\Order\OrderActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ExpireUnverifiedUpiOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                static fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_shop_specific_expiry_deadlines_and_never_values_are_respected(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $neverShop = $this->shop('Never Shop');
            $blankShop = $this->shop('Blank Shop');
            $legacyBlankShop = $this->shop('Legacy Blank Shop');
            $fiveShop = $this->shop('Five Shop');
            $tenShop = $this->shop('Ten Shop');
            $fifteenShop = $this->shop('Fifteen Shop');
            $thirtyShop = $this->shop('Thirty Shop');

            $this->expiry($neverShop, 0);
            $this->expiry($blankShop, null);
            $this->expiry($legacyBlankShop, 0);
            ShopSetting::query()
                ->where('shop_id', $legacyBlankShop->getKey())
                ->where('group', 'payment')
                ->where('setting_key', 'merchant_upi_expiry_minutes')
                ->update(['setting_value' => '']);
            $this->expiry($fiveShop, 5);
            $this->expiry($tenShop, 10);
            $this->expiry($fifteenShop, 15);
            $this->expiry($thirtyShop, 30);

            $never = $this->order($neverShop, ['created_at' => now()->subHours(2)]);
            $blank = $this->order($blankShop, ['created_at' => now()->subHours(2)]);
            $legacyBlank = $this->order($legacyBlankShop, ['created_at' => now()->subHours(2)]);
            $young = $this->order($fiveShop, ['created_at' => now()->subMinutes(4)]);
            $five = $this->order($fiveShop, ['created_at' => now()->subMinutes(5)]);
            $ten = $this->order($tenShop, ['created_at' => now()->subMinutes(10)]);
            $fifteen = $this->order($fifteenShop, ['created_at' => now()->subMinutes(15)]);
            $thirtyYoung = $this->order($thirtyShop, ['created_at' => now()->subMinutes(29)]);
            $thirty = $this->order($thirtyShop, ['created_at' => now()->subMinutes(30)]);

            $counts = app(ExpireUnverifiedUpiOrdersService::class)->run();

            $this->assertSame(4, $counts['expired']);
            foreach ([$five, $ten, $fifteen, $thirty] as $expired) {
                $this->assertSame(Order::STATUS_CANCELLED, $expired->fresh()->order_status);
                $this->assertSame(PaymentStatus::CODE_PENDING, $expired->fresh()->payment_status);
            }
            foreach ([$never, $blank, $legacyBlank, $young, $thirtyYoung] as $open) {
                $this->assertSame(Order::STATUS_PENDING, $open->fresh()->order_status);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_only_pending_unverified_storefront_direct_upi_orders_expire(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Eligibility Shop');
            $this->expiry($shop, 5);
            $old = ['created_at' => now()->subMinutes(10)];

            $eligible = $this->order($shop, $old);
            $paymentNotFound = $this->order($shop, $old);
            $paymentNotFound->statusHistories()->create([
                'from_status' => Order::STATUS_PENDING,
                'to_status' => Order::STATUS_PENDING,
                'notes' => 'Direct Merchant UPI payment could not be verified.',
                'metadata' => ['action' => OrderStatusHistory::ACTION_UPI_PAYMENT_REJECTED, 'reason' => 'No payment found.'],
                'created_at' => now()->subMinutes(6),
            ]);
            $paid = $this->order($shop, [...$old, 'payment_status' => PaymentStatus::CODE_PAID, 'amount_paid' => 100]);
            $confirmed = $this->order($shop, [...$old, 'order_status' => Order::STATUS_CONFIRMED]);
            $completed = $this->order($shop, [...$old, 'order_status' => Order::STATUS_COMPLETED]);
            $cancelled = $this->order($shop, [...$old, 'order_status' => Order::STATUS_CANCELLED]);
            $cod = $this->order($shop, [...$old, 'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY]);
            $cashAtShop = $this->order($shop, [...$old, 'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP]);
            $pos = $this->order($shop, [...$old, 'created_source' => Order::SOURCE_POS]);

            app(ExpireUnverifiedUpiOrdersService::class)->run();

            $this->assertSame(Order::STATUS_CANCELLED, $eligible->fresh()->order_status);
            $this->assertSame(Order::STATUS_CANCELLED, $paymentNotFound->fresh()->order_status);
            foreach ([$paid, $confirmed, $completed, $cancelled, $cod, $cashAtShop, $pos] as $untouched) {
                $this->assertSame($untouched->order_status, $untouched->fresh()->order_status);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expiry_restores_inventory_and_records_activity_only_once(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Inventory Shop');
            $this->expiry($shop, 5);
            [$productId, $variantId] = $this->productVariant($shop, 8);
            $order = $this->order($shop, ['created_at' => now()->subMinutes(5)]);
            $order->items()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'product_name' => 'Expiry Product',
                'variant_name' => 'Default',
                'quantity' => 2,
                'unit_price' => 50,
                'line_subtotal' => 100,
                'line_total' => 100,
            ]);

            Artisan::call('orders:expire-unverified-upi');
            Artisan::call('orders:expire-unverified-upi');

            $this->assertSame(10, (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'));
            $this->assertSame(1, $order->statusHistories()->where('metadata->action', OrderStatusHistory::ACTION_UPI_PAYMENT_EXPIRED)->count());
            $history = $order->statusHistories()->latest('id')->firstOrFail();
            $this->assertNull($history->changed_by);
            $this->assertTrue($history->metadata['automatic']);
            $this->assertSame(5, $history->metadata['expiry_minutes']);
            $this->assertSame(PaymentStatus::CODE_PENDING, $order->fresh()->payment_status);
            $presenter = app(OrderActivityPresenter::class);
            $this->assertSame('UPI Payment Expired', $presenter->title($history));
            $this->assertStringContainsString('automatically cancelled', $presenter->merchantDescription($order->fresh(), $history));
            $this->assertSame(
                'The order was cancelled because the UPI payment was not verified within the allowed payment time.',
                $presenter->customerDescription($order->fresh(), $history),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_final_locked_recheck_skips_an_order_paid_before_cancellation(): void
    {
        Carbon::setTestNow('2026-09-25 12:00:00');
        try {
            $shop = $this->shop('Race Shop');
            $this->expiry($shop, 5);
            $order = $this->order($shop, ['created_at' => now()->subMinutes(10)]);
            $order->forceFill(['payment_status' => PaymentStatus::CODE_PAID, 'amount_paid' => 100])->save();

            $this->assertFalse(app(ExpireUnverifiedUpiOrdersService::class)->expire($order->getKey()));
            $this->assertSame(Order::STATUS_PENDING, $order->fresh()->order_status);
            $this->assertSame(PaymentStatus::CODE_PAID, $order->fresh()->payment_status);
            $this->assertDatabaseCount('order_status_histories', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function expiry(Shop $shop, ?int $minutes): void
    {
        app(ShopSettingsService::class)->setTyped($shop->getKey(), 'payment', 'merchant_upi_expiry_minutes', $minutes, ShopSetting::TYPE_INTEGER);
    }

    private function shop(string $name): Shop
    {
        $userId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'email' => Str::slug($name).'@example.test',
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
            'fulfilment_type' => Order::FULFILMENT_PICKUP, 'order_status' => Order::STATUS_PENDING,
            'payment_method' => StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI,
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
            'product_name' => 'Expiry Product', 'slug' => 'expiry-product-'.Str::random(5), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(), 'product_id' => $productId, 'shop_id' => $shop->getKey(),
            'sku' => 'EXP-'.Str::upper(Str::random(5)), 'name' => 'Default', 'mrp' => 50, 'selling_price' => 50,
            'stock_quantity' => $stock, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$productId, $variantId];
    }
}
