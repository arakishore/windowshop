<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use Database\Seeders\MasterData\ProductCategorySeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ProductCategorySeoSeederTest extends TestCase
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

    public function test_seeder_populates_empty_category_seo_without_creating_duplicates(): void
    {
        $apparel = ProductCategory::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel-1',
            'status' => 'active',
        ]);
        $women = ProductCategory::query()->create([
            'parent_id' => $apparel->getKey(),
            'name' => 'Women',
            'slug' => 'women-2',
            'status' => 'active',
        ]);
        $tshirts = ProductCategory::query()->create([
            'parent_id' => $women->getKey(),
            'name' => 'T-Shirts',
            'slug' => 't-shirts-3',
            'status' => 'active',
        ]);
        $shirts = ProductCategory::query()->create([
            'parent_id' => $women->getKey(),
            'name' => 'Shirts',
            'slug' => 'shirts-4',
            'status' => 'active',
            'meta_title' => "Women's Shirts Online | WindowShop",
            'meta_description' => "Explore women's Shirts from local shops, including new styles, offers and everyday favourites on WindowShop.",
        ]);
        $manual = ProductCategory::query()->create([
            'name' => 'Footwear',
            'slug' => 'footwear-5',
            'status' => 'active',
            'description' => 'Manual category content.',
            'meta_title' => 'Custom Footwear Title | WindowShop',
            'meta_description' => 'Custom footwear description.',
            'product_disclaimer' => 'Manual footwear disclaimer.',
        ]);
        $grocery = ProductCategory::query()->create([
            'name' => 'Grocery & Daily Needs',
            'slug' => 'grocery-6',
            'status' => 'active',
        ]);

        $initialCount = ProductCategory::query()->count();

        $this->seed(ProductCategorySeoSeeder::class);
        $this->seed(ProductCategorySeoSeeder::class);

        $this->assertSame($initialCount, ProductCategory::query()->count());
        $this->assertSame('Apparel & Fashion', $apparel->fresh()->meta_title);
        $this->assertSame("Women's Fashion & Clothing", $women->fresh()->meta_title);
        $this->assertSame("Women's T-Shirts Online", $tshirts->fresh()->meta_title);
        $this->assertSame("Women's Shirts Online", $shirts->fresh()->meta_title);
        $this->assertSame(
            "Explore women's Shirts from local shops, including new styles, offers and everyday favourites.",
            $shirts->fresh()->meta_description,
        );
        $this->assertSame(
            "Explore women's T-Shirts from local shops, including new styles, offers and everyday favourites.",
            $tshirts->fresh()->meta_description,
        );
        $this->assertSame(
            "Explore women's T-Shirts from local shops, including new styles, offers and everyday favourites.",
            $tshirts->fresh()->description,
        );
        $this->assertSame('Manual category content.', $manual->fresh()->description);
        $this->assertSame('Custom Footwear Title | WindowShop', $manual->fresh()->meta_title);
        $this->assertSame('Custom footwear description.', $manual->fresh()->meta_description);
        $this->assertStringContainsString('colors, fit, size and fabric', $apparel->fresh()->product_disclaimer);
        $this->assertStringContainsString('expiry', $grocery->fresh()->product_disclaimer);
        $this->assertSame('Manual footwear disclaimer.', $manual->fresh()->product_disclaimer);
    }
}
