<?php

namespace Tests\Feature;

use App\Models\MerchantCustomer;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Checkout\CheckoutPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontCustomerAccountFoundationTest extends TestCase
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

    public function test_guest_is_redirected_to_storefront_login(): void
    {
        $this->get(route('storefront.account'))
            ->assertRedirect(route('storefront.login'));

        $this->get(route('storefront.account.profile'))
            ->assertRedirect(route('storefront.login'));

        $this->get(route('storefront.account.addresses'))
            ->assertRedirect(route('storefront.login'));
    }

    public function test_logged_in_customer_can_view_dashboard_profile_and_orders_placeholder(): void
    {
        $customer = $this->customerUser('kishore@example.test', 'Kishore Kumar', '9876543210');
        $roleId = $this->assignRole($customer, 'customer');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account'))
            ->assertOk()
            ->assertSee('Welcome back, Kishore Kumar')
            ->assertSee('Total Orders')
            ->assertSee('Saved Addresses')
            ->assertSee(route('storefront.account.profile'), false)
            ->assertSee(route('storefront.account.addresses'), false)
            ->assertSee(route('storefront.account.orders'), false)
            ->assertSee(route('storefront.account.wishlist'), false);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.profile'))
            ->assertOk()
            ->assertSee('Your Details')
            ->assertSee('Kishore Kumar')
            ->assertSee('9876543210')
            ->assertSee('kishore@example.test')
            ->assertSee('Profile editing will be added separately');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders'))
            ->assertOk()
            ->assertSee('My Orders will be available here.')
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Request Return')
            ->assertDontSee('Request Exchange');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.wishlist'))
            ->assertOk()
            ->assertSee('Wishlist will be available here.')
            ->assertSee('Saved favourite products will appear here')
            ->assertDontSee('Request Return')
            ->assertDontSee('Request Exchange');
    }

    public function test_addresses_page_shows_only_addresses_for_authenticated_storefront_customer(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        [$merchant, , $shop] = $this->merchantFixture('Scoped Merchant');
        $customer = $this->customerUser('own-address@example.test', 'Own Customer', '9876543210');
        $otherCustomer = $this->customerUser('other-address@example.test', 'Other Customer', '9123456780');
        $roleId = $this->assignRole($customer, 'customer');

        $ownMerchantCustomer = $this->merchantCustomer($merchant, $customer, 'Own Buyer', '9876543210');
        $otherMerchantCustomer = $this->merchantCustomer($merchant, $otherCustomer, 'Other Buyer', '9123456780');

        $ownAddress = $this->address($customer, [
            'label' => 'Home',
            'recipient_name' => 'Own Recipient',
            'recipient_mobile' => '9876543210',
            'address_line_1' => '12 Own Street',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'postal_code' => '600001',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->address($otherCustomer, [
            'label' => 'Work',
            'recipient_name' => 'Other Recipient',
            'recipient_mobile' => '9123456780',
            'address_line_1' => '99 Other Street',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'postal_code' => '600002',
        ]);
        $this->createOrder($merchant, $shop, $ownMerchantCustomer, 'ORD-OWN-ACCOUNT');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.addresses', ['customer_id' => $otherCustomer->getKey()]))
            ->assertOk()
            ->assertSee('12 Own Street')
            ->assertSee('Own Recipient')
            ->assertSee('Default Delivery')
            ->assertSee('Default Billing')
            ->assertSee('600001')
            ->assertDontSee('99 Other Street')
            ->assertDontSee('Other Recipient')
            ->assertDontSee('600002');

        $checkoutAddresses = app(CheckoutPageService::class)->addressesFor($customer);

        $this->assertSame([$ownAddress->getKey()], $checkoutAddresses->pluck('id')->all());
    }

    public function test_backoffice_user_with_non_customer_active_role_cannot_use_customer_account(): void
    {
        $user = $this->customerUser('merchant-role@example.test', 'Merchant Role', '9000011111');
        $this->assignRole($user, 'customer');
        $merchantRoleId = $this->assignRole($user, 'merchant');

        $this->actingAs($user)
            ->withSession(['active_role_id' => $merchantRoleId])
            ->get(route('storefront.account'))
            ->assertRedirect(route('storefront.login'));
    }

    public function test_existing_storefront_logout_still_clears_customer_session(): void
    {
        $customer = $this->customerUser('logout-account@example.test', 'Logout Customer', '9000022222');
        $roleId = $this->assignRole($customer, 'customer');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.logout'))
            ->assertRedirect(route('storefront.home'));

        $this->assertGuest();
    }

    private function customerUser(string $email, string $name, string $mobile): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->forceFill(['mobile' => $mobile])->save();

        return $user->refresh();
    }

    private function assignRole(User $user, string $slug): int
    {
        $roleId = $this->roleId($slug);

        DB::table('auth_user_roles')->updateOrInsert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $roleId;
    }

    private function roleId(string $slug): int
    {
        DB::table('auth_roles')->updateOrInsert([
            'slug' => $slug,
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'description' => Str::headline($slug).' role',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('auth_roles')->where('slug', $slug)->value('id');
    }

    /**
     * @return array{0: MerchantProfile, 1: User, 2: Shop}
     */
    private function merchantFixture(string $businessName): array
    {
        $user = $this->customerUser(Str::slug($businessName).'-'.Str::random(6).'@example.test', $businessName.' Owner', '91'.random_int(10000000, 99999999));
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

        return [$merchant, $user, $shop];
    }

    private function merchantCustomer(MerchantProfile $merchant, User $user, string $name, string $mobile): MerchantCustomer
    {
        return MerchantCustomer::query()->create([
            'merchant_id' => $merchant->getKey(),
            'customer_id' => $this->globalCustomer($user)->getKey(),
            'customer_code' => 'CUS-'.Str::upper(Str::random(8)),
            'status' => MerchantCustomer::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function address(User $customer, array $overrides): CustomerAddress
    {
        return CustomerAddress::query()->create(array_merge([
            'customer_id' => $this->globalCustomer($customer)->getKey(),
            'label' => 'Home',
            'recipient_name' => 'Recipient',
            'recipient_mobile_country_code' => '+91',
            'recipient_mobile' => '9876543210',
            'recipient_mobile_normalized' => '919876543210',
            'address_line_1' => 'Main Street',
            'postal_code' => '600001',
            'status' => CustomerAddress::STATUS_ACTIVE,
        ], $overrides));
    }

    private function globalCustomer(User $user): Customer
    {
        return Customer::query()->firstOrCreate([
            'user_id' => $user->getKey(),
        ], [
            'name' => $user->name,
            'mobile_country_code' => '+91',
            'mobile' => $user->mobile,
            'mobile_normalized' => '91'.$user->mobile,
            'email' => $user->email,
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }

    private function createOrder(MerchantProfile $merchant, Shop $shop, MerchantCustomer $customer, string $number): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->getKey(),
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_PENDING,
            'currency_code' => 'INR',
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
            'name' => 'Tamil Nadu',
            'country_id' => $countryId,
            'country_code' => 'IN',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = (int) DB::table('loc_cities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Chennai',
            'state_id' => $stateId,
            'country_id' => $countryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$countryId, $stateId, $cityId];
    }
}
