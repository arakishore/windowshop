<?php

namespace Tests\Feature;

use App\Models\MerchantCustomer;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantCustomerCrudTest extends TestCase
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

    public function test_merchant_can_list_search_and_filter_own_customers(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Customer List Merchant');
        [, $otherMerchant] = $this->merchantFixture('Other List Merchant');
        $own = $this->customer($merchant, 'Asha Searchable', '9876543210', 'asha@example.test', MerchantCustomer::STATUS_ACTIVE);
        $inactive = $this->customer($merchant, 'Inactive Buyer', '9876543211', 'inactive@example.test', MerchantCustomer::STATUS_INACTIVE);
        $other = $this->customer($otherMerchant, 'Other Merchant Buyer', '9876543212', 'other@example.test');

        $response = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.index', ['search' => $own->customer_code]));

        $response
            ->assertOk()
            ->assertSee('Asha Searchable')
            ->assertDontSee('Inactive Buyer')
            ->assertDontSee('Other Merchant Buyer');

        $response = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.index', ['status' => MerchantCustomer::STATUS_INACTIVE]));

        $response
            ->assertOk()
            ->assertSee('Inactive Buyer')
            ->assertDontSee('Asha Searchable')
            ->assertDontSee('Other Merchant Buyer');

        $this->assertSame($other->merchant_id, $otherMerchant->getKey());
    }

    public function test_merchant_can_create_view_edit_toggle_and_delete_customer(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Customer Crud Merchant');

        $create = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.store'), [
                'name' => 'Created Customer',
                'mobile' => '+91 98765 43210',
                'email' => 'created@example.test',
                'status' => MerchantCustomer::STATUS_ACTIVE,
            ]);

        $customer = MerchantCustomer::query()->where('merchant_id', $merchant->getKey())->firstOrFail();

        $create->assertRedirect(route('merchant.customers.show', $customer));
        $this->assertSame('CUS-000001', $customer->customer_code);
        $this->assertSame('919876543210', $customer->mobile_normalized);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.show', $customer))
            ->assertOk()
            ->assertSee('Created Customer')
            ->assertSee('Customer Summary');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->put(route('merchant.customers.update', $customer), [
                'name' => 'Edited Customer',
                'mobile' => '09876543210',
                'email' => 'edited@example.test',
                'status' => MerchantCustomer::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('merchant.customers.show', $customer));

        $this->assertSame('Edited Customer', $customer->refresh()->name);
        $this->assertSame('edited@example.test', $customer->email);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.deactivate', $customer))
            ->assertRedirect();
        $this->assertSame(MerchantCustomer::STATUS_INACTIVE, $customer->refresh()->status);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.activate', $customer))
            ->assertRedirect();
        $this->assertSame(MerchantCustomer::STATUS_ACTIVE, $customer->refresh()->status);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->delete(route('merchant.customers.destroy', $customer))
            ->assertRedirect(route('merchant.customers.index'));

        $this->assertSoftDeleted('merchant_customers', ['id' => $customer->getKey()]);
    }

    public function test_merchant_can_bulk_update_customers_and_scope_to_own_records(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Bulk Customer Merchant');
        [, $otherMerchant] = $this->merchantFixture('Other Bulk Customer Merchant');
        $first = $this->customer($merchant, 'Bulk First', '9876543210', 'bulk-first@example.test', MerchantCustomer::STATUS_INACTIVE);
        $second = $this->customer($merchant, 'Bulk Second', '9876543211', 'bulk-second@example.test', MerchantCustomer::STATUS_INACTIVE);
        $other = $this->customer($otherMerchant, 'Bulk Other', '9876543212', 'bulk-other@example.test', MerchantCustomer::STATUS_INACTIVE);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.bulk-action'), [
                'action' => 'mark_active',
                'customer_ids' => [$first->getKey(), $second->getKey(), $other->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame(MerchantCustomer::STATUS_ACTIVE, $first->refresh()->status);
        $this->assertSame(MerchantCustomer::STATUS_ACTIVE, $second->refresh()->status);
        $this->assertSame(MerchantCustomer::STATUS_INACTIVE, $other->refresh()->status);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.bulk-action'), [
                'action' => 'mark_inactive',
                'customer_ids' => [$first->getKey(), $other->getKey()],
            ])
            ->assertRedirect();

        $this->assertSame(MerchantCustomer::STATUS_INACTIVE, $first->refresh()->status);
        $this->assertSame(MerchantCustomer::STATUS_INACTIVE, $other->refresh()->status);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.bulk-action'), [
                'action' => 'delete',
                'customer_ids' => [$first->getKey(), $other->getKey()],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('merchant_customers', ['id' => $first->getKey()]);
        $this->assertFalse($other->refresh()->trashed());
    }

    public function test_customer_show_includes_summary_and_order_history(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Customer Summary Merchant');
        $customer = $this->customer($merchant, 'History Buyer', '9876543210', 'history@example.test');
        $this->order($merchant, $shop, $customer, 'ORD-HISTORY-001', 250);
        $this->order($merchant, $shop, $customer, 'ORD-HISTORY-002', 150);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'orders']))
            ->assertOk()
            ->assertSee('History Buyer')
            ->assertSee('INR 400.00')
            ->assertSee('ORD-HISTORY-001')
            ->assertSee('ORD-HISTORY-002');
    }

    public function test_merchant_can_manage_customer_addresses_and_defaults(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Address Merchant');
        $customer = $this->customer($merchant, 'Address Buyer', '9876543210', 'address@example.test');
        [$countryId, $stateId, $cityId] = $this->locationFixture();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.addresses.store', $customer), [
                'label' => 'Home',
                'recipient_name' => 'Address Buyer',
                'recipient_mobile_country_code' => '+91',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Line One',
                'address_line_2' => 'Line Two',
                'landmark' => 'Near Park',
                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,
                'postal_code' => '422001',
                'is_default_shipping' => 1,
                'is_default_billing' => 1,
                'status' => \App\Models\MerchantCustomerAddress::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']));

        $first = $customer->addresses()->firstOrFail();
        $this->assertSame('Home', $first->label);
        $this->assertTrue($first->is_default_shipping);
        $this->assertTrue($first->is_default_billing);
        $this->assertSame('919876543210', $first->recipient_mobile_normalized);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.addresses.store', $customer), [
                'label' => 'Office',
                'recipient_name' => 'Address Buyer',
                'recipient_mobile_country_code' => '+91',
                'recipient_mobile' => '9876543211',
                'address_line_1' => 'Office Line',
                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,
                'postal_code' => '422002',
                'is_default_shipping' => 1,
                'status' => \App\Models\MerchantCustomerAddress::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']));

        $this->assertFalse($first->refresh()->is_default_shipping);
        $this->assertTrue($first->is_default_billing);

        $office = $customer->addresses()->where('label', 'Office')->firstOrFail();
        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']))
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Office')
            ->assertSee('Shipping')
            ->assertSee('Billing');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->put(route('merchant.customers.addresses.update', [$customer, $office]), [
                'label' => 'Office Updated',
                'recipient_name' => 'Office Recipient',
                'recipient_mobile_country_code' => '+91',
                'recipient_mobile' => '9876543212',
                'address_line_1' => 'Updated Office Line',
                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,
                'postal_code' => '422003',
                'is_default_billing' => 1,
                'status' => \App\Models\MerchantCustomerAddress::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']));

        $this->assertSame('Office Updated', $office->refresh()->label);
        $this->assertTrue($office->is_default_billing);
        $this->assertFalse($first->refresh()->is_default_billing);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->delete(route('merchant.customers.addresses.destroy', [$customer, $office]))
            ->assertRedirect(route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']));

        $this->assertSoftDeleted('merchant_customer_addresses', ['id' => $office->getKey()]);
    }

    public function test_merchant_cannot_manage_another_customers_address(): void
    {
        [$user, , $shop] = $this->merchantFixture('Address Scope Merchant');
        [, $otherMerchant] = $this->merchantFixture('Other Address Scope Merchant');
        $otherCustomer = $this->customer($otherMerchant, 'Other Address Buyer', '9876543210', 'other-address@example.test');
        $address = $otherCustomer->addresses()->create([
            'label' => 'Private',
            'recipient_name' => 'Private Buyer',
            'recipient_mobile_country_code' => '+91',
            'recipient_mobile' => '9876543210',
            'recipient_mobile_normalized' => '919876543210',
            'address_line_1' => 'Private Line',
            'status' => \App\Models\MerchantCustomerAddress::STATUS_ACTIVE,
        ]);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.addresses.edit', [$otherCustomer, $address]))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->delete(route('merchant.customers.addresses.destroy', [$otherCustomer, $address]))
            ->assertNotFound();

        $this->assertFalse($address->refresh()->trashed());
    }

    public function test_mobile_lookup_reports_existing_customer_for_active_merchant_only(): void
    {
        [$user, $merchant, $shop] = $this->merchantFixture('Lookup Merchant');
        [, $otherMerchant] = $this->merchantFixture('Other Lookup Merchant');
        $customer = $this->customer($merchant, 'Lookup Buyer', '9876543210', 'lookup@example.test');
        $this->customer($otherMerchant, 'Other Lookup Buyer', '9123456780', 'other-lookup@example.test');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->getJson(route('merchant.customers.mobile-lookup', ['mobile' => '+91 98765 43210']))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('customer.name', 'Lookup Buyer')
            ->assertJsonPath('customer.customer_code', $customer->customer_code);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->getJson(route('merchant.customers.mobile-lookup', ['mobile' => '9123456780']))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('customer', null);
    }

    public function test_merchant_cannot_access_another_merchants_customers(): void
    {
        [$user, , $shop] = $this->merchantFixture('Scoped Merchant');
        [, $otherMerchant] = $this->merchantFixture('Other Scoped Merchant');
        $otherCustomer = $this->customer($otherMerchant, 'Private Customer', '9876543210', 'private@example.test');

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.customers.show', $otherCustomer))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->put(route('merchant.customers.update', $otherCustomer), [
                'name' => 'Attack',
                'mobile' => '9876543219',
                'status' => MerchantCustomer::STATUS_ACTIVE,
            ])
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.customers.deactivate', $otherCustomer))
            ->assertNotFound();

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->delete(route('merchant.customers.destroy', $otherCustomer))
            ->assertNotFound();

        $this->assertFalse($otherCustomer->refresh()->trashed());
    }

    /**
     * @return array{0: User, 1: MerchantProfile, 2: Shop}
     */
    private function merchantFixture(string $businessName): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $businessName,
            'email' => Str::slug($businessName).'-'.Str::random(6).'@example.test',
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

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $businessName,
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

        return [$user, $merchant, $shop];
    }

    private function customer(
        MerchantProfile $merchant,
        string $name,
        string $mobile,
        string $email,
        string $status = MerchantCustomer::STATUS_ACTIVE,
    ): MerchantCustomer {
        return MerchantCustomer::query()->create([
            'merchant_id' => $merchant->getKey(),
            'customer_code' => 'CUS-'.Str::upper(Str::random(6)),
            'name' => $name,
            'mobile_country_code' => '+91',
            'mobile' => $mobile,
            'mobile_normalized' => '91'.$mobile,
            'email' => $email,
            'status' => $status,
        ]);
    }

    private function order(MerchantProfile $merchant, Shop $shop, MerchantCustomer $customer, string $number, int $total): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'created_source' => Order::SOURCE_POS,
            'fulfilment_type' => Order::FULFILMENT_COUNTER,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_PAID,
            'currency_code' => 'INR',
            'grand_total' => $total,
            'amount_paid' => $total,
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
        ]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function locationFixture(): array
    {
        $countryId = (int) DB::table('loc_countries')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'India',
            'iso2' => 'IN',
            'iso3' => 'IND',
            'phonecode' => '91',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $stateId = (int) DB::table('loc_states')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'country_id' => $countryId,
            'country_code' => 'IN',
            'name' => 'Maharashtra',
            'iso2' => 'MH',
            'iso3166_2' => 'IN-MH',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = (int) DB::table('loc_cities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'country_id' => $countryId,
            'state_id' => $stateId,
            'name' => 'Nashik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$countryId, $stateId, $cityId];
    }
}
