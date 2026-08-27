<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontWishlistTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->currencySetting('symbol', 'INR ');
        $this->currencySetting('decimal_places', '2', AdminSetting::TYPE_INTEGER);
        $this->currencySetting('thousands_separator', ',');
        $this->currencySetting('decimal_separator', '.');
        $this->currencySetting('symbol_position', 'before');
    }

    public function test_customer_can_add_dedupe_remove_and_add_product_again(): void
    {
        $fixture = $this->fixture('Wishlist Merchant');
        $product = $this->product($fixture, 'Wishlist Tee');
        $variant = $this->variant($product, stock: 7);
        $customer = $this->customerUser('wishlist-add@example.test', 'Wishlist Customer', '9422945125');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $orderCount = Order::query()->count();
        $paymentStatusCount = DB::table('payment_statuses')->count();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->postJson(route('storefront.wishlist.products.store', $product))
            ->assertOk()
            ->assertJson(['wishlisted' => true, 'product_id' => $product->getKey()]);

        $firstItem = WishlistItem::query()->where('customer_id', $globalCustomer->getKey())->where('product_id', $product->getKey())->firstOrFail();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->postJson(route('storefront.wishlist.products.store', $product))
            ->assertOk()
            ->assertJson(['wishlisted' => true]);

        $this->assertSame(1, WishlistItem::query()->where('customer_id', $globalCustomer->getKey())->where('product_id', $product->getKey())->count());

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->deleteJson(route('storefront.wishlist.products.destroy', $product))
            ->assertOk()
            ->assertJson(['wishlisted' => false]);

        $this->assertDatabaseMissing('wishlist_items', ['id' => $firstItem->getKey()]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->postJson(route('storefront.wishlist.products.store', $product))
            ->assertOk();

        $secondItem = WishlistItem::query()->where('customer_id', $globalCustomer->getKey())->where('product_id', $product->getKey())->firstOrFail();

        $this->assertNotSame($firstItem->getKey(), $secondItem->getKey());
        $this->assertSame(7, (int) $variant->refresh()->stock_quantity);
        $this->assertSame($orderCount, Order::query()->count());
        $this->assertSame($paymentStatusCount, DB::table('payment_statuses')->count());
    }

    public function test_guest_is_sent_to_storefront_login_for_wishlist_ajax(): void
    {
        $fixture = $this->fixture('Guest Wishlist Merchant');
        $product = $this->product($fixture, 'Guest Wishlist Product');
        $this->variant($product);

        $this->postJson(route('storefront.wishlist.products.store', $product))
            ->assertUnauthorized()
            ->assertJson(['login_url' => route('storefront.login')]);

        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_customer_cannot_remove_another_customers_wishlist_item(): void
    {
        $fixture = $this->fixture('Ownership Wishlist Merchant');
        $product = $this->product($fixture, 'Scoped Wishlist Product');
        $this->variant($product);
        $owner = $this->customerUser('wishlist-owner@example.test', 'Wishlist Owner', '9422945126');
        $other = $this->customerUser('wishlist-other@example.test', 'Wishlist Other', '9422945127');
        $otherRoleId = $this->assignRole($other, 'customer');
        $ownerCustomer = $this->globalCustomer($owner);

        WishlistItem::query()->create([
            'customer_id' => $ownerCustomer->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->actingAs($other)
            ->withSession(['active_role_id' => $otherRoleId])
            ->deleteJson(route('storefront.wishlist.products.destroy', $product))
            ->assertOk()
            ->assertJson(['wishlisted' => false]);

        $this->assertDatabaseHas('wishlist_items', [
            'customer_id' => $ownerCustomer->getKey(),
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_listing_and_detail_show_active_wishlist_heart_state(): void
    {
        $fixture = $this->fixture('Active Heart Merchant');
        $product = $this->product($fixture, 'Active Heart Product');
        $this->variant($product);
        $customer = $this->customerUser('wishlist-heart@example.test', 'Heart Customer', '9422945128');
        $roleId = $this->assignRole($customer, 'customer');

        WishlistItem::query()->create([
            'customer_id' => $this->globalCustomer($customer)->getKey(),
            'product_id' => $product->getKey(),
        ]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.products'))
            ->assertOk()
            ->assertSee('data-wishlist-product-id="'.$product->getKey().'"', false)
            ->assertSee('is-wishlisted', false)
            ->assertSee('Remove from Wishlist');

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.product.show', $product->slug))
            ->assertOk()
            ->assertSee('product-wishlist-btn', false)
            ->assertSee('data-wishlist-state="1"', false)
            ->assertSee('Remove from Wishlist', false);
    }

    public function test_account_wishlist_page_shows_cross_merchant_products_and_empty_state(): void
    {
        $firstFixture = $this->fixture('First Wishlist Merchant');
        $secondFixture = $this->fixture('Second Wishlist Merchant');
        $firstProduct = $this->product($firstFixture, 'First Saved Product');
        $secondProduct = $this->product($secondFixture, 'Second Saved Product');
        $this->variant($firstProduct, mrp: '500.00', sellingPrice: '450.00');
        $this->variant($secondProduct, mrp: '900.00', sellingPrice: '800.00');
        $this->image($firstProduct, 'products/wishlist/first-thumb.webp');
        $customer = $this->customerUser('wishlist-page@example.test', 'Wishlist Page Customer', '9422945129');
        $roleId = $this->assignRole($customer, 'customer');
        $globalCustomer = $this->globalCustomer($customer);
        $address = $this->address($globalCustomer);

        WishlistItem::query()->create(['customer_id' => $globalCustomer->getKey(), 'product_id' => $firstProduct->getKey()]);
        WishlistItem::query()->create(['customer_id' => $globalCustomer->getKey(), 'product_id' => $secondProduct->getKey()]);

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.wishlist'))
            ->assertOk()
            ->assertSee('First Saved Product')
            ->assertSee('Second Saved Product')
            ->assertSee($firstFixture['shop']->name)
            ->assertSee($secondFixture['shop']->name)
            ->assertSee('INR 450.00')
            ->assertSee('View Product')
            ->assertSee('Remove')
            ->assertSee('account-empty-panel d-none', false);

        $this->assertSame(1, CustomerAddress::query()->where('id', $address->getKey())->count());

        WishlistItem::query()->where('customer_id', $globalCustomer->getKey())->delete();

        $this->actingAs($customer)
            ->withSession(['active_role_id' => $roleId])
            ->get(route('storefront.account.wishlist'))
            ->assertOk()
            ->assertSee('Your wishlist is empty.')
            ->assertSee('Save products you like and find them easily later.')
            ->assertSee('Continue Shopping');
    }

    /**
     * @return array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory}
     */
    private function fixture(string $merchantName): array
    {
        $merchant = $this->merchant($merchantName);
        $root = ProductCategory::query()->create([
            'name' => $merchantName.' Root',
            'slug' => Str::slug($merchantName).'-root-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => $merchantName.' Leaf',
            'slug' => Str::slug($merchantName).'-leaf-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $merchantName.' Shop',
            'slug' => Str::slug($merchantName).'-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return compact('merchant', 'shop', 'root', 'category');
    }

    private function merchant(string $name): MerchantProfile
    {
        $user = User::query()->create([
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(6).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name,
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
    }

    /**
     * @param array{merchant: MerchantProfile, shop: Shop, root: ProductCategory, category: ProductCategory} $fixture
     */
    private function product(array $fixture, string $name): Product
    {
        return Product::query()->create([
            'merchant_id' => $fixture['merchant']->getKey(),
            'shop_id' => $fixture['shop']->getKey(),
            'root_product_category_id' => $fixture['root']->getKey(),
            'product_category_id' => $fixture['category']->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'tax_mode' => 'inherit',
            'status' => 'active',
        ]);
    }

    private function variant(Product $product, string $mrp = '100.00', string $sellingPrice = '90.00', int $stock = 5): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'name' => $product->product_name,
            'mrp' => $mrp,
            'selling_price' => $sellingPrice,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
            'is_sellable' => true,
            'is_default' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);
    }

    private function image(Product $product, string $path): ProductImage
    {
        Storage::disk('public')->put($path, 'image');

        $image = ProductImage::query()->create([
            'product_id' => $product->getKey(),
            'image_path' => str_replace('-thumb.', '-original.', $path),
            'thumbnail_path' => $path,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $product->forceFill(['primary_image_id' => $image->getKey()])->save();

        return $image;
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

        $roleId = (int) DB::table('auth_roles')->where('slug', $slug)->value('id');

        DB::table('auth_user_roles')->updateOrInsert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $roleId;
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

    private function address(Customer $customer): CustomerAddress
    {
        return CustomerAddress::query()->create([
            'customer_id' => $customer->getKey(),
            'label' => 'Home',
            'recipient_name' => 'Wishlist Recipient',
            'recipient_mobile_country_code' => '+91',
            'recipient_mobile' => '9422945129',
            'recipient_mobile_normalized' => '919422945129',
            'address_line_1' => 'Wishlist Road',
            'postal_code' => '422009',
            'status' => CustomerAddress::STATUS_ACTIVE,
        ]);
    }

    private function currencySetting(string $key, string $value, string $type = AdminSetting::TYPE_STRING): void
    {
        AdminSetting::query()->updateOrCreate(
            ['group' => 'currency', 'setting_key' => $key],
            ['setting_value' => $value, 'setting_type' => $type],
        );
    }
}
