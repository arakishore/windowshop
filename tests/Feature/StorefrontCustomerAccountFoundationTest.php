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
            ->assertSee('Mobile and email changes will be handled through verification later')
            ->assertSee('name="name"', false)
            ->assertSee('readonly', false);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.orders'))
            ->assertOk()
            ->assertSee('Continue Shopping')
            ->assertDontSee('Cancel Order')
            ->assertDontSee('Request Return')
            ->assertDontSee('Request Exchange');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.wishlist'))
            ->assertOk()
            ->assertSee('Your wishlist is empty.')
            ->assertSee('Continue Shopping')
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

    public function test_customer_can_add_address_and_first_address_becomes_delivery_and_billing_default(): void
    {
        $this->locationFixture();
        $this->postalCodeFixture('600001', 'Chennai', 'Tamil Nadu');
        $customer = $this->customerUser('add-address@example.test', 'Add Customer', '9876500001');
        $roleId = $this->assignRole($customer, 'customer');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.addresses.store'), $this->addressPayload([
                'address_type' => 'Other',
                'label' => 'Parents Home',
                'address_label' => 'Parents Home',
                'recipient_name' => 'Parent Customer',
                'recipient_mobile' => '9876500002',
                'address_line_1' => '45 Parent Road',
                'city_name' => 'Cidco Nashik',
            ]))
            ->assertRedirect(route('storefront.account.addresses'));

        $address = CustomerAddress::query()->with('city')->firstOrFail();

        $this->assertSame($this->globalCustomer($customer)->getKey(), $address->customer_id);
        $this->assertSame('Parents Home', $address->label);
        $this->assertSame('Cidco Nashik', $address->city?->name);
        $this->assertSame('919876500002', $address->recipient_mobile_normalized);
        $this->assertTrue((bool) $address->is_default_shipping);
        $this->assertTrue((bool) $address->is_default_billing);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.addresses'))
            ->assertOk()
            ->assertSee('45 Parent Road')
            ->assertSee('Default Delivery')
            ->assertSee('Default Billing');
    }

    public function test_second_address_does_not_duplicate_defaults_and_customer_can_change_each_default(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $this->postalCodeFixture('600001', 'Chennai', 'Tamil Nadu');
        $customer = $this->customerUser('defaults-address@example.test', 'Defaults Customer', '9876500011');
        $roleId = $this->assignRole($customer, 'customer');
        $first = $this->address($customer, [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.addresses.store'), $this->addressPayload([
                'label' => 'Office',
                'recipient_mobile' => '9876500012',
                'address_line_1' => '77 Office Road',
            ]))
            ->assertRedirect(route('storefront.account.addresses'));

        $second = CustomerAddress::query()->where('label', 'Office')->firstOrFail();

        $this->assertFalse((bool) $second->is_default_shipping);
        $this->assertFalse((bool) $second->is_default_billing);
        $this->assertSame(1, CustomerAddress::query()->where('customer_id', $first->customer_id)->where('is_default_shipping', true)->count());
        $this->assertSame(1, CustomerAddress::query()->where('customer_id', $first->customer_id)->where('is_default_billing', true)->count());

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.addresses.default-delivery', $second))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertFalse((bool) $first->refresh()->is_default_shipping);
        $this->assertTrue((bool) $second->refresh()->is_default_shipping);
        $this->assertTrue((bool) $first->is_default_billing);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.addresses.default-billing', $second))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertFalse((bool) $first->refresh()->is_default_billing);
        $this->assertTrue((bool) $second->refresh()->is_default_billing);
        $this->assertSame(1, CustomerAddress::query()->where('customer_id', $first->customer_id)->where('is_default_shipping', true)->count());
        $this->assertSame(1, CustomerAddress::query()->where('customer_id', $first->customer_id)->where('is_default_billing', true)->count());
    }

    public function test_edit_updates_saved_checkout_address_but_not_order_snapshots(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $this->postalCodeFixture('600001', 'Chennai', 'Tamil Nadu');
        [$merchant, , $shop] = $this->merchantFixture('Snapshot Merchant');
        $customer = $this->customerUser('snapshot-address@example.test', 'Snapshot Customer', '9876500021');
        $roleId = $this->assignRole($customer, 'customer');
        $merchantCustomer = $this->merchantCustomer($merchant, $customer, 'Snapshot Customer', '9876500021');
        $address = $this->address($customer, [
            'recipient_name' => 'Original Recipient',
            'address_line_1' => 'Original Road',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $order = $this->createOrder($merchant, $shop, $merchantCustomer, 'ORD-SNAPSHOT-ACCOUNT', [
            'shipping_recipient_name' => 'Original Recipient',
            'shipping_address_line_1' => 'Original Road',
            'billing_recipient_name' => 'Original Recipient',
            'billing_address_line_1' => 'Original Road',
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->put(route('storefront.account.addresses.update', $address), $this->addressPayload([
                'label' => 'Updated Label',
                'recipient_name' => 'Updated Recipient',
                'address_line_1' => 'Updated Road',
                'is_default_shipping' => '1',
                'is_default_billing' => '1',
            ]))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertSame('Updated Road', $address->refresh()->address_line_1);
        $this->assertSame(
            'Updated Road',
            app(CheckoutPageService::class)->addressesFor($customer)->firstWhere('id', $address->getKey())?->address_line_1,
        );
        $this->assertSame('Original Recipient', $order->refresh()->shipping_recipient_name);
        $this->assertSame('Original Road', $order->shipping_address_line_1);
        $this->assertSame('Original Road', $order->billing_address_line_1);
    }

    public function test_customer_can_delete_normal_address_without_changing_defaults(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $customer = $this->customerUser('delete-normal@example.test', 'Delete Normal', '9876500031');
        $roleId = $this->assignRole($customer, 'customer');
        $default = $this->address($customer, [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $normal = $this->address($customer, [
            'label' => 'Temporary',
            'address_line_1' => 'Temporary Road',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->delete(route('storefront.account.addresses.destroy', $normal))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertSoftDeleted('customer_addresses', ['id' => $normal->getKey()]);
        $this->assertTrue((bool) $default->refresh()->is_default_shipping);
        $this->assertTrue((bool) $default->is_default_billing);
    }

    public function test_deleting_default_promotes_another_address_and_deleting_last_leaves_no_defaults(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $customer = $this->customerUser('delete-default@example.test', 'Delete Default', '9876500041');
        $roleId = $this->assignRole($customer, 'customer');
        $first = $this->address($customer, [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $second = $this->address($customer, [
            'label' => 'Replacement',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->delete(route('storefront.account.addresses.destroy', $first))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertTrue((bool) $second->refresh()->is_default_shipping);
        $this->assertTrue((bool) $second->is_default_billing);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->delete(route('storefront.account.addresses.destroy', $second))
            ->assertRedirect(route('storefront.account.addresses'));

        $globalCustomerId = $this->globalCustomer($customer)->getKey();
        $this->assertSame(0, CustomerAddress::query()->where('customer_id', $globalCustomerId)->count());
        $this->assertSame(0, CustomerAddress::withTrashed()->where('customer_id', $globalCustomerId)->where('is_default_shipping', true)->count());
        $this->assertSame(0, CustomerAddress::withTrashed()->where('customer_id', $globalCustomerId)->where('is_default_billing', true)->count());
    }

    public function test_deleting_billing_default_promotes_delivery_default_when_it_is_the_remaining_address(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $customer = $this->customerUser('split-default@example.test', 'Split Default', '9876500045');
        $roleId = $this->assignRole($customer, 'customer');
        $home = $this->address($customer, [
            'label' => 'Home',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => false,
        ]);
        $work = $this->address($customer, [
            'label' => 'Work',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => false,
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->delete(route('storefront.account.addresses.destroy', $work))
            ->assertRedirect(route('storefront.account.addresses'));

        $this->assertTrue((bool) $home->refresh()->is_default_shipping);
        $this->assertTrue((bool) $home->is_default_billing);
        $this->assertSoftDeleted('customer_addresses', ['id' => $work->getKey()]);
    }

    public function test_customer_cannot_manage_another_customers_address(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        $customer = $this->customerUser('owner-address@example.test', 'Owner Customer', '9876500051');
        $otherCustomer = $this->customerUser('wrong-address@example.test', 'Wrong Customer', '9876500052');
        $roleId = $this->assignRole($customer, 'customer');
        $otherAddress = $this->address($otherCustomer, [
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.addresses.edit', $otherAddress))
            ->assertNotFound();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->put(route('storefront.account.addresses.update', $otherAddress), $this->addressPayload())
            ->assertNotFound();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->post(route('storefront.account.addresses.default-delivery', $otherAddress))
            ->assertNotFound();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->delete(route('storefront.account.addresses.destroy', $otherAddress))
            ->assertNotFound();
    }

    public function test_global_customer_address_is_visible_once_across_multiple_merchant_links(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        [$firstMerchant] = $this->merchantFixture('First Account Merchant');
        [$secondMerchant] = $this->merchantFixture('Second Account Merchant');
        $customer = $this->customerUser('global-address@example.test', 'Global Address', '9876500061');
        $roleId = $this->assignRole($customer, 'customer');

        $this->merchantCustomer($firstMerchant, $customer, 'Global Address', '9876500061');
        $this->merchantCustomer($secondMerchant, $customer, 'Global Address', '9876500061');
        $this->address($customer, [
            'label' => 'Global Home',
            'address_line_1' => 'One Global Road',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.addresses'))
            ->assertOk()
            ->assertSee('One Global Road');

        $this->assertSame(1, CustomerAddress::query()->where('customer_id', $this->globalCustomer($customer)->getKey())->count());
    }

    public function test_customer_can_update_only_own_profile_name_and_session_remains_valid(): void
    {
        $customer = $this->customerUser('profile-name@example.test', 'Original Name', '9876500071');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->put(route('storefront.account.profile.update'), [
                'name' => '  Updated Name  ',
                'mobile' => '9000000000',
                'email' => 'changed@example.test',
            ])
            ->assertRedirect(route('storefront.account.profile'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame('Updated Name', $globalCustomer->refresh()->name);
        $this->assertSame('Updated Name', $customer->refresh()->name);
        $this->assertSame('9876500071', $globalCustomer->mobile);
        $this->assertSame('profile-name@example.test', $globalCustomer->email);
        $this->assertSame('9876500071', $customer->mobile);
        $this->assertSame('profile-name@example.test', $customer->email);

        $this->actingAs($customer->refresh())
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account'))
            ->assertOk()
            ->assertSee('Welcome back, Updated Name');
    }

    public function test_profile_name_update_rejects_empty_or_too_long_name(): void
    {
        $customer = $this->customerUser('profile-invalid@example.test', 'Valid Name', '9876500072');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->from(route('storefront.account.profile'))
            ->put(route('storefront.account.profile.update'), ['name' => '   '])
            ->assertRedirect(route('storefront.account.profile'))
            ->assertSessionHasErrors('name');

        $this->assertSame('Valid Name', $globalCustomer->refresh()->name);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->from(route('storefront.account.profile'))
            ->put(route('storefront.account.profile.update'), ['name' => str_repeat('A', 151)])
            ->assertRedirect(route('storefront.account.profile'))
            ->assertSessionHasErrors('name');

        $this->assertSame('Valid Name', $globalCustomer->refresh()->name);
    }

    public function test_profile_name_update_cannot_update_another_customer_and_leaves_related_data_unchanged(): void
    {
        [$countryId, $stateId, $cityId] = $this->locationFixture();
        [$merchant, , $shop] = $this->merchantFixture('Profile Merchant');
        $customer = $this->customerUser('profile-owner@example.test', 'Profile Owner', '9876500073');
        $otherCustomer = $this->customerUser('profile-other@example.test', 'Other Profile', '9876500074');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $otherGlobalCustomer = $this->globalCustomer($otherCustomer);
        $merchantCustomer = $this->merchantCustomer($merchant, $customer, 'Profile Owner', '9876500073');
        $merchantSnapshot = $merchantCustomer->refresh()->only(['id', 'merchant_id', 'customer_id', 'customer_code', 'trust_status', 'status']);
        $address = $this->address($customer, [
            'recipient_name' => 'Address Recipient',
            'address_line_1' => 'Profile Address Road',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $addressSnapshot = $address->only([
            'label',
            'recipient_name',
            'recipient_mobile',
            'address_line_1',
            'postal_code',
            'is_default_shipping',
            'is_default_billing',
        ]);
        $order = $this->createOrder($merchant, $shop, $merchantCustomer, 'ORD-PROFILE-SNAPSHOT', [
            'customer_name' => 'Historical Customer Name',
            'shipping_recipient_name' => 'Historical Ship Name',
            'billing_recipient_name' => 'Historical Bill Name',
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->put(route('storefront.account.profile.update'), [
                'name' => 'New Profile Owner',
                'customer_id' => $otherGlobalCustomer->getKey(),
            ])
            ->assertRedirect(route('storefront.account.profile'));

        $this->assertSame('New Profile Owner', $globalCustomer->refresh()->name);
        $this->assertSame('Other Profile', $otherGlobalCustomer->refresh()->name);
        $this->assertSame($merchantSnapshot, $merchantCustomer->refresh()->only(['id', 'merchant_id', 'customer_id', 'customer_code', 'trust_status', 'status']));
        $this->assertSame($addressSnapshot, $address->refresh()->only([
            'label',
            'recipient_name',
            'recipient_mobile',
            'address_line_1',
            'postal_code',
            'is_default_shipping',
            'is_default_billing',
        ]));
        $this->assertSame('Historical Customer Name', $order->refresh()->customer_name);
        $this->assertSame('Historical Ship Name', $order->shipping_recipient_name);
        $this->assertSame('Historical Bill Name', $order->billing_recipient_name);
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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Recipient Customer',
            'recipient_mobile_country_code' => '+91',
            'recipient_mobile' => '9876543210',
            'address_line_1' => 'Main Street',
            'address_line_2' => null,
            'landmark' => null,
            'postal_code' => '600001',
            'is_default_shipping' => '0',
            'is_default_billing' => '0',
        ], $overrides);
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function createOrder(MerchantProfile $merchant, Shop $shop, MerchantCustomer $customer, string $number, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => $number,
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'customer_id' => $customer->customer_id,
            'created_source' => Order::SOURCE_STOREFRONT,
            'fulfilment_type' => Order::FULFILMENT_DELIVERY,
            'order_status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_PENDING,
            'currency_code' => 'INR',
            'customer_name' => $customer->name,
            'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email,
        ], $overrides));
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

    private function postalCodeFixture(string $postalCode, string $district, string $state): void
    {
        DB::table('postal_codes')->insert([
            'source_key' => 'test-'.$postalCode,
            'office_name' => $district.' Head Office',
            'postal_code' => $postalCode,
            'delivery_status' => 'Delivery',
            'shipping_enabled' => true,
            'district' => $district,
            'state' => $state,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
