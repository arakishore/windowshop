<?php

namespace Tests\Feature;

use App\Models\MerchantCustomer;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Merchant\MerchantCustomerService;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class CustomerManagementFoundationTest extends TestCase
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

    public function test_customer_can_be_created_for_merchant_with_generated_unique_code_and_without_user(): void
    {
        [$merchant] = $this->merchantFixture('Customer Merchant');

        $first = $this->customerService()->create($merchant, [
            'name' => 'Asha Buyer',
            'mobile' => '98765 43210',
            'email' => 'asha@example.test',
            'status' => 'active',
        ]);
        $second = $this->customerService()->create($merchant, [
            'name' => 'Bala Buyer',
            'mobile' => '98765 43211',
            'status' => 'active',
        ]);

        $this->assertSame($merchant->getKey(), $first->merchant_id);
        $this->assertNull($first->user_id);
        $this->assertSame('CUS-000001', $first->customer_code);
        $this->assertSame('CUS-000002', $second->customer_code);
        $this->assertNotSame($first->customer_code, $second->customer_code);
        $this->assertSame('+91', $first->mobile_country_code);
        $this->assertSame('9876543210', $first->mobile);
        $this->assertSame('919876543210', $first->mobile_normalized);
        $this->assertTrue($merchant->customers()->whereKey($first->getKey())->exists());
    }

    public function test_same_normalized_mobile_is_unique_per_merchant_but_allowed_across_merchants(): void
    {
        [$firstMerchant] = $this->merchantFixture('First Mobile Merchant');
        [$secondMerchant] = $this->merchantFixture('Second Mobile Merchant');

        $this->customerService()->create($firstMerchant, [
            'name' => 'First Buyer',
            'mobile' => '+91 98765 43210',
            'status' => 'active',
        ]);
        $otherMerchantCustomer = $this->customerService()->create($secondMerchant, [
            'name' => 'Second Buyer',
            'mobile' => '91-9876543210',
            'status' => 'active',
        ]);

        $this->assertSame('919876543210', $otherMerchantCustomer->mobile_normalized);

        $this->expectException(QueryException::class);

        $this->customerService()->create($firstMerchant, [
            'name' => 'Duplicate Buyer',
            'mobile' => '09876543210',
            'status' => 'active',
        ]);
    }

    public function test_different_indian_mobile_formats_normalize_to_the_same_value(): void
    {
        $normalizer = app(MobileNumberNormalizer::class);
        $formats = [
            '98765 43210',
            '09876543210',
            '+91 98765 43210',
            '91-9876543210',
        ];

        $normalized = array_map(
            fn (string $mobile): array => $normalizer->normalize($mobile),
            $formats,
        );

        foreach ($normalized as $row) {
            $this->assertSame('+91', $row['country_code']);
            $this->assertSame('9876543210', $row['mobile']);
            $this->assertSame('919876543210', $row['mobile_normalized']);
        }
    }

    public function test_one_user_can_link_to_customer_records_from_multiple_merchants_and_user_delete_nulls_links(): void
    {
        [$firstMerchant] = $this->merchantFixture('First Link Merchant');
        [$secondMerchant] = $this->merchantFixture('Second Link Merchant');
        $user = $this->userFixture('linked-customer@example.test', '919876543210');

        $first = $this->customerService()->create($firstMerchant, [
            'name' => 'Linked Buyer One',
            'mobile' => '9876543210',
            'user_id' => $user->getKey(),
            'linked_at' => now(),
            'status' => 'active',
        ]);
        $second = $this->customerService()->create($secondMerchant, [
            'name' => 'Linked Buyer Two',
            'mobile' => '9876543210',
            'user_id' => $user->getKey(),
            'linked_at' => now(),
            'status' => 'active',
        ]);

        $this->assertEqualsCanonicalizing(
            [$first->getKey(), $second->getKey()],
            $user->merchantCustomerProfiles()->pluck('id')->all(),
        );

        $user->forceDelete();

        $this->assertNull($first->refresh()->user_id);
        $this->assertNull($second->refresh()->user_id);
    }

    public function test_deleting_merchant_cascades_customers(): void
    {
        [$merchant] = $this->merchantFixture('Cascade Merchant');
        $customer = $this->customerService()->create($merchant, [
            'name' => 'Cascade Buyer',
            'mobile' => '9876543210',
            'status' => 'active',
        ]);

        $merchant->forceDelete();

        $this->assertDatabaseMissing('merchant_customers', [
            'id' => $customer->getKey(),
        ]);
    }

    public function test_orders_allow_walk_in_null_customer_and_customer_deletion_nulls_link(): void
    {
        [$merchant, , $shop] = $this->merchantFixture('Order Customer Merchant');

        $walkIn = $this->createOrder($merchant, $shop, [
            'customer_id' => null,
            'order_number' => 'ORD-WALK-IN',
        ]);

        $this->assertNull($walkIn->customer_id);

        $customer = $this->customerService()->create($merchant, [
            'name' => 'Order Buyer',
            'mobile' => '9876543210',
            'email' => 'buyer@example.test',
            'status' => 'active',
        ]);
        $order = $this->createOrder($merchant, $shop, [
            'order_number' => 'ORD-CUSTOMER',
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
        ]);

        $customer->delete();

        $this->assertNull($order->refresh()->customer_id);
        $this->assertSame('Order Buyer', $order->customer_name);
        $this->assertSame('9876543210', $order->customer_mobile);
        $this->assertSame('buyer@example.test', $order->customer_email);
    }

    public function test_order_customer_snapshot_fields_remain_after_customer_is_edited(): void
    {
        [$merchant, , $shop] = $this->merchantFixture('Snapshot Merchant');
        $customer = $this->customerService()->create($merchant, [
            'name' => 'Original Buyer',
            'mobile' => '9876543210',
            'email' => 'original@example.test',
            'status' => 'active',
        ]);
        $order = $this->createOrder($merchant, $shop, [
            'order_number' => 'ORD-SNAPSHOT',
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
        ]);

        $this->customerService()->update($customer, [
            'name' => 'Updated Buyer',
            'mobile' => '9123456780',
            'email' => 'updated@example.test',
            'status' => 'inactive',
        ]);

        $order->refresh();
        $this->assertSame('Original Buyer', $order->customer_name);
        $this->assertSame('9876543210', $order->customer_mobile);
        $this->assertSame('original@example.test', $order->customer_email);
    }

    public function test_soft_deleted_customers_are_excluded_from_normal_queries(): void
    {
        [$merchant] = $this->merchantFixture('Soft Delete Merchant');
        $customer = $this->customerService()->create($merchant, [
            'name' => 'Soft Deleted Buyer',
            'mobile' => '9876543210',
            'status' => 'active',
        ]);

        $customer->delete();

        $this->assertFalse(MerchantCustomer::query()->whereKey($customer->getKey())->exists());
        $this->assertTrue(MerchantCustomer::withTrashed()->whereKey($customer->getKey())->exists());
    }

    private function customerService(): MerchantCustomerService
    {
        return app(MerchantCustomerService::class);
    }

    /**
     * @return array{0: MerchantProfile, 1: User, 2: Shop}
     */
    private function merchantFixture(string $businessName): array
    {
        $user = $this->userFixture(Str::slug($businessName).'-'.Str::random(6).'@example.test');
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $businessName.' '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => $businessName.' Root',
            'slug' => Str::slug($businessName).'-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $businessName.' Shop',
            'slug' => Str::slug($businessName).'-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return [$merchant, $user, $shop];
    }

    private function userFixture(string $email, ?string $mobile = null): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test User',
            'email' => $email,
            'mobile' => $mobile ?? '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createOrder(MerchantProfile $merchant, Shop $shop, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'created_source' => Order::SOURCE_POS,
            'fulfilment_type' => Order::FULFILMENT_COUNTER,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_PAID,
            'currency_code' => 'INR',
        ], $overrides));
    }
}
