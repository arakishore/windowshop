<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontHomeCategoriesTest extends TestCase
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

    public function test_homepage_category_slider_uses_active_root_categories(): void
    {
        $category = $this->category('Local Fashion', imagePath: 'product-categories/local-fashion/web.webp');

        $response = $this->get(route('storefront.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('Shop By Categories', $section);
        $this->assertStringContainsString('Local Fashion', $section);
        $this->assertStringContainsString($this->categoryUrl($category), $section);
        $this->assertStringContainsString('/storage/product-categories/local-fashion/web.webp', $section);
        $this->assertStringNotContainsString('Outerwear', $section);
    }

    public function test_homepage_category_slider_excludes_inactive_and_child_categories(): void
    {
        $root = $this->category('Active Root');
        $this->category('Inactive Root', status: 'inactive');
        $this->category('Child Category', parent: $root);

        $response = $this->get(route('storefront.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('Active Root', $section);
        $this->assertStringNotContainsString('Inactive Root', $section);
        $this->assertStringNotContainsString('Child Category', $section);
    }

    public function test_homepage_category_slider_follows_sort_order_and_limit(): void
    {
        for ($i = 1; $i <= NavigationService::HOMEPAGE_CATEGORY_LIMIT + 1; $i++) {
            $this->category('Category '.$i, sortOrder: $i);
        }

        $response = $this->get(route('storefront.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('Category 1', $section);
        $this->assertStringContainsString('Category 8', $section);
        $this->assertStringNotContainsString('Category 9', $section);
        $this->assertLessThan(strpos($section, 'Category 2'), strpos($section, 'Category 1'));
        $this->assertLessThan(strpos($section, 'Category 3'), strpos($section, 'Category 2'));
    }

    public function test_homepage_category_without_image_uses_existing_fallback_asset(): void
    {
        $this->category('No Image Category');

        $response = $this->get(route('storefront.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('No Image Category', $section);
        $this->assertStringContainsString('assets/storefront/images/category/cate-1.jpg', $section);
    }

    public function test_homepage_category_slider_keeps_static_fallback_when_no_dynamic_categories_exist(): void
    {
        $response = $this->get(route('storefront.home'))->assertOk();
        $section = $this->categorySection($response->getContent());

        $this->assertStringContainsString('Outerwear', $section);
        $this->assertStringContainsString('Tops &amp; Shirts', $section);
        $this->assertStringContainsString('assets/storefront/images/category/cate-1.jpg', $section);
    }

    private function category(
        string $name,
        ?ProductCategory $parent = null,
        int $sortOrder = 0,
        string $status = 'active',
        ?string $imagePath = null,
    ): ProductCategory {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'image_path' => $imagePath,
            'sort_order' => $sortOrder,
            'status' => $status,
        ]);
    }

    private function categorySection(string $content): string
    {
        $start = strpos($content, '<section id="categories"');
        $this->assertIsInt($start);

        $end = strpos($content, '</section>', $start);
        $this->assertIsInt($end);

        return substr($content, $start, $end - $start);
    }

    private function categoryUrl(ProductCategory $category): string
    {
        return app(StorefrontUrlService::class)->category($category);
    }
}
