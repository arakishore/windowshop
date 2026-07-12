<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class ProductShopCategorySyncTest extends TestCase
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

    public function test_product_shop_category_is_copied_from_shop(): void
    {
        [$merchantId, $shopId, $shopCategoryId, $otherShopCategoryId, $productCategoryId] = $this->createProductSetup();

        $product = Product::query()->create([
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'shop_category_id' => $otherShopCategoryId,
            'product_category_id' => $productCategoryId,
            'product_name' => 'Premium Cotton Casual T-Shirt',
            'slug' => 'pending-'.Str::uuid(),
            'product_type' => 'simple',
            'status' => 'draft',
        ]);

        $this->assertSame($shopCategoryId, $product->fresh()->shop_category_id);
    }

    public function test_shop_category_change_updates_products(): void
    {
        [$merchantId, $shopId, $shopCategoryId, $otherShopCategoryId, $productCategoryId] = $this->createProductSetup();

        $product = Product::query()->create([
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'product_category_id' => $productCategoryId,
            'product_name' => 'Premium Cotton Casual T-Shirt',
            'slug' => 'pending-'.Str::uuid(),
            'product_type' => 'simple',
            'status' => 'draft',
        ]);

        $this->assertSame($shopCategoryId, $product->fresh()->shop_category_id);

        Shop::query()->findOrFail($shopId)->update([
            'shop_category_id' => $otherShopCategoryId,
        ]);

        $this->assertSame($otherShopCategoryId, $product->fresh()->shop_category_id);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function createProductSetup(): array
    {
        $userId = (int) DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => 'merchant-product-sync@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchantId = (int) DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'business_name' => 'Sync Merchant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shopCategoryId = $this->createShopCategory('Apparel', 'apparel');
        $otherShopCategoryId = $this->createShopCategory('Footwear', 'footwear');
        $productCategoryId = $this->createProductCategory('T-Shirts', 't-shirts');

        $shopId = (int) DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'shop_category_id' => $shopCategoryId,
            'name' => 'Sync Shop',
            'slug' => 'sync-shop',
            'address_line_1' => 'Main Road',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$merchantId, $shopId, $shopCategoryId, $otherShopCategoryId, $productCategoryId];
    }

    private function createShopCategory(string $name, string $slug): int
    {
        return (int) DB::table('shop_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProductCategory(string $name, string $slug): int
    {
        return (int) DB::table('product_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
