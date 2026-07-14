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

    public function test_demo_product_seeder_creates_products_without_seeded_variants(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        $this->seed(DemoMerchantSeeder::class);
        $this->seed(DemoShopSeeder::class);
        $this->seed(DemoProductSeeder::class);

        $this->assertGreaterThan(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('product_variants')->count());
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
