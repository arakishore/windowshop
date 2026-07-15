<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantProductManagementTest extends TestCase
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

    public function test_merchant_can_create_product_for_active_shop(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $category->getKey(),
                'brand_id' => null,
                'product_name' => 'Merchant Shirt',
                'status' => 'active',
            ])
            ->assertRedirect();

        $product = Product::query()->where('product_name', 'Merchant Shirt')->firstOrFail();

        $this->assertSame($shop->getKey(), $product->shop_id);
        $this->assertSame($shop->merchant_id, $product->merchant_id);
        $this->assertSame('active', $product->status);
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
    }

    public function test_merchant_cannot_access_another_shop_product(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();
        [$otherUser, $otherShop] = $this->merchantFixture('other-merchant@example.test', 'Other Shop');
        $product = Product::query()->create([
            'merchant_id' => $otherShop->merchant_id,
            'shop_id' => $otherShop->getKey(),
            'root_product_category_id' => $otherShop->root_product_category_id,
            'product_category_id' => $category->getKey(),
            'product_name' => 'Other Product',
            'slug' => 'other-product-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.edit', $product))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.index'))
            ->assertOk()
            ->assertDontSee('Other Product');

        $this->assertTrue($otherUser->exists);
    }

    public function test_merchant_can_duplicate_only_active_shop_product(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();
        [$otherUser, $otherShop] = $this->merchantFixture('duplicate-other@example.test', 'Other Duplicate Shop');
        $product = Product::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $category->getKey(),
            'product_name' => 'Merchant Duplicate Source',
            'slug' => 'merchant-duplicate-source-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherProduct = Product::query()->create([
            'merchant_id' => $otherShop->merchant_id,
            'shop_id' => $otherShop->getKey(),
            'root_product_category_id' => $otherShop->root_product_category_id,
            'product_category_id' => $category->getKey(),
            'product_name' => 'Other Duplicate Source',
            'slug' => 'other-duplicate-source-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.duplicate', $product))
            ->assertRedirect();

        $duplicate = Product::query()
            ->where('shop_id', $shop->getKey())
            ->where('product_name', 'Merchant Duplicate Source - Copy')
            ->firstOrFail();

        $this->assertSame($shop->merchant_id, $duplicate->merchant_id);
        $this->assertSame($shop->root_product_category_id, $duplicate->root_product_category_id);
        $this->assertSame('draft', $duplicate->status);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.duplicate', $otherProduct))
            ->assertNotFound();

        $this->assertSame(1, Product::query()->where('product_name', 'Other Duplicate Source')->count());
        $this->assertSame(0, Product::query()->where('product_name', 'Other Duplicate Source - Copy')->count());
        $this->assertTrue($otherUser->exists);
    }

    /**
     * @return array{0: User, 1: Shop, 2: ProductCategory}
     */
    private function merchantFixture(string $email = 'merchant@example.test', string $shopName = 'Demo Shop'): array
    {
        $user = $this->createMerchantUser($email);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Demo Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $shopName,
            'slug' => Str::slug($shopName).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);

        return [$user, $shop, $category];
    }

    private function createMerchantUser(string $email): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => $email,
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
