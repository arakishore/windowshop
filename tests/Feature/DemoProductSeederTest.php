<?php

namespace Tests\Feature;

use Database\Seeders\DemoData\DemoMerchantSeeder;
use Database\Seeders\DemoData\DemoProductSeeder;
use Database\Seeders\DemoData\DemoShopSeeder;
use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DemoProductSeederTest extends TestCase
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

    public function test_demo_product_seeder_creates_one_base_variant_per_seeded_product(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        $this->seed(DemoMerchantSeeder::class);
        $this->seed(DemoShopSeeder::class);
        $this->seed(DemoProductSeeder::class);

        $productCount = DB::table('products')->count();

        $this->assertGreaterThan(0, $productCount);
        $this->assertSame($productCount, DB::table('product_variants')->count());
        $this->assertSame($productCount, DB::table('product_variants')->where('is_default', true)->count());
        $this->assertSame(0, DB::table('products')
            ->leftJoin('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->whereNull('product_variants.id')
            ->count());
        $this->assertSame(0, DB::table('products')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('short_description')
                    ->orWhere('short_description', '');
            })
            ->count());
        $this->assertSame(0, DB::table('product_variants')
            ->where(function ($query): void {
                $query->where('mrp', '<=', 0)
                    ->orWhere('selling_price', '<=', 0);
            })
            ->count());

        $initialVariantCount = DB::table('product_variants')->count();
        $this->seed(DemoProductSeeder::class);

        $this->assertSame($initialVariantCount, DB::table('product_variants')->count());
    }

    public function test_demo_product_seeder_uses_category_specific_products_for_beauty_and_bags(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        $this->seed(DemoMerchantSeeder::class);
        $this->seed(DemoShopSeeder::class);
        $this->seed(DemoProductSeeder::class);

        $beautyProducts = $this->productsForShop('grace-bloom-beauty');
        $bagProducts = $this->productsForShop('vana-accessories-corner');

        $this->assertGreaterThan(0, $beautyProducts->count());
        $this->assertGreaterThan(0, $bagProducts->count());

        $this->assertTrue($beautyProducts->every(
            fn ($product): bool => $product->root_category === 'Beauty & Cosmetics'
                && $product->category === 'Makeup'
                && ! str_contains(strtolower((string) $product->product_name), 't-shirt'),
        ));

        $this->assertTrue($bagProducts->every(
            fn ($product): bool => $product->root_category === 'Jewellery & Accessories'
                && $product->category === 'Bags'
            && ! str_contains(strtolower((string) $product->product_name), 't-shirt'),
        ));
    }

    public function test_demo_data_covers_every_active_root_shop_type_with_products(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        $this->seed(DemoMerchantSeeder::class);
        $this->seed(DemoShopSeeder::class);
        $this->seed(DemoProductSeeder::class);

        $rootCategories = DB::table('product_categories')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('name', 'id');

        $this->assertGreaterThan(0, $rootCategories->count());

        foreach ($rootCategories as $rootCategoryId => $rootCategoryName) {
            $shopIds = DB::table('shops')
                ->where('root_product_category_id', $rootCategoryId)
                ->whereNull('deleted_at')
                ->pluck('id');

            $this->assertGreaterThan(
                0,
                $shopIds->count(),
                "Missing demo shop for {$rootCategoryName}.",
            );

            $this->assertGreaterThan(
                0,
                DB::table('products')
                    ->whereIn('shop_id', $shopIds)
                    ->where('root_product_category_id', $rootCategoryId)
                    ->whereNull('deleted_at')
                    ->count(),
                "Missing demo products for {$rootCategoryName}.",
            );
        }
    }

    private function productsForShop(string $shopSlug)
    {
        return DB::table('products')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->join('product_categories as categories', 'categories.id', '=', 'products.product_category_id')
            ->join('product_categories as roots', 'roots.id', '=', 'products.root_product_category_id')
            ->where('shops.slug', $shopSlug)
            ->whereNull('products.deleted_at')
            ->get([
                'products.product_name',
                'categories.name as category',
                'roots.name as root_category',
            ]);
    }
}
