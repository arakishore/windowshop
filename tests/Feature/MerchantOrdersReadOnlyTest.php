<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\User;
use Database\Seeders\OrderStatusSeeder;
use Database\Seeders\PaymentStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantOrdersReadOnlyTest extends TestCase
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

    public function test_storefront_pending_order_appears_in_merchant_orders_and_detail(): void
    {
        $this->seed(OrderStatusSeeder::class);
        $this->seed(PaymentStatusSeeder::class);
        [$user, $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-20260825-000001',
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'payment_method' => 'cash_at_shop',
            'grand_total' => 1998,
        ]);
        $this->orderItem($order->getKey(), 'Maroon Comfort Soft Fleece', 'FLEECE-MAROON', 1998);
        $this->statusHistory($order->getKey(), null, Order::STATUS_PENDING, 'Storefront order placed');

        $list = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.index'));

        $list->assertOk();
        $list->assertSee('Orders');
        $list->assertSee('ORD-20260825-000001');
        $list->assertSee('Kishore Mishra');
        $list->assertSee('Pickup');
        $list->assertSee('Cash at Shop');
        $list->assertSee('Pending');
        $list->assertDontSee('Accept Order');
        $list->assertDontSee('Mark Payment Received');

        $detail = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $order));

        $detail->assertOk();
        $detail->assertSee('Order');
        $detail->assertSee('ORD-20260825-000001');
        $detail->assertSee('Storefront');
        $detail->assertSee('Order Progress');
        $detail->assertSee('Customer');
        $detail->assertSee('Maroon Comfort Soft Fleece');
        $detail->assertSee('Order Summary');
        $detail->assertSee('Pickup Information');
        $detail->assertSee('Payment');
        $detail->assertSee('Order Activity');
        $detail->assertSee('Storefront order placed');
        $detail->assertDontSee('Confirm Order');
        $detail->assertDontSee('Cancel Order');
    }

    public function test_delivery_cod_order_detail_uses_delivery_workspace_sections(): void
    {
        [$user, $shopId] = $this->merchantShopFixture();
        $order = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-DELIVERY-COD',
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'payment_method' => 'cash_on_delivery',
            'shipping_total' => 50,
            'grand_total' => 2048,
            'shipping_recipient_name' => 'Kishore Mishra',
            'shipping_mobile_country_code' => '+91',
            'shipping_mobile' => '9422945125',
            'shipping_address_line_1' => 'Flat 08, Laxmi Govind',
            'shipping_landmark' => 'Indira Nagar',
            'shipping_city' => 'Nashik',
            'shipping_state' => 'Maharashtra',
            'shipping_country' => 'India',
            'shipping_postal_code' => '422009',
        ]);
        $this->orderItem($order->getKey(), 'Delivery Shirt', 'DEL-SHIRT', 1998);

        $response = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Delivery Information');
        $response->assertSee('Cash on Delivery');
        $response->assertSee('Packed');
        $response->assertSee('Shipped');
        $response->assertSee('Out for Delivery');
        $response->assertSee('Delivered');
        $response->assertDontSee('Pickup Information');
    }

    public function test_orders_source_filter_supports_storefront_and_customer_app_only(): void
    {
        [$user, $shopId] = $this->merchantShopFixture();
        $storefront = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-STOREFRONT',
            'created_source' => Order::SOURCE_STOREFRONT,
        ]);
        $customerApp = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-CUSTOMER-APP',
            'created_source' => Order::SOURCE_CUSTOMER_APP,
        ]);
        $pos = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-POS',
            'created_source' => Order::SOURCE_POS,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
        ]);
        $exchange = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-EXCHANGE',
            'created_source' => Order::SOURCE_EXCHANGE_REPLACEMENT,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
        ]);

        $all = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.index'));

        $all->assertOk();
        $all->assertSee($storefront->order_number);
        $all->assertSee($customerApp->order_number);
        $all->assertDontSee($pos->order_number);
        $all->assertDontSee($exchange->order_number);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.index', ['source' => Order::SOURCE_STOREFRONT]))
            ->assertOk()
            ->assertSee($storefront->order_number)
            ->assertDontSee($customerApp->order_number);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.index', ['source' => Order::SOURCE_CUSTOMER_APP]))
            ->assertOk()
            ->assertSee($customerApp->order_number)
            ->assertDontSee($storefront->order_number);
    }

    public function test_customer_app_detail_works_but_pos_and_internal_details_are_not_found(): void
    {
        [$user, $shopId] = $this->merchantShopFixture();
        $customerApp = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-CUSTOMER-APP-DETAIL',
            'created_source' => Order::SOURCE_CUSTOMER_APP,
        ]);
        $pos = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-POS-DETAIL',
            'created_source' => Order::SOURCE_POS,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
        ]);
        $exchange = $this->operationalOrder($shopId, [
            'order_number' => 'ORD-EXCHANGE-DETAIL',
            'created_source' => Order::SOURCE_EXCHANGE_REPLACEMENT,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => PaymentStatus::CODE_PAID,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $customerApp))
            ->assertOk()
            ->assertSee('Customer App');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $pos))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $exchange))
            ->assertNotFound();
    }

    public function test_order_detail_is_limited_to_active_shop(): void
    {
        [$user, $shopId] = $this->merchantShopFixture();
        $otherShopId = $this->shopForMerchant((int) DB::table('shops')->where('id', $shopId)->value('merchant_id'), 'Other Branch');
        $otherOrder = $this->operationalOrder($otherShopId, ['order_number' => 'ORD-OTHER-SHOP']);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.orders.show', $otherOrder))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: int}
     */
    private function merchantShopFixture(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Orders Merchant',
            'email' => 'orders-merchant-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = (int) (DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchantId = (int) DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'business_name' => 'Orders Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $this->shopForMerchant($merchantId, 'Vana Women Studio')];
    }

    private function shopForMerchant(int $merchantId, string $name): int
    {
        $categoryId = (int) (DB::table('product_categories')->where('slug', 'retail')->value('id')
            ?? DB::table('product_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Retail',
                'slug' => 'retail',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        return (int) DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'root_product_category_id' => $categoryId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Shop 12, College Road',
            'landmark' => 'Near Big Bazaar',
            'pincode' => '422009',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function operationalOrder(int $shopId, array $overrides = []): Order
    {
        $merchantId = (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');

        return Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_PICKUP,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => 'cash_at_shop',
            'payment_status' => PaymentStatus::CODE_PENDING,
            'currency_code' => 'INR',
            'subtotal' => 1998,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 1998,
            'amount_paid' => 0,
            'customer_name' => 'Kishore Mishra',
            'customer_mobile' => '9422945125',
            'customer_email' => 'kishore@example.test',
            'billing_recipient_name' => 'Kishore Mishra',
            'billing_mobile_country_code' => '+91',
            'billing_mobile' => '9422945125',
            'billing_address_line_1' => 'Flat 08, Laxmi Govind',
            'billing_city' => 'Nashik',
            'billing_state' => 'Maharashtra',
            'billing_country' => 'India',
            'billing_postal_code' => '422009',
        ], $overrides));
    }

    private function orderItem(int $orderId, string $productName, string $sku, float $total): void
    {
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_name' => $productName,
            'variant_name' => 'Default',
            'sku' => $sku,
            'quantity' => 1,
            'unit_mrp' => $total,
            'unit_price' => $total,
            'unit_discount' => 0,
            'line_subtotal' => $total,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusHistory(int $orderId, ?string $fromStatus, string $toStatus, string $notes): void
    {
        DB::table('order_status_histories')->insert([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
