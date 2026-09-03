<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductReturnPolicy;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionCoupon;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Cart\CartResolver;
use App\Services\Merchant\ShopSettingsService;
use App\Services\Order\OrderCreationService;
use App\Services\Promotion\Coupons\CouponSessionStore;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use Database\Seeders\MasterData\PromotionTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontCartPageTest extends TestCase
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

    public function test_viewing_empty_cart_does_not_create_cart(): void
    {
        $this->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Your cart is empty');

        $this->assertSame(0, Cart::query()->count());
    }

    public function test_guest_cart_page_shows_only_current_session_cart(): void
    {
        $first = $this->productFixture(name: 'Session Product');
        $second = $this->productFixture(name: 'Other Guest Product');
        $this->cartItem($this->guestCart('guest-token'), $first['variant']);
        $this->cartItem($this->guestCart('other-token'), $second['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'guest-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Session Product')
            ->assertDontSee('Other Guest Product');
    }

    public function test_sidebar_cart_uses_current_cart_data(): void
    {
        $fixture = $this->productFixture(name: 'Sidebar Cart Product', price: 75);
        $this->cartItem($this->guestCart('sidebar-token'), $fixture['variant'], 2);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'sidebar-token'])
            ->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('id="shoppingCart"', false)
            ->assertSee('Sidebar Cart Product')
            ->assertSee('150.00', false)
            ->assertSee('data-mini-cart-empty hidden', false)
            ->assertDontSee('data-mini-cart-remove-url', false)
            ->assertDontSee('tf-totals-total-value', false)
            ->assertDontSee('tf-mini-card-price', false);
    }

    public function test_add_to_cart_response_contains_sidebar_cart_payload(): void
    {
        $fixture = $this->productFixture(name: 'Payload Product', price: 60);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('is_empty', false)
            ->assertJsonPath('cart_count', '2')
            ->assertJsonPath('subtotal_cents', 12000)
            ->assertJsonPath('shop_groups.0.items.0.product_name', 'Payload Product');
    }

    public function test_customer_cart_page_shows_user_cart(): void
    {
        $customer = $this->userFixture('cart-customer@example.test');
        $customerRoleId = $this->assignRole($customer, 'customer');
        $fixture = $this->productFixture(name: 'Customer Cart Product');
        $this->cartItem(Cart::query()->create(['user_id' => $customer->getKey()]), $fixture['variant']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $customerRoleId])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Customer Cart Product');
    }

    public function test_admin_context_uses_guest_cart_not_auth_user_cart(): void
    {
        $admin = $this->userFixture('cart-admin@example.test');
        $adminRoleId = $this->assignRole($admin, 'admin');
        $userFixture = $this->productFixture(name: 'Admin User Cart Product');
        $guestFixture = $this->productFixture(name: 'Admin Guest Cart Product');
        $this->cartItem(Cart::query()->create(['user_id' => $admin->getKey()]), $userFixture['variant']);
        $this->cartItem($this->guestCart('admin-guest-token'), $guestFixture['variant']);

        $this->actingAs($admin)
            ->withSession([
                'active_role_id' => $adminRoleId,
                CartResolver::SESSION_TOKEN_KEY => 'admin-guest-token',
            ])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Admin Guest Cart Product')
            ->assertDontSee('Admin User Cart Product');
    }

    public function test_multiple_shops_are_grouped_with_subtotals_and_cart_total(): void
    {
        $first = $this->productFixture(name: 'First Shop Product', price: 100);
        $second = $this->productFixture(name: 'Second Shop Product', price: 50);
        $cart = $this->guestCart('group-token');
        $this->cartItem($cart, $first['variant'], 2);
        $this->cartItem($cart, $second['variant'], 3);

        $response = $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'group-token'])
            ->get(route('storefront.cart'));

        $response->assertOk()
            ->assertSee('First Shop Product')
            ->assertSee('Second Shop Product')
            ->assertSee($first['shop']->name)
            ->assertSee($second['shop']->name)
            ->assertSee('Shop subtotal')
            ->assertSee('350.00', false)
            ->assertSee('href="'.route('storefront.checkout').'"', false)
            ->assertDontSee('id="checkout-btn"', false)
            ->assertDontSee('each-total-price', false)
            ->assertDontSee('each-subtotal-price', false);
    }

    public function test_cart_shows_shop_specific_delivery_minimum_remaining_message(): void
    {
        $first = $this->productFixture(name: 'Below Minimum Product', price: 200);
        $second = $this->productFixture(name: 'Above Minimum Product', price: 500);
        $this->shopSetting($first['shop'], 'fulfillment', 'delivery_min_order_amount', 500, ShopSetting::TYPE_DECIMAL);
        $this->shopSetting($second['shop'], 'fulfillment', 'delivery_min_order_amount', 300, ShopSetting::TYPE_DECIMAL);
        $cart = $this->guestCart('delivery-minimum-token');
        $this->cartItem($cart, $first['variant']);
        $this->cartItem($cart, $second['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'delivery-minimum-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Add')
            ->assertSee('300.00')
            ->assertSee('more from this shop to qualify for delivery.')
            ->assertSee('data-shop-delivery-minimum="'.$first['shop']->getKey().'"', false)
            ->assertSee('data-shop-delivery-minimum="'.$second['shop']->getKey().'"', false);
    }

    public function test_full_cart_page_keeps_remove_action_behind_confirmation_modal(): void
    {
        $fixture = $this->productFixture(name: 'Confirm Remove Product');
        $item = $this->cartItem($this->guestCart('confirm-remove-token'), $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'confirm-remove-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Confirm Remove Product')
            ->assertSee('data-cart-remove-url="'.route('storefront.cart.items.destroy', $item).'"', false)
            ->assertSee('data-cart-item-message', false)
            ->assertDontSee('data-cart-message', false)
            ->assertSee('Remove item?')
            ->assertSee('Are you sure you want to remove this item from your cart?')
            ->assertSee('data-cart-confirm-remove', false)
            ->assertSee('data-bs-dismiss="modal"', false);

        $this->assertDatabaseHas('cart_items', ['id' => $item->getKey()]);
    }

    public function test_variant_attributes_display_but_default_variant_label_does_not(): void
    {
        $fixture = $this->productFixture(name: 'Attribute Product');
        $this->attachVariantAttribute($fixture['variant'], 'Size', 'M');
        $this->cartItem($this->guestCart('attribute-token'), $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'attribute-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Size')
            ->assertSee('M')
            ->assertDontSee('Default</span>', false);
    }

    public function test_cart_page_shows_effective_return_exchange_policy_for_each_item(): void
    {
        $defaultPolicy = $this->productFixture(name: 'Default Cart Policy Product');
        $overridePolicy = $this->productFixture(name: 'Override Cart Policy Product');
        ProductReturnPolicy::query()->create([
            'product_id' => $overridePolicy['product']->getKey(),
            'refund_allowed' => true,
            'refund_window_days' => 5,
            'exchange_allowed' => false,
            'exchange_window_days' => 0,
        ]);
        $cart = $this->guestCart('policy-token');
        $this->cartItem($cart, $defaultPolicy['variant']);
        $this->cartItem($cart, $overridePolicy['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'policy-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('No Refund · Exchange within 7 days')
            ->assertSee('Refund within 5 days · No Exchange');
    }

    public function test_cart_policy_loading_uses_eager_loaded_relationships(): void
    {
        $first = $this->productFixture(name: 'First Query Policy Product');
        $second = $this->productFixture(name: 'Second Query Policy Product');
        $this->shopSetting($second['shop'], 'returns', 'refund_allowed', true, ShopSetting::TYPE_BOOLEAN);
        $this->shopSetting($second['shop'], 'returns', 'refund_window_days', 4, ShopSetting::TYPE_INTEGER);
        ProductReturnPolicy::query()->create([
            'product_id' => $first['product']->getKey(),
            'refund_allowed' => true,
            'refund_window_days' => 2,
            'exchange_allowed' => true,
            'exchange_window_days' => 2,
        ]);
        $cart = $this->guestCart('policy-query-token');
        $this->cartItem($cart, $first['variant']);
        $this->cartItem($cart, $second['variant']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'policy-query-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Refund within 2 days · Exchange within 2 days')
            ->assertSee('Refund within 4 days · Exchange within 7 days');

        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $query): string => strtolower($query));

        $this->assertLessThanOrEqual(1, $queries->filter(fn (string $query): bool => str_contains($query, 'product_return_policies'))->count());
        $this->assertLessThanOrEqual(1, $queries->filter(fn (string $query): bool => str_contains($query, 'shop_settings'))->count());

        DB::disableQueryLog();
    }

    public function test_quantity_update_refreshes_totals_and_header_count(): void
    {
        $fixture = $this->productFixture(price: 125, stock: 10);
        $cart = $this->guestCart('update-token');
        $item = $this->cartItem($cart, $fixture['variant'], 1);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'update-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 3,
                'unit_price' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('cart_count', '3')
            ->assertJsonPath('subtotal_cents', 37500)
            ->assertJsonPath('total_cents', 37500);

        $this->assertSame('3.000', $item->refresh()->quantity);
        $this->assertSame('125.00', $item->unit_price);
    }

    public function test_quantity_decrease_updates_existing_item(): void
    {
        $fixture = $this->productFixture(stock: 10);
        $cart = $this->guestCart('decrease-token');
        $item = $this->cartItem($cart, $fixture['variant'], 3);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'decrease-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('cart_count', '2');

        $this->assertSame('2.000', $item->refresh()->quantity);
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $fixture = $this->productFixture([
            'allow_decimal_quantity' => false,
            'minimum_order_quantity' => 2,
            'maximum_order_quantity' => 5,
            'quantity_increment' => 2,
            'stock_quantity' => 10,
        ]);
        $cart = $this->guestCart('invalid-token');
        $item = $this->cartItem($cart, $fixture['variant'], 2);

        foreach ([0, -1, 1.5, 1, 7, 3] as $quantity) {
            $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'invalid-token'])
                ->patchJson(route('storefront.cart.items.update', $item), [
                    'quantity' => $quantity,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('quantity');
        }
    }

    public function test_cannot_update_beyond_stock_without_backorder(): void
    {
        $fixture = $this->productFixture(stock: 2);
        $cart = $this->guestCart('stock-token');
        $item = $this->cartItem($cart, $fixture['variant'], 1);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'stock-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame('1.000', $item->refresh()->quantity);
    }

    public function test_remove_deletes_only_current_cart_item_and_updates_totals(): void
    {
        $first = $this->productFixture(price: 100);
        $second = $this->productFixture(price: 50);
        $cart = $this->guestCart('remove-token');
        $remove = $this->cartItem($cart, $first['variant'], 2);
        $keep = $this->cartItem($cart, $second['variant'], 1);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'remove-token'])
            ->deleteJson(route('storefront.cart.items.destroy', $remove))
            ->assertOk()
            ->assertJsonPath('cart_count', '1')
            ->assertJsonPath('subtotal_cents', 5000)
            ->assertJsonCount(1, 'shop_groups');

        $this->assertDatabaseMissing('cart_items', ['id' => $remove->getKey()]);
        $this->assertDatabaseHas('cart_items', ['id' => $keep->getKey()]);
        $this->assertDatabaseHas('carts', ['id' => $cart->getKey()]);
    }

    public function test_logged_in_customer_can_remove_item_from_user_cart(): void
    {
        $customer = $this->userFixture('cart-remove-customer@example.test');
        $customerRoleId = $this->assignRole($customer, 'customer');
        $fixture = $this->productFixture(price: 80);
        $cart = Cart::query()->create(['user_id' => $customer->getKey()]);
        $item = $this->cartItem($cart, $fixture['variant']);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $customerRoleId])
            ->deleteJson(route('storefront.cart.items.destroy', $item))
            ->assertOk()
            ->assertJsonPath('is_empty', true)
            ->assertJsonPath('cart_count', '0');

        $this->assertDatabaseMissing('cart_items', ['id' => $item->getKey()]);
        $this->assertDatabaseHas('carts', ['id' => $cart->getKey()]);
    }

    public function test_cart_item_ownership_is_enforced_for_update_and_delete(): void
    {
        $owned = $this->productFixture();
        $other = $this->productFixture();
        $this->cartItem($this->guestCart('owned-token'), $owned['variant']);
        $otherItem = $this->cartItem($this->guestCart('other-token'), $other['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'owned-token'])
            ->patchJson(route('storefront.cart.items.update', $otherItem), [
                'quantity' => 2,
            ])
            ->assertNotFound();

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'owned-token'])
            ->deleteJson(route('storefront.cart.items.destroy', $otherItem))
            ->assertNotFound();
    }

    public function test_price_change_is_refreshed_from_current_variant_price(): void
    {
        $fixture = $this->productFixture(price: 250, stock: 10);
        $cart = $this->guestCart('price-token');
        $item = $this->cartItem($cart, $fixture['variant'], 2, unitPrice: 100);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'price-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('500.00', false);

        $this->assertSame('250.00', $item->refresh()->unit_price);
    }

    public function test_cart_totals_include_automatic_promotion_without_mutating_stored_unit_price(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 500, stock: 10);
        $promotion = $this->cartPromotion($fixture, 'fixed_discount', ['value_amount' => '100.00']);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
            'sort_order' => 10,
        ]);
        $cart = $this->guestCart('promotion-cart-token');
        $item = $this->cartItem($cart, $fixture['variant'], 2);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'promotion-cart-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('base_subtotal_cents', 100000)
            ->assertJsonPath('promotion_discount_cents', 20000)
            ->assertJsonPath('subtotal_cents', 80000)
            ->assertJsonPath('shop_groups.0.items.0.promotion.id', $promotion->getKey());

        $this->assertSame('500.00', $item->refresh()->unit_price);
    }

    public function test_cart_exposes_free_gift_virtually_and_removes_it_when_qualification_is_lost(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000, stock: 10);
        $giftProduct = Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['shop']->root_product_category_id,
            'product_category_id' => $fixture['product']->product_category_id,
            'product_name' => 'Cart Gift Product',
            'slug' => 'cart-gift-product-'.Str::random(8),
            'availability_status_id' => $fixture['product']->availability_status_id,
            'status' => 'active',
            'published_at' => now(),
        ]);
        $giftVariant = ProductVariant::query()->create([
            'product_id' => $giftProduct->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'availability_status_id' => $giftProduct->availability_status_id,
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Gift',
            'mrp' => 700,
            'selling_price' => 600,
            'stock_quantity' => 3,
            'is_default' => true,
            'is_sellable' => true,
            'status' => 'active',
        ]);
        $promotion = $this->cartPromotion($fixture, 'free_gift', []);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
            'sort_order' => 10,
        ]);
        $promotion->targets()->create([
            'target_role' => PromotionTarget::ROLE_GIFT,
            'target_type' => PromotionTarget::TYPE_VARIANT,
            'target_id' => $giftVariant->getKey(),
            'sort_order' => 20,
        ]);
        $promotion->conditions()->create([
            'condition_type' => PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL,
            'operator' => '>=',
            'value_numeric' => '2000.00',
            'sort_order' => 10,
        ]);
        $cart = $this->guestCart('free-gift-cart-token');
        $item = $this->cartItem($cart, $fixture['variant'], 2);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'free-gift-cart-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('base_subtotal_cents', 260000)
            ->assertJsonPath('promotion_discount_cents', 60000)
            ->assertJsonPath('subtotal_cents', 200000)
            ->assertJsonPath('shop_groups.0.items.1.is_generated_gift', true)
            ->assertJsonPath('shop_groups.0.items.1.product_variant_id', $giftVariant->getKey());

        $this->assertSame(1, CartItem::query()->where('cart_id', $cart->getKey())->count());

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'free-gift-cart-token'])
            ->patchJson(route('storefront.cart.items.update', $item), [
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'shop_groups.0.items')
            ->assertJsonPath('promotion_discount_cents', 0);
    }

    public function test_unavailable_item_renders_warning_and_can_be_removed(): void
    {
        $fixture = $this->productFixture(name: 'Unavailable Product');
        $cart = $this->guestCart('unavailable-token');
        $item = $this->cartItem($cart, $fixture['variant']);
        $fixture['product']->delete();

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'unavailable-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('Unavailable Product')
            ->assertSee('currently unavailable');

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'unavailable-token'])
            ->deleteJson(route('storefront.cart.items.destroy', $item))
            ->assertOk()
            ->assertJsonPath('is_empty', true)
            ->assertJsonPath('cart_count', '0');
    }

    public function test_backorder_cart_item_renders_confirmation_warning_but_remains_available(): void
    {
        $fixture = $this->productFixture(stock: 5);
        $fixture['product']->forceFill([
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_BACKORDER)->getKey(),
        ])->save();
        $cart = $this->guestCart('backorder-cart-token');
        $this->cartItem($cart, $fixture['variant'], 7);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'backorder-cart-token'])
            ->get(route('storefront.cart'))
            ->assertOk()
            ->assertSee('5 items are currently in stock. 2 items require confirmation from the merchant.')
            ->assertDontSee('Checkout is disabled until unavailable items are removed.');
    }

    public function test_coupon_apply_normalizes_code_and_stores_per_shop_session_state(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000);
        $this->couponPromotion($fixture, 'percentage_discount', 'SAVE20', ['value_percent' => '20.00']);
        $cart = $this->guestCart('coupon-normalize-token');
        $this->cartItem($cart, $fixture['variant']);

        $response = $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-normalize-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), [
                'coupon_code' => ' save20 ',
                'promotion_id' => 999999,
                'discount_amount' => 999999,
            ]);

        $response->assertOk()
            ->assertJsonPath('coupon.status', 'applied')
            ->assertJsonPath('coupon.code', 'SAVE20')
            ->assertJsonPath('promotion_discount_cents', 20000);

        $this->assertSame(
            [''.$fixture['shop']->getKey() => 'SAVE20'],
            session(CouponSessionStore::SESSION_KEY),
        );
    }

    public function test_same_coupon_code_resolves_independently_per_shop_and_remove_is_scoped(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->productFixture(price: 1000);
        $second = $this->productFixture(price: 500);
        $this->couponPromotion($first, 'percentage_discount', 'SAVE20', ['value_percent' => '20.00']);
        $this->couponPromotion($second, 'fixed_discount', 'SAVE20', ['value_amount' => '100.00']);
        $cart = $this->guestCart('coupon-multishop-token');
        $this->cartItem($cart, $first['variant']);
        $this->cartItem($cart, $second['variant']);

        $session = [CartResolver::SESSION_TOKEN_KEY => 'coupon-multishop-token'];
        $this->withSession($session)
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $first['shop']->getKey()]), ['coupon_code' => 'SAVE20'])
            ->assertOk();
        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-multishop-token', CouponSessionStore::SESSION_KEY => session(CouponSessionStore::SESSION_KEY)])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $second['shop']->getKey()]), ['coupon_code' => 'SAVE20'])
            ->assertOk()
            ->assertJsonPath('promotion_discount_cents', 30000);

        $stored = session(CouponSessionStore::SESSION_KEY);
        $this->assertSame('SAVE20', $stored[$first['shop']->getKey()]);
        $this->assertSame('SAVE20', $stored[$second['shop']->getKey()]);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-multishop-token', CouponSessionStore::SESSION_KEY => $stored])
            ->deleteJson(route('storefront.cart.shops.coupon.destroy', ['shop' => $first['shop']->getKey()]))
            ->assertOk()
            ->assertJsonPath('promotion_discount_cents', 10000);

        $this->assertArrayNotHasKey($first['shop']->getKey(), session(CouponSessionStore::SESSION_KEY));
        $this->assertSame('SAVE20', session(CouponSessionStore::SESSION_KEY)[$second['shop']->getKey()]);
    }

    public function test_coupon_from_another_shop_cannot_apply_to_current_shop_group(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $first = $this->productFixture(price: 1000);
        $second = $this->productFixture(price: 1000);
        $this->couponPromotion($first, 'percentage_discount', 'SHOPA', ['value_percent' => '20.00']);
        $cart = $this->guestCart('coupon-wrong-shop-token');
        $this->cartItem($cart, $second['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-wrong-shop-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $second['shop']->getKey()]), ['coupon_code' => 'SHOPA'])
            ->assertUnprocessable()
            ->assertJsonPath('coupon.status', 'invalid');
    }

    public function test_invalid_coupon_states_return_structured_statuses(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000);
        $cart = $this->guestCart('coupon-invalid-token');
        $this->cartItem($cart, $fixture['variant']);

        $inactiveCoupon = $this->couponPromotion($fixture, 'fixed_discount', 'OFF', ['value_amount' => '100.00']);
        $inactiveCoupon->forceFill(['status' => PromotionCoupon::STATUS_INACTIVE])->save();
        $futureCoupon = $this->couponPromotion($fixture, 'fixed_discount', 'FUTURE', ['value_amount' => '100.00']);
        $futureCoupon->forceFill(['starts_at' => now()->addDay()])->save();
        $expiredCoupon = $this->couponPromotion($fixture, 'fixed_discount', 'OLD', ['value_amount' => '100.00']);
        $expiredCoupon->forceFill(['ends_at' => now()->subDay()])->save();
        $inactivePromotion = $this->couponPromotion($fixture, 'fixed_discount', 'PROMO', ['value_amount' => '100.00']);
        $inactivePromotion->promotion->forceFill(['status' => Promotion::STATUS_INACTIVE])->save();

        $baseSession = [CartResolver::SESSION_TOKEN_KEY => 'coupon-invalid-token'];
        $cases = [
            ['MISSING', 'invalid'],
            ['OFF', 'inactive'],
            ['FUTURE', 'not_started'],
            ['OLD', 'expired'],
            ['PROMO', 'inactive'],
        ];

        foreach ($cases as [$code, $status]) {
            $this->withSession($baseSession)
                ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => $code])
                ->assertUnprocessable()
                ->assertJsonPath('coupon.status', $status);
        }
    }

    public function test_coupon_and_automatic_promotions_compete_by_existing_conflict_rules(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 2000);
        $automatic = $this->cartPromotion($fixture, 'percentage_discount', ['value_percent' => '30.00']);
        $automatic->targets()->create(['target_role' => PromotionTarget::ROLE_ELIGIBLE, 'target_type' => PromotionTarget::TYPE_ALL, 'sort_order' => 10]);
        $this->couponPromotion($fixture, 'fixed_discount', 'SAVE500', ['value_amount' => '500.00']);
        $cart = $this->guestCart('coupon-conflict-token');
        $this->cartItem($cart, $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-conflict-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => 'SAVE500'])
            ->assertOk()
            ->assertJsonPath('coupon.status', 'valid_but_not_best')
            ->assertJsonPath('promotion_discount_cents', 60000);
    }

    public function test_coupon_fixed_discount_is_capped_and_fixed_price_without_benefit_is_ignored(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 300);
        $this->couponPromotion($fixture, 'fixed_discount', 'SAVE500', ['value_amount' => '500.00']);
        $noBenefitFixture = $this->productFixture(price: 750);
        $this->couponPromotion($noBenefitFixture, 'fixed_price', 'FIX799', ['value_amount' => '799.00']);
        $cart = $this->guestCart('coupon-caps-token');
        $this->cartItem($cart, $fixture['variant']);
        $this->cartItem($cart, $noBenefitFixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-caps-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => 'SAVE500'])
            ->assertOk()
            ->assertJsonPath('coupon.discount_cents', 30000);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-caps-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $noBenefitFixture['shop']->getKey()]), ['coupon_code' => 'FIX799'])
            ->assertOk()
            ->assertJsonPath('coupon.status', 'not_eligible');
    }

    public function test_temporarily_ineligible_coupon_state_is_retained_and_can_requalify(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000, stock: 5);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'SAVE100', ['value_amount' => '100.00']);
        $coupon->promotion->conditions()->create([
            'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
            'operator' => '>=',
            'value_numeric' => '2.00',
            'sort_order' => 10,
        ]);
        $cart = $this->guestCart('coupon-requalify-token');
        $item = $this->cartItem($cart, $fixture['variant'], 2);

        $session = [CartResolver::SESSION_TOKEN_KEY => 'coupon-requalify-token'];
        $this->withSession($session)
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => 'SAVE100'])
            ->assertOk()
            ->assertJsonPath('coupon.status', 'applied');

        $stored = session(CouponSessionStore::SESSION_KEY);
        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-requalify-token', CouponSessionStore::SESSION_KEY => $stored])
            ->patchJson(route('storefront.cart.items.update', $item), ['quantity' => 1])
            ->assertOk()
            ->assertJsonPath('shop_groups.0.coupon.status', 'not_eligible');
        $this->assertSame('SAVE100', session(CouponSessionStore::SESSION_KEY)[$fixture['shop']->getKey()]);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-requalify-token', CouponSessionStore::SESSION_KEY => session(CouponSessionStore::SESSION_KEY)])
            ->patchJson(route('storefront.cart.items.update', $item), ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('shop_groups.0.coupon.status', 'applied');
    }

    public function test_unsupported_coupon_reward_types_do_not_activate_in_phase_3d_a(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000);
        $cart = $this->guestCart('coupon-unsupported-token');
        $this->cartItem($cart, $fixture['variant'], 3);

        foreach ([
            'quantity_discount' => ['value_type' => 'amount', 'value_amount' => '100.00'],
            'fixed_bundle_price' => ['bundle_quantity' => 2, 'bundle_price' => '1000.00'],
            'tier_pricing' => ['tier_config' => [['min_quantity' => 2, 'unit_price' => '900.00']]],
            'buy_x_get_y_free' => ['buy_quantity' => 2, 'get_quantity' => 1],
            'buy_x_get_y_discount' => ['buy_quantity' => 2, 'get_quantity' => 1, 'value_percent' => '50.00'],
            'free_gift' => [],
        ] as $type => $reward) {
            $coupon = $this->couponPromotion($fixture, $type, Str::upper(Str::replace('_', '', $type)), $reward);
            if ($type === 'quantity_discount') {
                $coupon->promotion->conditions()->create(['condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY, 'operator' => '>=', 'value_numeric' => '2.00', 'sort_order' => 10]);
            }
            if (in_array($type, ['buy_x_get_y_free', 'buy_x_get_y_discount'], true)) {
                $coupon->promotion->targets()->create(['target_role' => PromotionTarget::ROLE_BUY, 'target_type' => PromotionTarget::TYPE_ALL, 'sort_order' => 20]);
                $coupon->promotion->targets()->create(['target_role' => PromotionTarget::ROLE_GET, 'target_type' => PromotionTarget::TYPE_ALL, 'sort_order' => 30]);
            }
            if ($type === 'free_gift') {
                $coupon->promotion->conditions()->create(['condition_type' => PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL, 'operator' => '>=', 'value_numeric' => '100.00', 'sort_order' => 10]);
                $coupon->promotion->targets()->create(['target_role' => PromotionTarget::ROLE_GIFT, 'target_type' => PromotionTarget::TYPE_VARIANT, 'target_id' => $fixture['variant']->getKey(), 'sort_order' => 20]);
            }

            $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-unsupported-token'])
                ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => $coupon->code])
                ->assertUnprocessable()
                ->assertJsonPath('coupon.status', 'unsupported_reward_type');
        }
    }

    public function test_guest_new_customer_only_coupon_is_presented_as_checkout_verification_required(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'WELCOME200', ['value_amount' => '200.00']);
        $coupon->promotion->forceFill(['new_customer_only' => true])->save();
        $cart = $this->guestCart('coupon-new-customer-token');
        $this->cartItem($cart, $fixture['variant']);

        $this->withSession([CartResolver::SESSION_TOKEN_KEY => 'coupon-new-customer-token'])
            ->postJson(route('storefront.cart.shops.coupon.store', ['shop' => $fixture['shop']->getKey()]), ['coupon_code' => 'WELCOME200'])
            ->assertOk()
            ->assertJsonPath('coupon.status', 'guest_verification_required')
            ->assertJsonPath('coupon.won', true)
            ->assertJsonPath('coupon.discount_cents', 20000)
            ->assertJsonPath('coupon.message', 'Coupon applied. Final eligibility will be confirmed after sign in.');
    }

    public function test_order_creation_revalidates_coupon_and_snapshots_coupon_metadata_only_when_coupon_wins(): void
    {
        $this->seed(PromotionTemplateSeeder::class);
        $fixture = $this->productFixture(price: 1000);
        $coupon = $this->couponPromotion($fixture, 'fixed_discount', 'SAVE200', ['value_amount' => '200.00']);
        $order = app(OrderCreationService::class)->create([
            'shop_id' => $fixture['shop']->getKey(),
            'customer_id' => null,
            'created_source' => \App\Models\Order::SOURCE_STOREFRONT,
            'order_status' => \App\Models\Order::STATUS_PENDING,
            'payment_status' => \App\Models\Order::PAYMENT_PENDING,
            'applied_coupon_code' => 'SAVE200',
            'items' => [[
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ]],
        ], $this->userFixture('coupon-order-actor@example.test'));

        $metadata = $order->items->first()->metadata['promotion'];
        $this->assertSame('coupon', $metadata['activation_type']);
        $this->assertSame($coupon->getKey(), $metadata['coupon_id']);
        $this->assertSame('SAVE200', $metadata['coupon_code']);

        $betterFixture = $this->productFixture(price: 1000);
        $auto = $this->cartPromotion($betterFixture, 'fixed_discount', ['value_amount' => '300.00']);
        $auto->targets()->create(['target_role' => PromotionTarget::ROLE_ELIGIBLE, 'target_type' => PromotionTarget::TYPE_ALL, 'sort_order' => 10]);
        $this->couponPromotion($betterFixture, 'fixed_discount', 'SAVE200', ['value_amount' => '200.00']);
        $order = app(OrderCreationService::class)->create([
            'shop_id' => $betterFixture['shop']->getKey(),
            'created_source' => \App\Models\Order::SOURCE_STOREFRONT,
            'order_status' => \App\Models\Order::STATUS_PENDING,
            'payment_status' => \App\Models\Order::PAYMENT_PENDING,
            'applied_coupon_code' => 'SAVE200',
            'items' => [[
                'product_variant_id' => $betterFixture['variant']->getKey(),
                'quantity' => 1,
            ]],
        ], $this->userFixture('coupon-order-auto-actor@example.test'));

        $metadata = $order->items->first()->metadata['promotion'];
        $this->assertSame('automatic', $metadata['activation_type']);
        $this->assertNull($metadata['coupon_id']);
        $this->assertNull($metadata['coupon_code']);
        $this->assertSame($auto->getKey(), $metadata['id']);
    }

    /**
     * @param array<string, mixed> $variantOverrides
     * @return array{merchant: MerchantProfile, shop: Shop, product: Product, variant: ProductVariant}
     */
    private function productFixture(array $variantOverrides = [], string $name = 'Cart Page Product', int $price = 199, int $stock = 5): array
    {
        $merchantUser = $this->userFixture('merchant-'.Str::uuid().'@example.test');
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Cart Page Merchant '.Str::random(8),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);
        $category = ProductCategory::query()->create([
            'name' => 'Cart Page Category '.Str::random(8),
            'slug' => 'cart-page-category-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Cart Page Shop '.Str::random(8),
            'slug' => 'cart-page-shop-'.Str::random(8),
            'address_line_1' => 'Fixture Street',
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $category->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $name,
            'slug' => 'cart-page-product-'.Str::random(8),
            'availability_status_id' => ProductAvailabilityStatus::query()
                ->where('merchant_id', $merchant->getKey())
                ->where('code', ProductAvailabilityStatus::CODE_IN_STOCK)
                ->value('id'),
            'status' => 'active',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create(array_merge([
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
        ], $variantOverrides));

        return compact('merchant', 'shop', 'product', 'variant');
    }

    private function cartItem(Cart $cart, ProductVariant $variant, int|float $quantity = 1, ?int $unitPrice = null): CartItem
    {
        return CartItem::query()->create([
            'cart_id' => $cart->getKey(),
            'shop_id' => $variant->shop_id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice ?? (int) $variant->selling_price,
        ]);
    }

    private function guestCart(string $token): Cart
    {
        return Cart::query()->create([
            'user_id' => null,
            'session_token' => $token,
        ]);
    }

    private function cartPromotion(array $fixture, string $templateCode, array $reward): Promotion
    {
        $template = PromotionTemplate::query()->where('code', $templateCode)->firstOrFail();
        $promotion = Promotion::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'promotion_template_id' => $template->getKey(),
            'name' => 'Cart Promotion '.Str::random(6),
            'slug' => 'cart-promotion-'.Str::random(8),
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

        return $promotion;
    }

    private function couponPromotion(array $fixture, string $templateCode, string $code, array $reward): PromotionCoupon
    {
        $promotion = $this->cartPromotion($fixture, $templateCode, $reward);
        $promotion->forceFill(['activation_type' => Promotion::ACTIVATION_COUPON])->save();
        $promotion->targets()->firstOrCreate([
            'target_role' => PromotionTarget::ROLE_ELIGIBLE,
            'target_type' => PromotionTarget::TYPE_ALL,
            'target_id' => null,
        ], ['sort_order' => 10]);

        return $promotion->coupons()->create([
            'shop_id' => $fixture['shop']->getKey(),
            'code' => $code,
            'status' => PromotionCoupon::STATUS_ACTIVE,
        ]);
    }

    private function attachVariantAttribute(ProductVariant $variant, string $groupName, string $valueName): void
    {
        $group = ProductAttributeGroup::query()->create([
            'name' => $groupName,
            'code' => Str::slug($groupName),
            'selection_type' => 'single',
            'status' => 'active',
        ]);
        $value = ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $group->getKey(),
            'name' => $valueName,
            'code' => Str::slug($valueName),
            'status' => 'active',
        ]);

        ProductVariantAttribute::query()->create([
            'product_variant_id' => $variant->getKey(),
            'product_attribute_group_id' => $group->getKey(),
            'product_attribute_group_value_id' => $value->getKey(),
        ]);
    }

    private function availabilityStatus(MerchantProfile $merchant, string $code): ProductAvailabilityStatus
    {
        return ProductAvailabilityStatus::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }

    private function userFixture(string $email): User
    {
        $user = User::query()->create([
            'name' => 'Cart Page User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->forceFill([
            'mobile' => '9'.random_int(100000000, 999999999),
            'status' => 'active',
        ])->save();

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
        $now = now();
        $name = Str::headline($slug);

        DB::table('auth_roles')->updateOrInsert([
            'slug' => $slug,
        ], [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'description' => $name.' role',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('auth_roles')->where('slug', $slug)->value('id');
    }

    private function shopSetting(Shop $shop, string $group, string $key, mixed $value, string $type): void
    {
        app(ShopSettingsService::class)->setTyped($shop->getKey(), $group, $key, $value, $type);
    }
}
