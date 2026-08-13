<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\User;
use App\Services\Storefront\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class StorefrontNavigationTest extends TestCase
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

        Cache::flush();
    }

    public function test_marketplace_menu_renders_active_root_category_hierarchy_in_order(): void
    {
        $zRoot = $this->category('Z Fashion', sortOrder: 20);
        $aRoot = $this->category('A Electronics', sortOrder: 10);
        $inactiveRoot = $this->category('Inactive Root', status: 'inactive');
        $deletedRoot = $this->category('Deleted Root');
        $deletedRoot->delete();

        $mobiles = $this->category('Mobiles', $aRoot, sortOrder: 1);
        $shirts = $this->category('Shirts', $zRoot, sortOrder: 1);
        $android = $this->category('Android Phones', $mobiles, sortOrder: 1);
        $inactiveChild = $this->category('Inactive Child', $aRoot, status: 'inactive');
        $deletedGrandchild = $this->category('Deleted Grandchild', $mobiles);
        $deletedGrandchild->delete();

        $response = $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('box-nav-menu', false)
            ->assertSee('mega-menu-item menu-lv-2', false)
            ->assertSee('A Electronics')
            ->assertSee('Z Fashion')
            ->assertSee('Mobiles')
            ->assertSee('Shirts')
            ->assertSee(route('storefront.category.child.show', [$zRoot->slug, $shirts->slug]), false)
            ->assertSee('mega-menu-drill-trigger', false)
            ->assertSee(route('storefront.category.child.show', [$aRoot->slug, $mobiles->slug]), false)
            ->assertSee('data-drill-target="mega-category-'.$mobiles->getKey().'"', false)
            ->assertSee('mega-menu-subcategory-grid', false)
            ->assertDontSee('sub-menu_list mega-menu-drill-list', false)
            ->assertSee('Back')
            ->assertSee('Android Phones')
            ->assertSee(route('storefront.category.child.show', [$mobiles->slug, $android->slug]), false)
            ->assertDontSee('View All')
            ->assertDontSee('Inactive Root')
            ->assertDontSee('Deleted Root')
            ->assertDontSee('Inactive Child')
            ->assertDontSee('Deleted Grandchild');

        $response->assertSeeInOrder(['A Electronics', 'Z Fashion']);
    }

    public function test_marketplace_menu_reflects_root_category_status_changes_immediately(): void
    {
        $fashion = $this->category('Fashion');
        $electronics = $this->category('Electronics');

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Fashion')
            ->assertSee('Electronics');

        $fashion->forceFill(['status' => 'inactive'])->save();
        $electronics->forceFill(['status' => 'inactive'])->save();

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertDontSee('Fashion')
            ->assertDontSee('Electronics');
    }

    public function test_top_navigation_contains_fixed_items_and_not_child_categories_as_top_items(): void
    {
        $root = $this->category('Fashion');
        $child = $this->category('Men', $root);

        $content = $this->get(route('storefront.home'))->assertOk()->getContent();

        $this->assertStringContainsString('<span class="text cus-text">Home</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">Categories</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">Shops</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">Brands</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">Offers</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">New Arrivals</span>', $content);
        $this->assertStringContainsString('<span class="text cus-text">Contact</span>', $content);
        $this->assertStringNotContainsString('<span class="text cus-text">'.$child->name.'</span>', $content);
    }

    public function test_merchant_menu_only_renders_categories_with_active_products_for_shop(): void
    {
        $fashion = $this->category('Fashion');
        $men = $this->category('Men', $fashion, sortOrder: 1);
        $women = $this->category('Women', $fashion, sortOrder: 2);
        $shirts = $this->category('Shirts', $men, sortOrder: 1);
        $jeans = $this->category('Jeans', $men, sortOrder: 2);
        $dresses = $this->category('Dresses', $women, sortOrder: 1);
        $electronics = $this->category('Electronics');
        $mobiles = $this->category('Mobiles', $electronics);
        $shop = $this->shop($fashion);
        $otherShop = $this->shop($electronics, 'Other Electronics');

        $this->product($shop, $fashion, $shirts, 'Active Shirt', 'active');
        $this->product($shop, $fashion, $jeans, 'Inactive Jeans', 'inactive');
        $this->product($otherShop, $electronics, $mobiles, 'Other Mobile', 'active');

        $response = $this->get(route('storefront.store.show', $shop->slug))
            ->assertOk()
            ->assertSee('Fashion')
            ->assertSee('Men')
            ->assertSee(route('storefront.store.category.show', [$shop->slug, $men->slug]), false)
            ->assertSee('mega-menu-drill-trigger', false)
            ->assertSee('Back')
            ->assertSee(route('storefront.store.category.show', [$shop->slug, $shirts->slug]), false)
            ->assertSee('Shirts')
            ->assertDontSee('Women')
            ->assertDontSee('Dresses')
            ->assertDontSee('Jeans')
            ->assertDontSee('Electronics')
            ->assertDontSee('Mobiles');

        $response->assertSeeInOrder(['Fashion', 'Men']);
    }

    public function test_store_category_placeholder_route_does_not_implement_product_listing(): void
    {
        $root = $this->category('Fashion');
        $child = $this->category('Men', $root);
        $shop = $this->shop($root);

        $this->product($shop, $root, $child, 'Active Shirt', 'active');

        $this->get(route('storefront.category.show', $child->slug))
            ->assertOk()
            ->assertSee('Men')
            ->assertSee('No products found.');

        $this->get(route('storefront.store.category.show', [$shop->slug, $child->slug]))
            ->assertOk()
            ->assertSee('Merchant category product listing is intentionally deferred.');
    }

    public function test_navigation_service_uses_bounded_queries_for_loaded_trees(): void
    {
        $root = $this->category('Fashion');
        $children = collect(range(1, 8))->map(fn (int $index) => $this->category('Child '.$index, $root, $index));

        $children->each(function (ProductCategory $child): void {
            collect(range(1, 3))->each(fn (int $index) => $this->category($child->name.' Leaf '.$index, $child, $index));
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(NavigationService::class)->getMarketplaceCategories();

        $marketplaceQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(3, $marketplaceQueries);

        $shop = $this->shop($root);
        $this->product($shop, $root, $children->first()->children->first(), 'Active Leaf Product', 'active');

        Cache::flush();
        DB::flushQueryLog();

        app(NavigationService::class)->getMerchantCategories($shop);

        $merchantQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $merchantQueries);
    }

    private function category(string $name, ?ProductCategory $parent = null, int $sortOrder = 0, string $status = 'active'): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'sort_order' => $sortOrder,
            'status' => $status,
        ]);
    }

    private function shop(ProductCategory $root, string $name = 'Demo Fashion Shop'): Shop
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::random(6).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $name.' Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
        ]);
    }

    private function product(Shop $shop, ProductCategory $root, ProductCategory $category, string $name, string $status): Product
    {
        return Product::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => $status,
        ]);
    }
}
