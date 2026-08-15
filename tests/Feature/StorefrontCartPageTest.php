<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cart\CartResolver;
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
}
