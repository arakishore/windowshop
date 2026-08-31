<?php

namespace Tests\Feature;

use Database\Seeders\DemoData\DemoCollectionSeeder;
use Database\Seeders\DemoData\DemoMerchantSeeder;
use Database\Seeders\DemoData\DemoProductSeeder;
use Database\Seeders\DemoData\DemoShopSeeder;
use Database\Seeders\MasterData\SystemFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class DemoCollectionSeederTest extends TestCase
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

    public function test_demo_collection_seeder_creates_shop_scoped_collections_with_same_shop_products(): void
    {
        $this->seed(SystemFoundationSeeder::class);
        $this->seed(DemoMerchantSeeder::class);
        $this->seed(DemoShopSeeder::class);
        $this->seed(DemoProductSeeder::class);
        $this->seed(DemoCollectionSeeder::class);

        $collectionCount = DB::table('collections')->count();
        $membershipCount = DB::table('collection_products')->count();

        $this->assertGreaterThan(0, $collectionCount);
        $this->assertGreaterThan(0, $membershipCount);
        $this->assertSame(0, DB::table('collection_products')
            ->join('collections', 'collections.id', '=', 'collection_products.collection_id')
            ->join('products', 'products.id', '=', 'collection_products.product_id')
            ->whereColumn('collections.shop_id', '!=', 'products.shop_id')
            ->count());

        $this->seed(DemoCollectionSeeder::class);

        $this->assertSame($collectionCount, DB::table('collections')->count());
        $this->assertSame($membershipCount, DB::table('collection_products')->count());
    }
}
