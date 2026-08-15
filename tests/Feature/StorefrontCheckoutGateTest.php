<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MerchantCustomer;
use App\Models\MerchantCustomerAddress;
use App\Models\MerchantProfile;
use App\Models\PostalCode;
use App\Models\PostalCodeRestriction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Storefront\StorefrontCountryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontCheckoutGateTest extends TestCase
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

    public function test_guest_with_cart_enters_checkout_account_gate(): void
    {
        $fixture = $this->productFixture();
        $this->cartItem($this->guestCart('checkout-guest-token'), $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'checkout-guest-token'])
            ->get(route('storefront.checkout'))
            ->assertRedirect(route('storefront.checkout.account'))
            ->assertSessionHas(CheckoutFlowService::INTENT_SESSION_KEY, true);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'checkout-guest-token'])
            ->get(route('storefront.checkout.account'))
            ->assertOk()
            ->assertSee('Checkout Account')
            ->assertSee('Account')
            ->assertSee('Address')
            ->assertSee(route('storefront.register', ['from' => 'checkout']), false)
            ->assertSee(route('storefront.login.store'), false)
            ->assertSee('data-customer-login-form', false)
            ->assertSee('Please enter your password.');
    }

    public function test_customer_with_cart_reaches_one_page_checkout(): void
    {
        $customer = $this->customerUser('checkout-customer@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Checkout')
            ->assertSee('Delivery Address')
            ->assertSee('Billing Address')
            ->assertSee('Same as delivery address')
            ->assertSee('Delivery Options')
            ->assertSee('Payment Method')
            ->assertSee('Order Summary')
            ->assertSee('Place Order')
            ->assertDontSee('checkout-progress', false)
            ->assertDontSee('Address step')
            ->assertDontSee('name="country_id"', false)
            ->assertSee('id="billing-same-as-delivery"', false)
            ->assertSee('name="billing_same_as_delivery"', false)
            ->assertSee('checked', false);
    }

    public function test_storefront_default_country_resolves_from_system_setting_code_without_numeric_id_assumption(): void
    {
        $usa = $this->country('United States', 'US', 'USA');
        $india = $this->country('India', 'IN', 'IND');
        $this->systemSetting('default_country_code', 'IN');

        $country = app(StorefrontCountryResolver::class)->defaultCountry();

        $this->assertSame($india['country_id'], $country->getKey());
        $this->assertNotSame($usa['country_id'], $country->getKey());
        $this->assertSame('IN', $country->iso2);
    }

    public function test_storefront_default_country_uses_config_fallback_when_setting_is_missing(): void
    {
        config(['location.default_country_code' => 'GB']);
        $greatBritain = $this->country('United Kingdom', 'GB', 'GBR');

        $country = app(StorefrontCountryResolver::class)->defaultCountry();

        $this->assertSame($greatBritain['country_id'], $country->getKey());
        $this->assertSame('GB', $country->iso2);
    }

    public function test_empty_cart_cannot_enter_checkout_for_guest_or_customer(): void
    {
        $this->get(route('storefront.checkout'))
            ->assertRedirect(route('storefront.cart'))
            ->assertSessionHas('error', 'Your cart is empty.');

        $customer = $this->customerUser('empty-customer@example.test');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertRedirect(route('storefront.cart'))
            ->assertSessionHas('error', 'Your cart is empty.');
    }

    public function test_checkout_login_merges_guest_cart_and_redirects_to_address(): void
    {
        $customer = $this->customerUser('merge-login@example.test');
        $fixture = $this->productFixture(price: 100);
        $customerCart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $guestCart = $this->guestCart('login-merge-token');
        $this->cartItem($customerCart, $fixture['variant'], 2);
        $this->cartItem($guestCart, $fixture['variant'], 1);

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'login-merge-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.login.store'), [
            'email' => 'merge-login@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.checkout'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame('3.000', CartItem::query()->where('cart_id', $customerCart->getKey())->where('product_variant_id', $fixture['variant']->getKey())->value('quantity'));
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->getKey()]);
        $this->assertFalse(session()->has(CartResolver::SESSION_TOKEN_KEY));
    }

    public function test_failed_checkout_login_keeps_guest_cart_unchanged(): void
    {
        $this->customerUser('failed-login@example.test');
        $fixture = $this->productFixture();
        $guestCart = $this->guestCart('failed-login-token');
        $item = $this->cartItem($guestCart, $fixture['variant'], 2);

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'failed-login-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->from(route('storefront.checkout.account'))
            ->post(route('storefront.login.store'), [
                'email' => 'failed-login@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('storefront.checkout.account'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('cart_items', ['id' => $item->getKey(), 'cart_id' => $guestCart->getKey()]);
    }

    public function test_normal_login_and_registration_use_normal_destination(): void
    {
        $customer = $this->customerUser('normal-login@example.test');

        $this->post(route('storefront.login.store'), [
            'email' => 'normal-login@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.home'));

        $this->assertAuthenticatedAs($customer);
        auth()->logout();
        session()->flush();

        $this->post(route('storefront.register.store'), [
            'name' => 'Normal',
            'last_name' => 'Register',
            'email' => 'normal-register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('storefront.home'));
    }

    public function test_checkout_registration_preserves_intent_merges_cart_and_redirects_to_address(): void
    {
        $fixture = $this->productFixture();
        $guestCart = $this->guestCart('register-merge-token');
        $this->cartItem($guestCart, $fixture['variant'], 2);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'register-merge-token'])
            ->get(route('storefront.register', ['from' => 'checkout']))
            ->assertOk()
            ->assertSee('data-customer-register-form', false)
            ->assertSee('Passwords do not match.');

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'register-merge-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.register.store'), [
            'name' => 'Checkout',
            'last_name' => 'Register',
            'email' => 'checkout-register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ])->assertRedirect(route('storefront.checkout'));

        $user = User::query()->where('email', 'checkout-register@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('carts', ['id' => $guestCart->getKey(), 'user_id' => $user->getKey(), 'session_token' => null]);
        $this->assertFalse(session()->has(CartResolver::SESSION_TOKEN_KEY));
    }

    public function test_merge_keeps_different_variants_separate(): void
    {
        $customer = $this->customerUser('variants@example.test');
        $fixture = $this->productFixture();
        $otherVariant = ProductVariant::query()->create([
            'product_id' => $fixture['product']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Large',
            'mrp' => 299,
            'selling_price' => 199,
            'stock_quantity' => 5,
            'is_default' => false,
            'is_sellable' => true,
            'status' => 'active',
        ]);
        $customerCart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($customerCart, $fixture['variant']);
        $this->cartItem($this->guestCart('different-variant-token'), $otherVariant);

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'different-variant-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.login.store'), [
            'email' => 'variants@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.checkout'));

        $this->assertSame(2, CartItem::query()->where('cart_id', $customerCart->getKey())->count());
    }

    public function test_guest_only_cart_becomes_customer_cart_and_does_not_merge_twice(): void
    {
        $customer = $this->customerUser('guest-only@example.test');
        $fixture = $this->productFixture();
        $guestCart = $this->guestCart('guest-only-token');
        $this->cartItem($guestCart, $fixture['variant'], 1);

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'guest-only-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.login.store'), [
            'email' => 'guest-only@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.checkout'));

        $this->assertDatabaseHas('carts', ['id' => $guestCart->getKey(), 'user_id' => $customer->getKey(), 'session_token' => null]);
        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk();

        $this->assertSame('1.000', CartItem::query()->where('cart_id', $guestCart->getKey())->value('quantity'));
    }

    public function test_empty_guest_cart_does_not_change_existing_customer_cart(): void
    {
        $customer = $this->customerUser('empty-guest@example.test');
        $fixture = $this->productFixture();
        $customerCart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($customerCart, $fixture['variant'], 2);
        $guestCart = $this->guestCart('empty-guest-token');

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'empty-guest-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.login.store'), [
            'email' => 'empty-guest@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.checkout'));

        $this->assertDatabaseMissing('carts', ['id' => $guestCart->getKey()]);
        $this->assertSame('2.000', CartItem::query()->where('cart_id', $customerCart->getKey())->value('quantity'));
    }

    public function test_guest_checkout_redirects_to_account_gate(): void
    {
        $fixture = $this->productFixture();
        $this->cartItem($this->guestCart('address-guest-token'), $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'address-guest-token'])
            ->get(route('storefront.checkout'))
            ->assertRedirect(route('storefront.checkout.account'))
            ->assertSessionHas(CheckoutFlowService::INTENT_SESSION_KEY, true);
    }

    public function test_old_checkout_address_route_redirects_to_checkout(): void
    {
        $this->get('/checkout/address')
            ->assertRedirect(route('storefront.checkout'));
    }

    public function test_saved_addresses_are_displayed_and_default_is_selected(): void
    {
        $customer = $this->customerUser('saved-address@example.test');
        $fixture = $this->productFixture(price: 349);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $home = $this->customerAddress($customer, $fixture['merchant'], [
            'label' => 'Home',
            'recipient_name' => 'Kishore Home',
            'address_line_1' => 'Home Road',
            'postal_code' => '422009',
            'is_default_shipping' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Kishore Home')
            ->assertSee('Home Road')
            ->assertSee('PIN 422009')
            ->assertSee('Fast Checkout')
            ->assertSee('name="address_id" value="'.$home->getKey().'"', false);
    }

    public function test_add_address_validation_uses_postal_code_master(): void
    {
        $customer = $this->customerUser('address-validation@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $locations = $this->indiaLocation();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->from(route('storefront.checkout'))
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Address Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $locations['country_id'],
                'address_line_1' => 'Main Road',
                'postal_code' => '999999',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHasErrors('postal_code');
    }

    public function test_customer_can_add_and_update_checkout_address(): void
    {
        $customer = $this->customerUser('address-create@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $locations = $this->indiaLocation();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Address Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $locations['country_id'],
                'address_line_1' => 'Main Road',
                'postal_code' => '422009',
                'is_default_shipping' => '1',
            ])
            ->assertRedirect(route('storefront.checkout'));

        $address = MerchantCustomerAddress::query()->where('recipient_name', 'Address Customer')->firstOrFail();
        $this->assertDatabaseHas('merchant_customer_addresses', [
            'id' => $address->getKey(),
            'postal_code' => '422009',
            'is_default_shipping' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->patch(route('storefront.checkout.addresses.update', $address), [
                'label' => 'Work',
                'recipient_name' => 'Updated Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $locations['country_id'],
                'address_line_1' => 'Updated Road',
                'postal_code' => '422009',
            ])
            ->assertRedirect(route('storefront.checkout'));

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertSee('Updated Customer')
            ->assertSee('Updated Road');
    }

    public function test_customer_can_add_checkout_address_over_ajax(): void
    {
        $customer = $this->customerUser('address-ajax-create@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Ajax Address Customer',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Ajax Road',
                'postal_code' => '422009',
                'address_context' => 'delivery',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Delivery address added.');

        $address = MerchantCustomerAddress::query()->where('recipient_name', 'Ajax Address Customer')->firstOrFail();
        $this->assertSame($address->getKey(), session(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY));
    }

    public function test_customer_can_update_checkout_address_over_ajax(): void
    {
        $customer = $this->customerUser('address-ajax-update@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('storefront.checkout.addresses.update', $address), [
                'label' => 'Work',
                'recipient_name' => 'Ajax Updated Customer',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Ajax Updated Road',
                'postal_code' => '422009',
                'address_context' => 'delivery',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Delivery address updated.');

        $this->assertDatabaseHas('merchant_customer_addresses', [
            'id' => $address->getKey(),
            'recipient_name' => 'Ajax Updated Customer',
            'address_line_1' => 'Ajax Updated Road',
        ]);
    }

    public function test_customer_cannot_select_or_place_order_with_another_customers_address(): void
    {
        $customer = $this->customerUser('address-owner@example.test');
        $other = $this->customerUser('address-other@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $otherAddress = $this->customerAddress($other, $fixture['merchant'], [
            'postal_code' => '422009',
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.addresses.select'), [
                'address_id' => $otherAddress->getKey(),
            ])
            ->assertNotFound();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $otherAddress->getKey(),
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
                'browser_total' => '1.00',
            ])
            ->assertNotFound();
    }

    public function test_order_summary_uses_current_cart_totals_and_ignores_submitted_totals(): void
    {
        $customer = $this->customerUser('summary-total@example.test');
        $fixture = $this->productFixture(price: 250);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant'], 2);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('500.00');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
                'browser_total' => '1.00',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHas('info', 'Order placement will be enabled in the next checkout step.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_billing_same_as_delivery_resolves_to_selected_delivery_address(): void
    {
        $customer = $this->customerUser('billing-same@example.test');
        $fixture = $this->productFixture(price: 250);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHas(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, true);

        $this->assertFalse(session()->has(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY));
    }

    public function test_unchecking_billing_same_as_delivery_shows_billing_selection(): void
    {
        $customer = $this->customerUser('billing-toggle@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->customerAddress($customer, $fixture['merchant'], ['label' => 'Office']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.billing.same'), [
                'billing_same_as_delivery' => '0',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHas(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY => false,
            ])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Saved Billing Addresses')
            ->assertSee('+ Add New Billing Address')
            ->assertSee('Office');
    }

    public function test_default_billing_address_is_preselected_when_billing_is_separate(): void
    {
        $customer = $this->customerUser('billing-default@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $delivery = $this->customerAddress($customer, $fixture['merchant'], [
            'label' => 'Home',
            'is_default_shipping' => true,
        ]);
        $billing = $this->customerAddress($customer, $fixture['merchant'], [
            'label' => 'Office',
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY => $delivery->getKey(),
                CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY => false,
            ])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Default Billing')
            ->assertSee('name="selected_billing_address_visual" value="'.$billing->getKey().'" checked', false);
    }

    public function test_customer_can_add_new_billing_address_with_existing_pin_validation(): void
    {
        $customer = $this->customerUser('billing-create@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $locations = $this->indiaLocation();

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY => false,
            ])
            ->post(route('storefront.checkout.billing-addresses.store'), [
                'label' => 'Work',
                'recipient_name' => 'Billing Customer',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Billing Road',
                'postal_code' => '422009',
                'is_default_billing' => '1',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHas(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false);

        $address = MerchantCustomerAddress::query()->where('recipient_name', 'Billing Customer')->firstOrFail();
        $this->assertSame($address->getKey(), session(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY));
        $this->assertDatabaseHas('merchant_customer_addresses', [
            'id' => $address->getKey(),
            'country_id' => $locations['country_id'],
            'state_id' => $locations['state_id'],
            'city_id' => $locations['city_id'],
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);
    }

    public function test_customer_can_add_billing_address_over_ajax(): void
    {
        $customer = $this->customerUser('billing-ajax-create@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY => false,
            ])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson(route('storefront.checkout.billing-addresses.store'), [
                'label' => 'Work',
                'recipient_name' => 'Ajax Billing Customer',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Ajax Billing Road',
                'postal_code' => '422009',
                'is_default_billing' => '1',
                'address_context' => 'billing',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Billing address added.');

        $address = MerchantCustomerAddress::query()->where('recipient_name', 'Ajax Billing Customer')->firstOrFail();
        $this->assertSame($address->getKey(), session(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY));
    }

    public function test_new_billing_address_reuses_india_pin_validation(): void
    {
        $customer = $this->customerUser('billing-invalid-pin@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY => false,
            ])
            ->from(route('storefront.checkout'))
            ->post(route('storefront.checkout.billing-addresses.store'), [
                'label' => 'Work',
                'recipient_name' => 'Billing Invalid',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Billing Road',
                'postal_code' => '999999',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHasErrors('postal_code');
    }

    public function test_customer_cannot_select_another_customers_billing_address(): void
    {
        $customer = $this->customerUser('billing-owner@example.test');
        $other = $this->customerUser('billing-other@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $otherAddress = $this->customerAddress($other, $fixture['merchant']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.billing-addresses.select'), [
                'billing_address_id' => $otherAddress->getKey(),
            ])
            ->assertNotFound();
    }

    public function test_delivery_and_billing_addresses_can_be_different(): void
    {
        $customer = $this->customerUser('billing-different@example.test');
        $fixture = $this->productFixture(price: 250);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $delivery = $this->customerAddress($customer, $fixture['merchant'], [
            'label' => 'Home',
            'postal_code' => '422009',
            'is_default_shipping' => true,
        ]);
        $billing = $this->customerAddress($customer, $fixture['merchant'], [
            'label' => 'Office',
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $delivery->getKey(),
                'billing_same_as_delivery' => '0',
                'billing_address_id' => $billing->getKey(),
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHas(CheckoutPageService::SELECTED_ADDRESS_SESSION_KEY, $delivery->getKey())
            ->assertSessionHas(CheckoutPageService::BILLING_SAME_AS_DELIVERY_SESSION_KEY, false)
            ->assertSessionHas(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY, $billing->getKey());
    }

    public function test_backend_default_country_requires_india_pin_and_ignores_tampered_country_id(): void
    {
        $customer = $this->customerUser('country-aware@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $india = $this->indiaLocation();
        $usa = $this->country('United States', 'US', 'USA');
        $this->postalCode('422009');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->from(route('storefront.checkout'))
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'India Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $india['country_id'],
                'address_line_1' => 'Main Road',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHasErrors('postal_code');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Work',
                'recipient_name' => 'Non India Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $usa['country_id'],
                'address_line_1' => 'Market Street',
                'postal_code' => '422009',
            ])
            ->assertRedirect(route('storefront.checkout'));

        $this->assertDatabaseHas('merchant_customer_addresses', [
            'recipient_name' => 'Non India Customer',
            'country_id' => $india['country_id'],
            'state_id' => $india['state_id'],
            'city_id' => $india['city_id'],
            'postal_code' => '422009',
        ]);
    }

    public function test_india_default_country_keeps_six_digit_pin_validation(): void
    {
        $customer = $this->customerUser('pin-format@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->from(route('storefront.checkout'))
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'PIN Format Customer',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Main Road',
                'postal_code' => '42200A',
            ])
            ->assertRedirect(route('storefront.checkout'))
            ->assertSessionHasErrors('postal_code');
    }

    public function test_india_pin_lookup_returns_location_shipping_and_shop_restrictions(): void
    {
        $customer = $this->customerUser('pin-lookup@example.test');
        $fixture = $this->productFixture();
        $second = $this->productFixture(price: 299);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $fixture['variant']);
        $this->cartItem($cart, $second['variant']);
        $this->postalCode('422009', shippingEnabled: true);
        $this->restriction('422009', $second['merchant']->getKey(), $second['shop']->getKey(), 'Delivery temporarily unavailable');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->getJson(route('storefront.checkout.postal-code.show', '422009'))
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('city', 'Nashik')
            ->assertJsonPath('state', 'Maharashtra')
            ->assertJsonPath('shipping_enabled', true)
            ->assertJsonFragment(['shop_id' => $fixture['shop']->getKey(), 'available' => true])
            ->assertJsonFragment(['shop_id' => $second['shop']->getKey(), 'available' => false, 'reason' => 'Delivery temporarily unavailable']);
    }

    public function test_shipping_disabled_pin_is_valid_but_not_shipping_enabled(): void
    {
        $customer = $this->customerUser('pin-disabled@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009', shippingEnabled: false);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->getJson(route('storefront.checkout.postal-code.show', '422009'))
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('shipping_enabled', false);
    }

    public function test_inactive_future_and_expired_restrictions_are_ignored(): void
    {
        $customer = $this->customerUser('pin-restrictions@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->restriction('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey(), 'Inactive', status: PostalCodeRestriction::STATUS_INACTIVE);
        $this->restriction('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey(), 'Future', startsAt: now()->addDay());
        $this->restriction('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey(), 'Expired', endsAt: now()->subDay());

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->getJson(route('storefront.checkout.postal-code.show', '422009'))
            ->assertOk()
            ->assertJsonFragment(['shop_id' => $fixture['shop']->getKey(), 'available' => true]);
    }

    public function test_india_address_save_resolves_location_from_pin_and_ignores_spoofed_city_state(): void
    {
        $customer = $this->customerUser('spoof-location@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $india = $this->indiaLocation();
        $otherState = $this->state($india['country_id'], 'Kerala');
        $otherCity = $this->city($india['country_id'], $otherState['state_id'], 'Kochi');
        $this->postalCode('422009');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Spoof Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $india['country_id'],
                'state_id' => $otherState['state_id'],
                'city_id' => $otherCity['city_id'],
                'address_line_1' => 'Main Road',
                'postal_code' => '422009',
            ])
            ->assertRedirect(route('storefront.checkout'));

        $this->assertDatabaseHas('merchant_customer_addresses', [
            'recipient_name' => 'Spoof Customer',
            'country_id' => $india['country_id'],
            'state_id' => $india['state_id'],
            'city_id' => $india['city_id'],
            'postal_code' => '422009',
        ]);
    }

    public function test_valid_pin_is_not_rejected_when_city_master_match_is_missing(): void
    {
        $customer = $this->customerUser('city-missing@example.test');
        $fixture = $this->productFixture();
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $india = $this->indiaLocation(createCity: false);
        $this->postalCode('422009');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.addresses.store'), [
                'label' => 'Home',
                'recipient_name' => 'Missing City Customer',
                'recipient_mobile' => '9876543210',
                'country_id' => $india['country_id'],
                'address_line_1' => 'Main Road',
                'postal_code' => '422009',
            ])
            ->assertRedirect(route('storefront.checkout'));

        $this->assertDatabaseHas('merchant_customer_addresses', [
            'recipient_name' => 'Missing City Customer',
            'country_id' => $india['country_id'],
            'state_id' => $india['state_id'],
            'city_id' => null,
            'postal_code' => '422009',
        ]);
    }

    public function test_inactive_guest_cart_item_is_dropped_during_merge(): void
    {
        $customer = $this->customerUser('inactive-item@example.test');
        $fixture = $this->productFixture();
        $guestCart = $this->guestCart('inactive-item-token');
        $item = $this->cartItem($guestCart, $fixture['variant']);
        $fixture['variant']->forceFill(['status' => 'inactive'])->save();

        $this->withSession([
            CartResolver::SESSION_TOKEN_KEY => 'inactive-item-token',
            CheckoutFlowService::INTENT_SESSION_KEY => true,
        ])->post(route('storefront.login.store'), [
            'email' => 'inactive-item@example.test',
            'password' => 'password',
        ])->assertRedirect(route('storefront.cart'))
            ->assertSessionHas('error', 'Your cart is empty.');

        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);
        $this->assertDatabaseHas('carts', ['id' => $guestCart->getKey(), 'user_id' => $customer->getKey(), 'session_token' => null]);
        $this->assertSame(0, Cart::query()->where('user_id', $customer->getKey())->first()?->items()->count() ?? 0);
    }

    public function test_invalid_checkout_cart_items_are_flagged_without_using_stale_totals(): void
    {
        $customer = $this->customerUser('stale-checkout@example.test');
        $fixture = $this->productFixture(price: 125);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant'], 2);
        $fixture['variant']->forceFill(['stock_quantity' => 0])->save();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Currently unavailable.')
            ->assertSee('250.00');
    }

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, product: Product, variant: ProductVariant}
     */
    private function productFixture(int $price = 199): array
    {
        $merchantUser = User::query()->create([
            'name' => 'Checkout Merchant',
            'email' => 'merchant-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Checkout Merchant '.Str::random(8),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'name' => 'Checkout Category '.Str::random(8),
            'slug' => 'checkout-category-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Checkout Shop '.Str::random(8),
            'slug' => 'checkout-shop-'.Str::random(8),
            'address_line_1' => 'Fixture Street',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $category->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Checkout Product '.Str::random(8),
            'slug' => 'checkout-product-'.Str::random(8),
            'status' => 'active',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Default',
            'mrp' => $price + 100,
            'selling_price' => $price,
            'stock_quantity' => 20,
            'is_default' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        return compact('merchant', 'shop', 'product', 'variant');
    }

    private function cartItem(Cart $cart, ProductVariant $variant, int|float $quantity = 1): CartItem
    {
        return CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'shop_id' => $variant->shop_id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'unit_price' => $variant->selling_price,
        ]);
    }

    private function customerAddress(User $user, MerchantProfile $merchant, array $overrides = []): MerchantCustomerAddress
    {
        $merchantCustomer = MerchantCustomer::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if (! $merchantCustomer instanceof MerchantCustomer) {
            $merchantCustomer = MerchantCustomer::query()->create([
                'merchant_id' => $merchant->getKey(),
                'user_id' => $user->getKey(),
                'customer_code' => 'CUS-'.Str::upper(Str::random(8)),
                'name' => $user->name,
                'mobile' => $user->mobile ?: '9876543210',
                'mobile_normalized' => $user->mobile ?: '9876543210',
                'email' => $user->email,
                'status' => MerchantCustomer::STATUS_ACTIVE,
                'linked_at' => now(),
            ]);
        }

        return MerchantCustomerAddress::query()->create([
            'merchant_customer_id' => $merchantCustomer->getKey(),
            'label' => $overrides['label'] ?? 'Home',
            'recipient_name' => $overrides['recipient_name'] ?? $user->name,
            'recipient_mobile' => $overrides['recipient_mobile'] ?? '9876543210',
            'recipient_mobile_normalized' => $overrides['recipient_mobile'] ?? '9876543210',
            'address_line_1' => $overrides['address_line_1'] ?? 'Main Road',
            'address_line_2' => $overrides['address_line_2'] ?? null,
            'landmark' => $overrides['landmark'] ?? null,
            'country_id' => $overrides['country_id'] ?? null,
            'state_id' => $overrides['state_id'] ?? null,
            'city_id' => $overrides['city_id'] ?? null,
            'postal_code' => $overrides['postal_code'] ?? null,
            'is_default_shipping' => (bool) ($overrides['is_default_shipping'] ?? false),
            'is_default_billing' => (bool) ($overrides['is_default_billing'] ?? false),
            'status' => MerchantCustomerAddress::STATUS_ACTIVE,
        ]);
    }

    private function postalCode(string $postalCode, bool $shippingEnabled = true): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|checkout test h.o|ho|nashik|maharashtra'),
            'circle_name' => 'Maharashtra',
            'region_name' => 'Nashik',
            'division_name' => 'Nashik',
            'office_name' => 'Checkout Test H.O',
            'postal_code' => $postalCode,
            'office_type' => 'HO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => $shippingEnabled,
            'district' => 'Nashik',
            'state' => 'Maharashtra',
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return array{country_id: int, state_id: int, city_id: ?int}
     */
    private function indiaLocation(bool $createCity = true): array
    {
        $country = $this->country('India', 'IN', 'IND');
        $state = $this->state($country['country_id'], 'Maharashtra');
        $city = $createCity ? $this->city($country['country_id'], $state['state_id'], 'Nashik') : ['city_id' => null];

        return [
            'country_id' => $country['country_id'],
            'state_id' => $state['state_id'],
            'city_id' => $city['city_id'],
        ];
    }

    /**
     * @return array{country_id: int}
     */
    private function country(string $name, string $iso2, string $iso3): array
    {
        $existing = DB::table('loc_countries')->where('iso2', $iso2)->value('id');

        if ($existing !== null) {
            return ['country_id' => (int) $existing];
        }

        return [
            'country_id' => (int) DB::table('loc_countries')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'iso2' => $iso2,
                'iso3' => $iso3,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ];
    }

    /**
     * @return array{state_id: int}
     */
    private function state(int $countryId, string $name): array
    {
        $existing = DB::table('loc_states')
            ->where('country_id', $countryId)
            ->where('name', $name)
            ->value('id');

        if ($existing !== null) {
            return ['state_id' => (int) $existing];
        }

        return [
            'state_id' => (int) DB::table('loc_states')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'country_id' => $countryId,
                'country_code' => 'IN',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ];
    }

    /**
     * @return array{city_id: int}
     */
    private function city(int $countryId, int $stateId, string $name): array
    {
        $existing = DB::table('loc_cities')
            ->where('country_id', $countryId)
            ->where('state_id', $stateId)
            ->where('name', $name)
            ->value('id');

        if ($existing !== null) {
            return ['city_id' => (int) $existing];
        }

        return [
            'city_id' => (int) DB::table('loc_cities')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'country_id' => $countryId,
                'state_id' => $stateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ];
    }

    private function restriction(
        string $postalCode,
        ?int $merchantId,
        ?int $shopId,
        string $reason,
        string $status = PostalCodeRestriction::STATUS_ACTIVE,
        mixed $startsAt = null,
        mixed $endsAt = null,
    ): PostalCodeRestriction {
        return PostalCodeRestriction::query()->create([
            'postal_code' => $postalCode,
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'reason' => $reason,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    private function guestCart(string $token): Cart
    {
        return Cart::query()->create([
            'user_id' => null,
            'session_token' => $token,
        ]);
    }

    private function customerUser(string $email): User
    {
        $this->country('India', 'IN', 'IND');

        $user = User::query()->create([
            'name' => 'Checkout Customer',
            'email' => $email,
            'password' => Hash::make('password'),
            'registration_source' => 'web',
        ]);
        $user->forceFill([
            'mobile' => '8'.random_int(100000000, 999999999),
            'status' => 'active',
        ])->save();
        $this->assignRole($user, 'customer');

        return $user->refresh();
    }

    private function systemSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert([
            'key' => $key,
        ], [
            'uuid' => (string) Str::uuid(),
            'label' => Str::headline($key),
            'value' => $value,
            'value_type' => 'string',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
}
