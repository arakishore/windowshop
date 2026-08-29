<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductAvailability\MerchantAvailabilityStatusSeeder;
use App\Services\Storefront\StorefrontUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontAddToCartTest extends TestCase
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

    public function test_guest_can_add_active_variant_to_session_cart(): void
    {
        $fixture = $this->productFixture();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart_count', '2');

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertNotEmpty($cart->session_token);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'product_id' => $fixture['product']->getKey(),
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => '2.000',
            'unit_price' => '199.00',
        ]);
    }

    public function test_logged_in_customer_cart_uses_global_user_id(): void
    {
        $customer = $this->userFixture('customer@example.test');
        $customerRoleId = $this->assignRole($customer, 'customer');
        $fixture = $this->productFixture();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $customerRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('cart_count', '1');

        $cart = Cart::query()->firstOrFail();

        $this->assertSame($customer->getKey(), $cart->user_id);
        $this->assertNull($cart->session_token);
    }

    public function test_admin_context_uses_guest_cart(): void
    {
        $admin = $this->userFixture('admin@example.test');
        $adminRoleId = $this->assignRole($admin, 'admin');
        $fixture = $this->productFixture();

        $this->actingAs($admin)
            ->withSession(['active_role_id' => $adminRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertNotEmpty($cart->session_token);
    }

    public function test_super_admin_context_uses_guest_cart(): void
    {
        $admin = $this->userFixture('super-admin@example.test');
        $superAdminRoleId = $this->assignRole($admin, 'super_admin');
        $fixture = $this->productFixture();

        $this->actingAs($admin)
            ->withSession(['active_role_id' => $superAdminRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertNotEmpty($cart->session_token);
    }

    public function test_merchant_context_uses_guest_cart(): void
    {
        $merchant = $this->userFixture('merchant-context@example.test');
        $merchantRoleId = $this->assignRole($merchant, 'merchant');
        $fixture = $this->productFixture();

        $this->actingAs($merchant)
            ->withSession(['active_role_id' => $merchantRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertNotEmpty($cart->session_token);
    }

    public function test_multi_role_user_with_merchant_active_uses_guest_cart(): void
    {
        $user = $this->userFixture('multi-merchant@example.test');
        $merchantRoleId = $this->assignRole($user, 'merchant');
        $this->assignRole($user, 'customer');
        $fixture = $this->productFixture();

        $this->actingAs($user)
            ->withSession(['active_role_id' => $merchantRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertNotEmpty($cart->session_token);
    }

    public function test_multi_role_user_with_customer_active_uses_user_cart(): void
    {
        $user = $this->userFixture('multi-customer@example.test');
        $this->assignRole($user, 'merchant');
        $customerRoleId = $this->assignRole($user, 'customer');
        $fixture = $this->productFixture();

        $this->actingAs($user)
            ->withSession(['active_role_id' => $customerRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $cart = Cart::query()->firstOrFail();

        $this->assertSame($user->getKey(), $cart->user_id);
        $this->assertNull($cart->session_token);
    }

    public function test_same_guest_session_reuses_guest_cart_for_backoffice_context(): void
    {
        $admin = $this->userFixture('same-session-admin@example.test');
        $adminRoleId = $this->assignRole($admin, 'admin');
        $first = $this->productFixture(['sku' => 'SESSION-A']);
        $second = $this->productFixture(['sku' => 'SESSION-B']);

        $this->actingAs($admin)
            ->withSession(['active_role_id' => $adminRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $first['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->withSession(['active_role_id' => $adminRoleId])
            ->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $second['variant']->getKey(),
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(2, CartItem::query()->count());
        $this->assertNull(Cart::query()->firstOrFail()->user_id);
    }

    public function test_adding_same_variant_increments_single_cart_item(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 8]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('cart_count', '3');

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame('3.000', CartItem::query()->firstOrFail()->quantity);
    }

    public function test_different_variants_and_shops_can_share_one_cart(): void
    {
        $first = $this->productFixture(['sku' => 'SKU-A']);
        $second = $this->productFixture(['sku' => 'SKU-B']);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $first['variant']->getKey(),
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $second['variant']->getKey(),
            'quantity' => 1,
        ])->assertCreated();

        $cart = Cart::query()->with('items')->firstOrFail();

        $this->assertCount(2, $cart->items);
        $this->assertEqualsCanonicalizing(
            [$first['shop']->getKey(), $second['shop']->getKey()],
            $cart->items->pluck('shop_id')->all(),
        );
    }

    public function test_different_variants_for_same_product_create_separate_cart_items(): void
    {
        $fixture = $this->productFixture(['sku' => 'SKU-M']);
        $secondVariant = ProductVariant::query()->create([
            'product_id' => $fixture['product']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'sku' => 'SKU-L',
            'name' => 'Large',
            'mrp' => 399,
            'selling_price' => 299,
            'stock_quantity' => 5,
            'is_default' => false,
            'is_sellable' => true,
            'status' => 'active',
        ]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $secondVariant->getKey(),
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame(2, CartItem::query()->count());
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $fixture['product']->getKey(),
            'product_variant_id' => $fixture['variant']->getKey(),
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $fixture['product']->getKey(),
            'product_variant_id' => $secondVariant->getKey(),
        ]);
    }

    public function test_client_submitted_price_is_ignored(): void
    {
        $fixture = $this->productFixture(['selling_price' => 249]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 1,
            'unit_price' => 1,
        ])->assertCreated();

        $this->assertSame('249.00', CartItem::query()->firstOrFail()->unit_price);
    }

    public function test_quantity_above_available_stock_is_rejected(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 7]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 8,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonFragment([
                'quantity' => ['Only 7 items are currently available.'],
            ])
            ->assertJsonMissing([
                'Only 7.000 items are currently available.',
            ]);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_backorder_can_be_added_beyond_physical_stock(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 5]);
        $fixture['product']->forceFill([
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_BACKORDER)->getKey(),
        ])->save();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('cart_count', '7');

        $this->assertSame('7.000', CartItem::query()->firstOrFail()->quantity);
    }

    public function test_preorder_can_be_added_with_zero_stock(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 0]);
        $fixture['product']->forceFill([
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_PREORDER)->getKey(),
        ])->save();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])->assertCreated();

        $this->assertSame('2.000', CartItem::query()->firstOrFail()->quantity);
    }

    public function test_out_of_stock_status_rejects_add_to_cart_even_when_stock_exists(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 5]);
        $fixture['product']->forceFill([
            'availability_status_id' => $this->availabilityStatus($fixture['merchant'], ProductAvailabilityStatus::CODE_OUT_OF_STOCK)->getKey(),
        ])->save();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_variant_id');

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_decimal_quantity_stock_message_trims_trailing_zeroes(): void
    {
        $fixture = $this->productFixture([
            'allow_decimal_quantity' => true,
            'stock_quantity' => 1.5,
        ]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonFragment([
                'quantity' => ['Only 1.5 items are currently available.'],
            ]);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_stock_validation_includes_existing_cart_quantity(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 3]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])->assertCreated();

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame('2.000', CartItem::query()->firstOrFail()->quantity);
    }

    public function test_inactive_product_shop_or_variant_cannot_be_added(): void
    {
        $inactiveVariant = $this->productFixture(['status' => 'inactive']);
        $inactiveProduct = $this->productFixture([], ['status' => 'inactive']);
        $inactiveShop = $this->productFixture([], [], ['status' => 'inactive']);

        foreach ([$inactiveVariant, $inactiveProduct, $inactiveShop] as $fixture) {
            $this->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('product_variant_id');
        }

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_soft_deleted_product_shop_or_variant_cannot_be_added(): void
    {
        $deletedVariant = $this->productFixture();
        $deletedVariant['variant']->delete();

        $deletedProduct = $this->productFixture();
        $deletedProduct['product']->delete();

        $deletedShop = $this->productFixture();
        $deletedShop['shop']->delete();

        foreach ([$deletedVariant, $deletedProduct, $deletedShop] as $fixture) {
            $this->postJson(route('storefront.cart.items.store'), [
                'product_variant_id' => $fixture['variant']->getKey(),
                'quantity' => 1,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('product_variant_id');
        }

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_storefront_header_count_reflects_current_cart_quantity(): void
    {
        $fixture = $this->productFixture(['stock_quantity' => 5]);

        $this->postJson(route('storefront.cart.items.store'), [
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 3,
        ])->assertCreated();

        $this->get($this->productUrl($fixture['product']))
            ->assertOk()
            ->assertSee('data-storefront-cart-count>3</span>', false);
    }

    public function test_header_count_uses_guest_context_for_authenticated_admin(): void
    {
        $admin = $this->userFixture('header-admin@example.test');
        $adminRoleId = $this->assignRole($admin, 'admin');
        $fixture = $this->productFixture(['stock_quantity' => 5]);

        Cart::query()->create([
            'user_id' => $admin->getKey(),
        ])->items()->create([
            'shop_id' => $fixture['shop']->getKey(),
            'product_id' => $fixture['product']->getKey(),
            'product_variant_id' => $fixture['variant']->getKey(),
            'quantity' => 4,
            'unit_price' => 199,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_role_id' => $adminRoleId])
            ->get($this->productUrl($fixture['product']))
            ->assertOk()
            ->assertSee('data-storefront-cart-count>0</span>', false)
            ->assertDontSee('data-storefront-cart-count>4</span>', false);
    }

    /**
     * @param array<string, mixed> $variantOverrides
     * @param array<string, mixed> $productOverrides
     * @param array<string, mixed> $shopOverrides
     * @return array{merchant: MerchantProfile, shop: Shop, product: Product, variant: ProductVariant}
     */
    private function productFixture(array $variantOverrides = [], array $productOverrides = [], array $shopOverrides = []): array
    {
        $merchantUser = $this->userFixture('merchant-'.Str::uuid().'@example.test');
        $merchant = MerchantProfile::query()->create([
            'user_id' => $merchantUser->getKey(),
            'business_name' => 'Cart Fixture Merchant '.Str::random(8),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        app(MerchantAvailabilityStatusSeeder::class)->seedDefaultsForMerchant($merchant);
        $category = ProductCategory::query()->create([
            'name' => 'Cart Category '.Str::random(8),
            'slug' => 'cart-category-'.Str::random(8),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create(array_merge([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Cart Shop '.Str::random(8),
            'slug' => 'cart-shop-'.Str::random(8),
            'address_line_1' => 'Fixture Street',
            'status' => 'active',
        ], $shopOverrides));
        $product = Product::query()->create(array_merge([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $category->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Cart Product '.Str::random(8),
            'slug' => 'cart-product-'.Str::random(8),
            'availability_status_id' => ProductAvailabilityStatus::query()
                ->where('merchant_id', $merchant->getKey())
                ->where('code', ProductAvailabilityStatus::CODE_IN_STOCK)
                ->value('id'),
            'status' => 'active',
            'published_at' => now(),
        ], $productOverrides));
        $variant = ProductVariant::query()->create(array_merge([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'sku' => 'SKU-'.Str::random(8),
            'name' => 'Default',
            'mrp' => 299,
            'selling_price' => 199,
            'stock_quantity' => 5,
            'is_default' => true,
            'is_sellable' => true,
            'status' => 'active',
        ], $variantOverrides));

        return compact('merchant', 'shop', 'product', 'variant');
    }

    private function userFixture(string $email): User
    {
        $user = User::query()->create([
            'name' => 'Cart User',
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

    private function productUrl(Product $product): string
    {
        return app(StorefrontUrlService::class)->product($product);
    }

    private function availabilityStatus(MerchantProfile $merchant, string $code): ProductAvailabilityStatus
    {
        return ProductAvailabilityStatus::query()
            ->where('merchant_id', $merchant->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }
}
