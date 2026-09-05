<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\PostalCode;
use App\Models\PostalCodeRestriction;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\PromotionRedemption;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Checkout\StorefrontDeliveryService;
use App\Services\Checkout\StorefrontPaymentMethodService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Promotion\Coupons\CouponSessionStore;
use App\Services\Promotion\Engine\Data\AppliedPromotion;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use App\Services\Promotion\Engine\Data\PromotionLineAdjustment;
use App\Services\Promotion\Engine\Data\PromotionLineInput;
use App\Services\Promotion\Redemptions\CouponRedemptionService;
use App\Services\Order\OrderStatusService;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use App\Services\Storefront\StorefrontCountryResolver;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
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
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

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

        $address = CustomerAddress::query()->where('recipient_name', 'Address Customer')->firstOrFail();
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->getKey(),
            'customer_id' => $this->globalCustomer($customer)->getKey(),
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

        $address = CustomerAddress::query()->where('recipient_name', 'Ajax Address Customer')->firstOrFail();
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

        $this->assertDatabaseHas('customer_addresses', [
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
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
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
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

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
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
                'browser_total' => '1.00',
                'customer_order_note' => '  Please call before delivery.  ',
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('500.00', $order->subtotal);
        $this->assertSame('Please call before delivery.', $order->customer_order_note);
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
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => 'standard',
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('Checkout Customer', $order->shipping_recipient_name);
        $this->assertSame('Main Road', $order->shipping_address_line_1);
        $this->assertSame('Main Road', $order->billing_address_line_1);
        $this->assertNull($order->customer_order_note);
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

        $address = CustomerAddress::query()->where('recipient_name', 'Billing Customer')->firstOrFail();
        $this->assertSame($address->getKey(), session(CheckoutPageService::SELECTED_BILLING_ADDRESS_SESSION_KEY));
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->getKey(),
            'customer_id' => $this->globalCustomer($customer)->getKey(),
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

        $address = CustomerAddress::query()->where('recipient_name', 'Ajax Billing Customer')->firstOrFail();
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
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $delivery->getKey(),
                'billing_same_as_delivery' => '0',
                'billing_address_id' => $billing->getKey(),
                'shipping_method' => 'standard',
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame('Main Road', $order->shipping_address_line_1);
        $this->assertSame('Main Road', $order->billing_address_line_1);
        $this->assertSame('Checkout Customer', $order->billing_recipient_name);
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

        $this->assertDatabaseHas('customer_addresses', [
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

    public function test_checkout_delivery_uses_flat_shop_delivery_charge(): void
    {
        $customer = $this->customerUser('delivery-flat@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_flat_charge', 50, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Standard Delivery')
            ->assertSee('₹50.00')
            ->assertSee('₹750.00');
    }

    public function test_checkout_delivery_free_threshold_overrides_flat_charge(): void
    {
        $customer = $this->customerUser('delivery-free@example.test');
        $fixture = $this->productFixture(price: 1200);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_flat_charge', 50, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'free_delivery_above', 1000, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('FREE')
            ->assertSee('₹1,200.00');
    }

    public function test_checkout_delivery_zero_day_estimate_renders_as_same_day(): void
    {
        $customer = $this->customerUser('delivery-same-day@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_estimate_min_days', 0, ShopSetting::TYPE_INTEGER);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_estimate_max_days', 1, ShopSetting::TYPE_INTEGER);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Estimated delivery: Same day to 1 day')
            ->assertDontSee('0-1 days');
    }

    public function test_checkout_pickup_details_render_as_separate_lines(): void
    {
        $customer = $this->customerUser('delivery-pickup-lines@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'pickup_instructions', 'Bring your order number.', ShopSetting::TYPE_STRING);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Collect from '.$fixture['shop']->name)
            ->assertSee('Fixture Street')
            ->assertSee('Bring your order number.')
            ->assertSee('checkout-fulfillment-line--shop', false)
            ->assertSee('checkout-fulfillment-line--address', false)
            ->assertSee('checkout-fulfillment-line--instructions', false);
    }

    public function test_checkout_delivery_minimum_order_can_make_delivery_unavailable(): void
    {
        $customer = $this->customerUser('delivery-min@example.test');
        $fixture = $this->productFixture(price: 400);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertSee('Minimum order of ₹500.00 required for delivery.');
    }

    public function test_checkout_pin_restriction_blocks_delivery_but_not_pickup(): void
    {
        $customer = $this->customerUser('delivery-restricted@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->restriction('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey(), 'Delivery temporarily unavailable');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Delivery temporarily unavailable')
            ->assertSee('Pickup from Shop')
            ->assertSee('FREE');
    }

    public function test_checkout_fulfillment_ajax_can_select_pickup_without_reload(): void
    {
        $customer = $this->customerUser('delivery-pickup-ajax@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_flat_charge', 50, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson(route('storefront.checkout.fulfillment'), ['fulfillment' => 'pickup'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('selected_fulfillment', 'pickup')
            ->assertJsonPath('shipping', '₹0.00')
            ->assertJsonPath('total', '₹700.00');
    }

    public function test_checkout_multishop_delivery_sums_shop_charges_and_hides_pickup(): void
    {
        $customer = $this->customerUser('delivery-multishop@example.test');
        $first = $this->productFixture(price: 600);
        $second = $this->productFixture(price: 700);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $first['variant']);
        $this->cartItem($cart, $second['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $first['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($first['shop'], 'fulfillment', 'delivery_flat_charge', 50, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($second['shop'], 'fulfillment', 'delivery_flat_charge', 40, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($second['shop'], 'fulfillment', 'free_delivery_above', 500, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('₹50.00')
            ->assertSee('₹1,350.00')
            ->assertDontSee('Pickup from Shop');
    }

    public function test_checkout_multishop_minimum_uses_each_shop_subtotal_not_whole_cart(): void
    {
        $customer = $this->customerUser('delivery-multishop-minimum@example.test');
        $first = $this->productFixture(price: 2000);
        $second = $this->productFixture(price: 5000);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $first['variant']);
        $this->cartItem($cart, $second['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $first['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($first['shop'], 'fulfillment', 'delivery_min_order_amount', 5000, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($second['shop'], 'fulfillment', 'delivery_min_order_amount', 3000, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Standard Delivery')
            ->assertSee('Unavailable')
            ->assertSee($first['shop']->name.': Minimum order of')
            ->assertDontSee($second['shop']->name.': Minimum order of');
    }

    public function test_checkout_delivery_shows_selected_cod_when_enabled_without_limits(): void
    {
        $customer = $this->customerUser('payment-cod-enabled@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Cash on Delivery')
            ->assertSee('Pay when your order is delivered.')
            ->assertSee('value="'.StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY.'"', false)
            ->assertDontSee('No payment method is currently available for this delivery option.');
    }

    public function test_checkout_delivery_hides_cod_when_disabled(): void
    {
        $customer = $this->customerUser('payment-cod-disabled@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertDontSee('Cash on Delivery')
            ->assertSee('No payment method is currently available for this delivery option.')
            ->assertSee('data-place-order-button disabled', false);
    }

    public function test_checkout_delivery_cod_minimum_can_make_cod_unavailable(): void
    {
        $customer = $this->customerUser('payment-cod-min@example.test');
        $fixture = $this->productFixture(price: 400);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Cash on Delivery')
            ->assertSee('Unavailable')
            ->assertSee('Minimum order of ₹500.00 required for COD.')
            ->assertSee('data-place-order-button disabled', false);
    }

    public function test_checkout_delivery_cod_maximum_can_make_cod_unavailable(): void
    {
        $customer = $this->customerUser('payment-cod-max@example.test');
        $fixture = $this->productFixture(price: 6000);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_max_order_amount', 5000, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Cash on Delivery')
            ->assertSee('COD is available only up to ₹5,000.00.')
            ->assertSee('data-place-order-button disabled', false);
    }

    public function test_checkout_pickup_shows_cash_at_shop_and_hides_cod(): void
    {
        $customer = $this->customerUser('payment-cash-shop@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                StorefrontDeliveryService::SELECTED_FULFILLMENT_SESSION_KEY => StorefrontDeliveryService::FULFILLMENT_PICKUP,
            ])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Cash at Shop')
            ->assertSee('Pay when you collect your order.')
            ->assertSee('value="'.StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP.'"', false);
    }

    public function test_checkout_pickup_disables_place_order_when_cash_at_shop_is_disabled(): void
    {
        $customer = $this->customerUser('payment-cash-shop-disabled@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cash_at_shop_enabled', false, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                StorefrontDeliveryService::SELECTED_FULFILLMENT_SESSION_KEY => StorefrontDeliveryService::FULFILLMENT_PICKUP,
            ])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertDontSee('Cash at Shop')
            ->assertSee('No payment method is currently available for this pickup option.')
            ->assertSee('data-place-order-button disabled', false);
    }

    public function test_checkout_fulfillment_ajax_replaces_cod_with_cash_at_shop(): void
    {
        $customer = $this->customerUser('payment-ajax-pickup@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson(route('storefront.checkout.fulfillment'), [
                'fulfillment' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
            ])
            ->assertOk()
            ->assertJsonPath('selected_payment_method', StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP)
            ->assertJsonMissing(['id' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY])
            ->assertJsonFragment(['id' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP]);
    }

    public function test_checkout_fulfillment_ajax_replaces_cash_at_shop_with_cod(): void
    {
        $customer = $this->customerUser('payment-ajax-delivery@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                StorefrontDeliveryService::SELECTED_FULFILLMENT_SESSION_KEY => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                StorefrontPaymentMethodService::SELECTED_PAYMENT_SESSION_KEY => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson(route('storefront.checkout.fulfillment'), [
                'fulfillment' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
            ])
            ->assertOk()
            ->assertJsonPath('selected_payment_method', StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY)
            ->assertJsonMissing(['id' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP])
            ->assertJsonFragment(['id' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY]);
    }

    public function test_checkout_rejects_tampered_pickup_with_cash_on_delivery(): void
    {
        $customer = $this->customerUser('payment-tamper@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_checkout_places_delivery_cod_order_with_server_calculated_shipping(): void
    {
        $customer = $this->customerUser('place-delivery@example.test');
        $fixture = $this->productFixture(price: 700);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $item = $this->cartItem($cart, $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_flat_charge', 100, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
                'shipping' => '0.00',
                'browser_total' => '700.00',
            ]);

        $order = Order::query()->with(['items', 'totals'])->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));

        $this->assertSame(Order::SOURCE_STOREFRONT, $order->created_source);
        $this->assertSame(Order::FULFILMENT_DELIVERY, $order->fulfilment_type);
        $this->assertSame(StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY, $order->payment_method);
        $this->assertSame(Order::PAYMENT_PENDING, $order->payment_status);
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);
        $this->assertSame('0.00', $order->amount_paid);
        $this->assertSame('100.00', $order->shipping_total);
        $this->assertSame('800.00', $order->grand_total);
        $this->assertSame('Checkout Customer', $order->shipping_recipient_name);
        $this->assertSame('Main Road', $order->shipping_address_line_1);
        $this->assertSame('422009', $order->shipping_postal_code);
        $this->assertSame('Main Road', $order->billing_address_line_1);
        $address->forceFill([
            'recipient_name' => 'Changed Customer',
            'address_line_1' => 'Changed Road',
            'postal_code' => '422010',
        ])->save();
        $order->refresh();
        $this->assertSame('Checkout Customer', $order->shipping_recipient_name);
        $this->assertSame('Main Road', $order->shipping_address_line_1);
        $this->assertSame('422009', $order->shipping_postal_code);
        $this->assertSame('Main Road', $order->billing_address_line_1);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $order->getKey(),
            'code' => OrderTotal::CODE_SHIPPING,
            'amount' => '100.00',
            'source' => 'storefront',
        ]);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);
        $this->assertSame(19, (int) $fixture['variant']->refresh()->stock_quantity);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout.success', $order))
            ->assertOk()
            ->assertSee('Order placed successfully')
            ->assertSee($order->order_number)
            ->assertSee('Cash on Delivery')
            ->assertSee('Pay when your order is delivered.');
    }

    public function test_checkout_places_pickup_cash_at_shop_order(): void
    {
        $customer = $this->customerUser('place-pickup@example.test');
        $fixture = $this->productFixture(price: 700);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $item = $this->cartItem($cart, $fixture['variant']);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ]);

        $order = Order::query()->with('shop')->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));

        $this->assertSame(Order::FULFILMENT_PICKUP, $order->fulfilment_type);
        $this->assertSame(StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP, $order->payment_method);
        $this->assertSame(Order::PAYMENT_PENDING, $order->payment_status);
        $this->assertSame('0.00', $order->shipping_total);
        $this->assertSame('Main Road', $order->billing_address_line_1);
        $this->assertSame($fixture['shop']->getKey(), $order->shop_id);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout.success', $order))
            ->assertOk()
            ->assertSee('Cash at Shop')
            ->assertSee('Pay when you collect your order.')
            ->assertSee($fixture['shop']->name);
    }

    public function test_checkout_place_order_applies_session_coupon_through_authoritative_order_creation(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-coupon@example.test');
        $fixture = $this->productFixture(price: 1000);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $item = $this->cartItem($cart, $fixture['variant']);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'SAVE200', ['value_amount' => '200.00']);

        $response = $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CouponSessionStore::SESSION_KEY => [$fixture['shop']->getKey() => 'SAVE200'],
            ])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ]);

        $order = Order::query()->with(['items', 'totals'])->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));

        $orderItem = $order->items->first();
        $this->assertSame('1000.00', $orderItem->line_subtotal);
        $this->assertSame('200.00', $orderItem->line_discount);
        $this->assertSame('800.00', $order->grand_total);
        $this->assertSame('coupon', $orderItem->metadata['promotion']['activation_type']);
        $this->assertSame($coupon->getKey(), $orderItem->metadata['promotion']['coupon_id']);
        $this->assertSame('SAVE200', $orderItem->metadata['promotion']['coupon_code']);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $order->getKey(),
            'code' => OrderTotal::CODE_ITEM_DISCOUNT,
            'amount' => '-200.00',
            'source' => 'promotion',
        ]);
        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id' => $coupon->promotion_id,
            'promotion_coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'customer_id' => $this->globalCustomer($customer)->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'discount_amount' => '200.00',
            'status' => PromotionRedemption::STATUS_REDEEMED,
        ]);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);
        $this->assertFalse(session()->has(CouponSessionStore::SESSION_KEY));
    }

    public function test_checkout_place_order_revalidates_stale_session_coupon_before_pricing(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-stale-coupon@example.test');
        $fixture = $this->productFixture(price: 1000);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $fixture['variant']);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'SAVE200', ['value_amount' => '200.00']);
        $coupon->forceFill(['status' => PromotionCoupon::STATUS_INACTIVE])->save();

        $response = $this->actingAs($customer)
            ->withSession([
                'active_role_id' => $this->roleId('customer'),
                CouponSessionStore::SESSION_KEY => [$fixture['shop']->getKey() => 'SAVE200'],
            ])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
                'promotion_id' => $coupon->promotion_id,
                'coupon_id' => $coupon->getKey(),
                'discount_amount' => '200.00',
                'browser_total' => '800.00',
            ]);

        $order = Order::query()->with('items')->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));

        $orderItem = $order->items->first();
        $this->assertSame('1000.00', $orderItem->line_subtotal);
        $this->assertSame('0.00', $orderItem->line_discount);
        $this->assertSame('1000.00', $order->grand_total);
        $this->assertNull($orderItem->metadata);
        $this->assertDatabaseCount('promotion_redemptions', 0);
        $this->assertFalse(session()->has(CouponSessionStore::SESSION_KEY));
    }

    public function test_checkout_coupon_redemption_usage_limits_and_cancellation_behaviour(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-coupon-limits@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'LIMITED', ['value_amount' => '150.00']);
        $coupon->promotion->forceFill(['total_usage_limit' => 1])->save();

        PromotionRedemption::query()->create([
            'promotion_id' => $coupon->promotion_id,
            'promotion_coupon_id' => $coupon->getKey(),
            'customer_id' => $this->globalCustomer($customer)->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'discount_amount' => '150.00',
            'status' => PromotionRedemption::STATUS_CANCELLED,
            'redeemed_at' => now()->subDay(),
            'cancelled_at' => now()->subHour(),
        ]);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'LIMITED');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('150.00', $order->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 2);
        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'discount_amount' => '150.00',
            'status' => PromotionRedemption::STATUS_REDEEMED,
        ]);
    }

    public function test_cancelled_order_marks_redemption_cancelled_and_releases_usage(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-cancel-release@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'CANCELFREE', ['value_amount' => '150.00']);
        $coupon->forceFill(['usage_limit' => 1])->save();
        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'CANCELFREE');
        $response->assertRedirect(route('storefront.checkout.success', $order));

        app(OrderStatusService::class)->transition($order, Order::STATUS_CANCELLED, $customer, 'Customer cancelled.');

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'status' => PromotionRedemption::STATUS_CANCELLED,
        ]);
        $this->assertNotNull(PromotionRedemption::query()->where('order_id', $order->getKey())->value('cancelled_at'));

        [$secondResponse, $secondOrder] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'CANCELFREE');

        $secondResponse->assertRedirect(route('storefront.checkout.success', $secondOrder));
        $this->assertSame('150.00', $secondOrder->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 2);
    }

    public function test_checkout_excludes_coupon_when_promotion_total_limit_is_exhausted(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-promotion-total-limit@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'PROMO-LIMIT', ['value_amount' => '125.00']);
        $coupon->promotion->forceFill(['total_usage_limit' => 1])->save();
        $this->redeemedCoupon($coupon, $fixture);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'PROMO-LIMIT');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('0.00', $order->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 1);
    }

    public function test_checkout_excludes_coupon_when_coupon_total_limit_is_exhausted(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-coupon-total-limit@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'COUPON-LIMIT', ['value_amount' => '125.00']);
        $coupon->forceFill(['usage_limit' => 1])->save();
        $this->redeemedCoupon($coupon, $fixture);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'COUPON-LIMIT');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('0.00', $order->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 1);
    }

    public function test_checkout_enforces_promotion_and_coupon_per_customer_limits_authoritatively(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-per-customer-limit@example.test');
        $fixture = $this->productFixture(price: 1000);
        $promotionLimited = $this->couponPromotion($fixture, 'fixed_discount', 'PROMO-CUSTOMER', ['value_amount' => '125.00']);
        $promotionLimited->promotion->forceFill(['per_customer_usage_limit' => 1])->save();
        $this->redeemedCoupon($promotionLimited, $fixture, $this->globalCustomer($customer));

        [$promotionResponse, $promotionOrder] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'PROMO-CUSTOMER');

        $promotionResponse->assertRedirect(route('storefront.checkout.success', $promotionOrder));
        $this->assertSame('0.00', $promotionOrder->items->first()->line_discount);

        $couponLimited = $this->couponPromotion($fixture, 'fixed_discount', 'COUPON-CUSTOMER', ['value_amount' => '125.00']);
        $couponLimited->forceFill(['per_customer_usage_limit' => 1])->save();
        $this->redeemedCoupon($couponLimited, $fixture, $this->globalCustomer($customer));

        [$couponResponse, $couponOrder] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'COUPON-CUSTOMER');

        $couponResponse->assertRedirect(route('storefront.checkout.success', $couponOrder));
        $this->assertSame('0.00', $couponOrder->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 2);
    }

    public function test_guest_coupon_preview_is_provisional_until_authenticated_checkout_limits_are_enforced(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-guest-provisional@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'GUESTCHECK', ['value_amount' => '125.00']);
        $coupon->forceFill(['per_customer_usage_limit' => 1])->save();
        $this->redeemedCoupon($coupon, $fixture, $this->globalCustomer($customer));
        $guestCart = $this->guestCart('guest-provisional-limit-token');
        $this->cartItem($guestCart, $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'guest-provisional-limit-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => 'GUESTCHECK'])
            ->assertOk()
            ->assertJsonPath('coupon.status', 'applied')
            ->assertJsonPath('coupon.discount_cents', 12500);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'GUESTCHECK');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('0.00', $order->items->first()->line_discount);
        $this->assertDatabaseCount('promotion_redemptions', 1);
    }

    public function test_checkout_usage_limits_are_isolated_by_shop_and_customer(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-shop-isolation@example.test');
        $fixture = $this->productFixture(price: 1000);
        $otherFixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'SHOPONLY', ['value_amount' => '175.00']);
        $coupon->forceFill(['usage_limit' => 1])->save();
        $otherCoupon = $this->couponPromotion($otherFixture, 'fixed_discount', 'SHOPONLY', ['value_amount' => '175.00']);
        $this->redeemedCoupon($otherCoupon, $otherFixture);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'SHOPONLY');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('175.00', $order->items->first()->line_discount);
        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'status' => PromotionRedemption::STATUS_REDEEMED,
        ]);
    }

    public function test_checkout_automatic_winner_creates_no_coupon_redemption(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-auto-wins-coupon@example.test');
        $fixture = $this->productFixture(price: 1000);
        $this->couponPromotion($fixture, 'fixed_discount', 'SMALLSAVE', ['value_amount' => '100.00']);
        $this->automaticPromotion($fixture, 'fixed_discount', ['value_amount' => '250.00']);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'SMALLSAVE');

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('250.00', $order->items->first()->line_discount);
        $this->assertSame(Promotion::ACTIVATION_AUTOMATIC, $order->items->first()->metadata['promotion']['activation_type']);
        $this->assertDatabaseCount('promotion_redemptions', 0);
    }

    public function test_coupon_redemption_creation_is_idempotent_for_same_order_promotion_coupon_identity(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-idempotent-coupon@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'ONCE', ['value_amount' => '120.00']);
        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'ONCE');
        $response->assertRedirect(route('storefront.checkout.success', $order));
        $result = new PromotionCalculationResult((int) $fixture['shop']->getKey(), [
            (int) $fixture['variant']->getKey() => new PromotionLineAdjustment(
                new PromotionLineInput((int) $fixture['variant']->getKey(), (int) $fixture['product']->getKey(), (int) $fixture['shop']->getKey(), '1.000', '1000.00'),
                100000,
                12000,
                88000,
                new AppliedPromotion(
                    (int) $coupon->promotion_id,
                    (string) $coupon->promotion->name,
                    $coupon->promotion->slug,
                    'fixed_discount',
                    'fixed_discount',
                    0,
                    12000,
                    activationType: Promotion::ACTIVATION_COUPON,
                    couponId: (int) $coupon->getKey(),
                    couponCode: 'ONCE',
                ),
            ),
        ]);

        app(CouponRedemptionService::class)->redeemWinningCoupon($order, $result);

        $this->assertDatabaseCount('promotion_redemptions', 1);
    }

    public function test_checkout_ignores_forged_browser_coupon_and_discount_values(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $customer = $this->customerUser('place-forged-coupon@example.test');
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'REAL100', ['value_amount' => '100.00']);

        [$response, $order] = $this->placeStorefrontOrderWithCoupon($customer, $fixture, 'REAL100', 1, [
            'promotion_id' => $coupon->promotion_id + 999,
            'coupon_id' => $coupon->getKey() + 999,
            'discount_amount' => '999.00',
            'browser_total' => '1.00',
        ]);

        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame('100.00', $order->items->first()->line_discount);
        $this->assertSame('900.00', $order->grand_total);
        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_coupon_id' => $coupon->getKey(),
            'discount_amount' => '100.00',
        ]);
    }

    public function test_storefront_backorder_checkout_can_deduct_stock_negative(): void
    {
        $customer = $this->customerUser('place-backorder@example.test');
        $fixture = $this->productFixture(
            price: 700,
            availabilityCode: ProductAvailabilityStatus::CODE_BACKORDER,
            stock: 5,
        );
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $fixture['variant'], 7);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ])
            ->assertRedirect();

        $this->assertSame(-2, (int) $fixture['variant']->refresh()->stock_quantity);
        $this->assertDatabaseHas('orders', [
            'created_source' => Order::SOURCE_STOREFRONT,
            'order_status' => Order::STATUS_PENDING,
        ]);
    }

    public function test_storefront_out_of_stock_status_blocks_checkout_even_when_stock_exists(): void
    {
        $customer = $this->customerUser('place-out-of-stock@example.test');
        $fixture = $this->productFixture(
            price: 700,
            availabilityCode: ProductAvailabilityStatus::CODE_OUT_OF_STOCK,
            stock: 5,
        );
        $item = $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['id' => $item->getKey()]);
        $this->assertSame(5, (int) $fixture['variant']->refresh()->stock_quantity);
    }

    public function test_checkout_place_order_uses_product_variant_id_not_cart_item_id(): void
    {
        $customer = $this->customerUser('place-variant-id@example.test');
        $fixture = $this->productFixture(price: 700);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $item = $this->cartItem($cart, $fixture['variant']);
        $item->forceFill(['id' => $fixture['variant']->getKey() + 1000])->save();
        $item->refresh();
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);

        $this->assertNotSame((int) $fixture['variant']->getKey(), (int) $item->getKey());

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ]);

        $order = Order::query()->with('items')->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));
        $this->assertSame($fixture['variant']->getKey(), $order->items->first()->product_variant_id);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);
    }

    public function test_checkout_pickup_order_reuses_global_customer_address_across_merchants_without_copies(): void
    {
        $customer = $this->customerUser('place-cross-merchant-address@example.test');
        $fixture = $this->productFixture(price: 700);
        $otherFixture = $this->productFixture(price: 300);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $this->cartItem($cart, $fixture['variant']);
        $globalAddress = $this->customerAddress($customer, $otherFixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
            'address_line_1' => 'Global Saved Address',
        ]);

        $response = $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $globalAddress->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('storefront.checkout.success', $order));
        $order->refresh();

        $this->assertSame($fixture['shop']->getKey(), $order->shop_id);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $globalAddress->getKey(),
            'address_line_1' => 'Global Saved Address',
        ]);
        $this->assertSame('Global Saved Address', $order->billing_address_line_1);

        $this->cartItem($cart, $otherFixture['variant']);

        $secondResponse = $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $globalAddress->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
            ]);

        $secondOrder = Order::query()->whereKeyNot($order->getKey())->firstOrFail();
        $secondResponse->assertRedirect(route('storefront.checkout.success', $secondOrder));

        $this->assertSame($otherFixture['shop']->getKey(), $secondOrder->shop_id);
        $this->assertSame('Global Saved Address', $secondOrder->billing_address_line_1);
        $this->assertDatabaseCount('customer_addresses', 1);
    }

    public function test_checkout_place_order_revalidates_cod_minimum(): void
    {
        $customer = $this->customerUser('place-cod-min@example.test');
        $fixture = $this->productFixture(price: 400);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_place_order_revalidates_restricted_pin(): void
    {
        $customer = $this->customerUser('place-restricted-pin@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->restriction('422009', $fixture['merchant']->getKey(), $fixture['shop']->getKey(), 'Delivery temporarily unavailable');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('shipping_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_place_order_revalidates_shop_delivery_minimum(): void
    {
        $customer = $this->customerUser('place-delivery-min@example.test');
        $fixture = $this->productFixture(price: 400);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);
        $this->shopSetting($fixture['shop'], 'fulfillment', 'delivery_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('shipping_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_local_only_delivery_mismatch_and_keeps_pickup_available(): void
    {
        $customer = $this->customerUser('checkout-local-mismatch@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009', district: 'Nashik', state: 'Maharashtra');
        $this->postalCode('802301', district: 'Bhojpur', state: 'Bihar');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '802301',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Standard Delivery')
            ->assertSee('Unavailable')
            ->assertSee('Delivery is not available to this PIN code.')
            ->assertSee('Pickup from Shop');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('shipping_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_place_order_empty_cart_is_rejected(): void
    {
        $customer = $this->customerUser('place-empty@example.test');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertRedirect(route('storefront.cart'))
            ->assertSessionHas('error', 'Your cart is empty.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_double_submit_creates_only_one_order(): void
    {
        $customer = $this->customerUser('place-double@example.test');
        $fixture = $this->productFixture(price: 700);
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $payload = [
            'address_id' => $address->getKey(),
            'billing_same_as_delivery' => '1',
            'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
            'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
        ];

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), $payload)
            ->assertRedirect();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), $payload)
            ->assertRedirect(route('storefront.cart'));

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_order_failure_keeps_cart_items(): void
    {
        $customer = $this->customerUser('place-failure@example.test');
        $fixture = $this->productFixture(price: 700);
        $item = $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);
        $fixture['variant']->forceFill(['stock_quantity' => 0])->save();
        $this->postalCode('422009');
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $this->shopSetting($fixture['shop'], 'payment', 'cod_enabled', true, ShopSetting::TYPE_BOOLEAN);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $this->roleId('customer')])
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_DELIVERY,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_ON_DELIVERY,
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['id' => $item->getKey()]);
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

        $this->assertDatabaseHas('customer_addresses', [
            'recipient_name' => 'Spoof Customer',
            'country_id' => $india['country_id'],
            'state_id' => $india['state_id'],
            'city_id' => $india['city_id'],
            'postal_code' => '422009',
        ]);
    }

    public function test_valid_pin_creates_city_master_match_when_missing(): void
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

        $cityId = (int) DB::table('loc_cities')
            ->where('country_id', $india['country_id'])
            ->where('state_id', $india['state_id'])
            ->where('name', 'Nashik')
            ->value('id');

        $this->assertGreaterThan(0, $cityId);
        $this->assertDatabaseHas('customer_addresses', [
            'recipient_name' => 'Missing City Customer',
            'country_id' => $india['country_id'],
            'state_id' => $india['state_id'],
            'city_id' => $cityId,
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
    private function productFixture(int $price = 199, string $availabilityCode = ProductAvailabilityStatus::CODE_IN_STOCK, int $stock = 20): array
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
        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);
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
            'pincode' => '422009',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $category->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Checkout Product '.Str::random(8),
            'slug' => 'checkout-product-'.Str::random(8),
            'availability_status_id' => ProductAvailabilityStatus::query()
                ->where('merchant_id', $merchant->getKey())
                ->where('code', $availabilityCode)
                ->value('id'),
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
            'stock_quantity' => $stock,
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

    private function couponPromotion(array $fixture, string $templateCode, string $code, array $reward): PromotionCoupon
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => 'Checkout Coupon '.Str::random(6),
            'slug' => 'checkout-coupon-'.Str::random(8),
            'status' => Promotion::STATUS_ACTIVE,
            'activation_type' => Promotion::ACTIVATION_COUPON,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);
        $promotion->rewards()->create([
            'reward_type' => $template->reward_type,
            ...$reward,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
            'sort_order' => 10,
        ]);

        return $promotion->coupons()->create([
            'shop_id' => $fixture['shop']->getKey(),
            'code' => $code,
            'status' => PromotionCoupon::STATUS_ACTIVE,
        ]);
    }

    private function automaticPromotion(array $fixture, string $templateCode, array $reward): Promotion
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => 'Checkout Automatic '.Str::random(6),
            'slug' => 'checkout-automatic-'.Str::random(8),
            'status' => Promotion::STATUS_ACTIVE,
            'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
            'origin' => Promotion::ORIGIN_MERCHANT,
            'refund_policy_mode' => Promotion::POLICY_INHERIT,
            'exchange_policy_mode' => Promotion::POLICY_INHERIT,
        ]);
        $promotion->rewards()->create([
            'reward_type' => $template->reward_type,
            ...$reward,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
            'sort_order' => 10,
        ]);

        return $promotion;
    }

    private function redeemedCoupon(PromotionCoupon $coupon, array $fixture, ?Customer $customer = null): PromotionRedemption
    {
        return PromotionRedemption::query()->create([
            'promotion_id' => $coupon->promotion_id,
            'promotion_coupon_id' => $coupon->getKey(),
            'customer_id' => $customer?->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'discount_amount' => '1.00',
            'status' => PromotionRedemption::STATUS_REDEEMED,
            'redeemed_at' => now()->subDay(),
        ]);
    }

    private function placeStorefrontOrderWithCoupon(User $customer, array $fixture, ?string $code, int $quantity = 1, array $post = []): array
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $customer->getKey()]);
        $cart->items()->delete();
        $this->cartItem($cart, $fixture['variant'], $quantity);
        $address = $this->customerAddress($customer, $fixture['merchant'], [
            'postal_code' => '422009',
            'is_default_billing' => true,
        ]);
        $session = ['active_role_id' => $this->roleId('customer')];
        if ($code !== null) {
            $session[CouponSessionStore::SESSION_KEY] = [$fixture['shop']->getKey() => $code];
        }

        $response = $this->actingAs($customer)
            ->withSession($session)
            ->post(route('storefront.checkout.place-order'), [
                'address_id' => $address->getKey(),
                'billing_same_as_delivery' => '1',
                'shipping_method' => StorefrontDeliveryService::FULFILLMENT_PICKUP,
                'payment_method' => StorefrontPaymentMethodService::PAYMENT_CASH_AT_SHOP,
                ...$post,
            ]);

        return [$response, Order::query()->with(['items', 'totals'])->latest('id')->firstOrFail()];
    }

    private function customerAddress(User $user, MerchantProfile $merchant, array $overrides = []): CustomerAddress
    {
        $customer = $this->globalCustomer($user);

        return CustomerAddress::query()->create([
            'customer_id' => $customer->getKey(),
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
            'status' => CustomerAddress::STATUS_ACTIVE,
        ]);
    }

    private function globalCustomer(User $user): Customer
    {
        return Customer::query()->firstOrCreate([
            'user_id' => $user->getKey(),
        ], [
            'name' => $user->name,
            'mobile_country_code' => '+91',
            'mobile' => $user->mobile ?: '9876543210',
            'mobile_normalized' => $user->mobile ?: '9876543210',
            'email' => $user->email,
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }

    private function postalCode(
        string $postalCode,
        bool $shippingEnabled = true,
        string $district = 'Nashik',
        string $state = 'Maharashtra',
    ): PostalCode
    {
        return PostalCode::query()->create([
            'source_key' => sha1($postalCode.'|checkout test h.o|ho|'.strtolower($district).'|'.strtolower($state)),
            'circle_name' => $state,
            'region_name' => $district,
            'division_name' => $district,
            'office_name' => 'Checkout Test H.O',
            'postal_code' => $postalCode,
            'office_type' => 'HO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => $shippingEnabled,
            'district' => $district,
            'state' => $state,
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

    private function shopSetting(Shop $shop, string $group, string $key, mixed $value, string $type): void
    {
        app(ShopSettingsService::class)->setTyped((int) $shop->getKey(), $group, $key, $value, $type);
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
