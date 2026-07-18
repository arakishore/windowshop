<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\MerchantCustomer;
use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Order\OrderCreationService;
use App\Services\Order\OrderNumberService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class OrderFoundationTest extends TestCase
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

    public function test_order_can_be_created_with_multiple_items_snapshots_and_simple_pos_totals(): void
    {
        [$user, $shop, $firstVariant, $secondVariant] = $this->orderFixture();

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 1000,
            'items' => [
                ['product_variant_id' => $firstVariant->getKey(), 'quantity' => 2],
                ['product_variant_id' => $secondVariant->getKey(), 'quantity' => 1],
            ],
        ], $user);

        $order->refresh()->load(['items', 'totals', 'statusHistories']);

        $this->assertSame($shop->merchant_id, $order->merchant_id);
        $this->assertSame($shop->getKey(), $order->shop_id);
        $this->assertNull($order->customer_id);
        $this->assertSame(Order::SOURCE_POS, $order->created_source);
        $this->assertSame(Order::FULFILMENT_COUNTER, $order->fulfilment_type);
        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame(Order::PAYMENT_METHOD_CASH, $order->payment_method);
        $this->assertSame(Order::PAYMENT_PAID, $order->payment_status);
        $this->assertSame('INR', $order->currency_code);
        $this->assertSame('650.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_total);
        $this->assertSame('0.00', $order->shipping_total);
        $this->assertSame('0.00', $order->tax_total);
        $this->assertSame('650.00', $order->grand_total);
        $this->assertSame('1000.00', $order->amount_paid);
        $this->assertSame('350.00', $order->change_amount);
        $this->assertNotNull($order->completed_at);

        $this->assertSame(2, $order->items->count());
        $firstItem = $order->items->firstWhere('product_variant_id', $firstVariant->getKey());
        $this->assertSame('Snapshot Product One', $firstItem->product_name);
        $this->assertSame('products/order-snapshot-one.webp', $firstItem->product_image);
        $this->assertSame('Red / M', $firstItem->variant_name);
        $this->assertSame('SKU-RED-M', $firstItem->sku);
        $this->assertSame('BAR-RED-M', $firstItem->barcode);
        $this->assertSame(2, $firstItem->quantity);
        $this->assertSame('200.00', $firstItem->unit_mrp);
        $this->assertSame('150.00', $firstItem->unit_price);
        $this->assertSame('300.00', $firstItem->line_subtotal);
        $this->assertSame('300.00', $firstItem->line_total);

        $this->assertSame(
            [OrderTotal::CODE_SUBTOTAL, OrderTotal::CODE_GRAND_TOTAL],
            $order->totals->pluck('code')->all(),
        );
        $this->assertSame(['650.00', '650.00'], $order->totals->pluck('amount')->all());
        $this->assertSame([10, 100], $order->totals->pluck('sort_order')->all());
        $this->assertSame(1, $order->statusHistories->count());
        $this->assertNull($order->statusHistories->first()->from_status);
        $this->assertSame(Order::STATUS_COMPLETED, $order->statusHistories->first()->to_status);
    }

    public function test_hybrid_totals_support_signed_discount_rows_and_ordered_breakdown(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 1000,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 2],
            ],
            'totals' => [
                ['code' => OrderTotal::CODE_COUPON_DISCOUNT, 'title' => 'Coupon VANA2026', 'amount' => 25, 'sort_order' => 40, 'source' => 'coupon'],
                ['code' => OrderTotal::CODE_SHIPPING, 'title' => 'Shipping Charges', 'amount' => 50, 'sort_order' => 50, 'source' => 'shipping'],
                ['code' => OrderTotal::CODE_TAX, 'title' => 'GST', 'amount' => 18, 'sort_order' => 60, 'source' => 'tax'],
                ['code' => OrderTotal::CODE_ROUNDING, 'title' => 'Rounding Adjustment', 'amount' => -0.50, 'sort_order' => 90, 'source' => 'rounding'],
            ],
        ], $user)->refresh();

        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('25.00', $order->discount_total);
        $this->assertSame('50.00', $order->shipping_total);
        $this->assertSame('18.00', $order->tax_total);
        $this->assertSame('-0.50', $order->rounding_adjustment);
        $this->assertSame('342.50', $order->grand_total);

        $totals = $order->totals()->get();
        $this->assertSame([
            OrderTotal::CODE_SUBTOTAL,
            OrderTotal::CODE_COUPON_DISCOUNT,
            OrderTotal::CODE_SHIPPING,
            OrderTotal::CODE_TAX,
            OrderTotal::CODE_ROUNDING,
            OrderTotal::CODE_GRAND_TOTAL,
        ], $totals->pluck('code')->all());
        $this->assertSame('-25.00', $totals->firstWhere('code', OrderTotal::CODE_COUPON_DISCOUNT)->amount);
        $this->assertSame('50.00', $totals->firstWhere('code', OrderTotal::CODE_SHIPPING)->amount);
    }

    public function test_status_transition_creates_history_and_sets_timestamps(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();
        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => Order::PAYMENT_UNPAID,
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ],
        ], $user);

        $service = app(OrderStatusService::class);
        $service->transition($order, Order::STATUS_COMPLETED, $user, 'POS cash sale completed');
        $order->refresh();

        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(2, $order->statusHistories()->count());

        $service->transition($order, Order::STATUS_CANCELLED, $user, 'Sale cancelled by merchant');
        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(3, $order->statusHistories()->count());
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => Order::STATUS_COMPLETED,
            'to_status' => Order::STATUS_CANCELLED,
            'notes' => 'Sale cancelled by merchant',
        ]);
    }

    public function test_order_number_is_unique_and_human_readable(): void
    {
        $service = app(OrderNumberService::class);
        [, $shop] = $this->orderFixture();

        $first = $service->generate(now());
        Order::query()->create([
            'order_number' => $first,
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
        ]);
        $second = $service->generate(now());

        $this->assertStringStartsWith('ORD-'.now()->format('Ymd').'-', $first);
        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('000002', $second);
    }

    public function test_order_creation_copies_customer_snapshot_from_database(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();
        $customer = MerchantCustomer::query()->create([
            'merchant_id' => $shop->merchant_id,
            'customer_code' => 'CUS-000001',
            'name' => 'Database Customer',
            'mobile_country_code' => '+91',
            'mobile' => '9876543210',
            'mobile_normalized' => '919876543210',
            'email' => 'database-customer@example.test',
            'status' => MerchantCustomer::STATUS_ACTIVE,
        ]);

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_name' => 'Spoofed Customer',
            'customer_mobile' => '0000000000',
            'customer_email' => 'spoofed@example.test',
            'amount_paid' => 500,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ],
        ], $user)->refresh();

        $this->assertSame($customer->getKey(), $order->customer_id);
        $this->assertSame('Database Customer', $order->customer_name);
        $this->assertSame('9876543210', $order->customer_mobile);
        $this->assertSame('database-customer@example.test', $order->customer_email);
    }

    public function test_product_force_deletion_does_not_remove_historical_order_items_or_snapshots(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();
        $product = $variant->product;
        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 500,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ],
        ], $user);

        $product->delete();
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Snapshot Product One', $order->items()->first()->product_name);

        $product->forceDelete();
        $item = $order->items()->first();

        $this->assertNotNull($item);
        $this->assertNull($item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame('Snapshot Product One', $item->product_name);
        $this->assertSame('SKU-RED-M', $item->sku);
    }

    public function test_order_creation_marks_short_payment_as_partially_paid(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 10,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ],
        ], $user)->refresh();

        $this->assertSame(Order::PAYMENT_PARTIALLY_PAID, $order->payment_status);
        $this->assertSame('10.00', $order->amount_paid);
        $this->assertSame('0.00', $order->change_amount);
    }

    public function test_order_creation_marks_zero_payment_as_unpaid(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();

        $order = app(OrderCreationService::class)->create([
            'shop_id' => $shop->getKey(),
            'amount_paid' => 0,
            'items' => [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ],
        ], $user)->refresh();

        $this->assertSame(Order::PAYMENT_UNPAID, $order->payment_status);
        $this->assertSame('0.00', $order->amount_paid);
    }

    public function test_order_creation_rejects_explicit_paid_status_when_amount_is_short(): void
    {
        [$user, $shop, $variant] = $this->orderFixture();

        $this->expectException(ValidationException::class);

        try {
            app(OrderCreationService::class)->create([
                'shop_id' => $shop->getKey(),
                'payment_status' => Order::PAYMENT_PAID,
                'amount_paid' => 10,
                'items' => [
                    ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
                ],
            ], $user);
        } finally {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, DB::table('order_items')->count());
            $this->assertSame(0, DB::table('order_totals')->count());
            $this->assertSame(0, DB::table('order_status_histories')->count());
        }
    }

    public function test_order_totals_service_rejects_mismatched_summary(): void
    {
        $this->expectException(ValidationException::class);

        app(OrderTotalsService::class)->save(new Order(), [
            'subtotal' => '100.00',
            'discount_total' => '10.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'rounding_adjustment' => '0.00',
            'grand_total' => '100.00',
            'amount_paid' => '100.00',
            'change_amount' => '0.00',
        ], []);
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductVariant, 3: ProductVariant}
     */
    private function orderFixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Order User',
            'email' => 'order-'.Str::random(8).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Order Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Order Root '.Str::random(4),
            'slug' => 'order-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Order Category '.Str::random(4),
            'slug' => 'order-category-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Order Shop '.Str::random(4),
            'slug' => 'order-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        $firstProduct = $this->createProduct($merchant, $shop, $root, $category, 'Snapshot Product One');
        $secondProduct = $this->createProduct($merchant, $shop, $root, $category, 'Snapshot Product Two');
        $this->attachPrimaryImage($firstProduct, 'products/order-snapshot-one.webp');

        return [
            $user,
            $shop,
            $this->createVariant($firstProduct, 'Red / M', 'SKU-RED-M', 'BAR-RED-M', 200, 150),
            $this->createVariant($secondProduct, 'Blue / L', 'SKU-BLUE-L', 'BAR-BLUE-L', 400, 350),
        ];
    }

    private function createProduct(MerchantProfile $merchant, Shop $shop, ProductCategory $root, ProductCategory $category, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createVariant(Product $product, string $name, string $sku, string $barcode, int $mrp, int $price): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $name,
            'mrp' => $mrp,
            'selling_price' => $price,
            'stock_quantity' => 10,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }

    private function attachPrimaryImage(Product $product, string $path): void
    {
        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => $path,
            'thumbnail_path' => $path,
            'is_primary' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $product->forceFill(['primary_image_id' => $image->getKey()])->save();
    }
}
