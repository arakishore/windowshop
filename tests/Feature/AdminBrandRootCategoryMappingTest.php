<?php

namespace Tests\Feature;

use App\Models\Brand;
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

class AdminBrandRootCategoryMappingTest extends TestCase
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

    public function test_admin_can_create_and_update_brand_shop_type_mappings(): void
    {
        $admin = $this->createAdminUser();
        $apparel = $this->createCategory('Apparel');
        $beauty = $this->createCategory('Beauty & Cosmetics');
        $makeup = $this->createCategory('Makeup', $beauty);

        $this->actingAs($admin)
            ->post(route('admin.master.brands.store'), [
                'name' => 'Mapped Brand',
                'description' => null,
                'website_url' => null,
                'sort_order' => 1,
                'status' => 'active',
                'root_product_category_ids' => [$apparel->getKey(), $beauty->getKey()],
            ])
            ->assertRedirect(route('admin.master.brands.index'))
            ->assertSessionHas('success', 'Brand created successfully.');

        $brand = Brand::query()->where('name', 'Mapped Brand')->firstOrFail();
        $apparelOnlyBrand = $this->createBrand('Apparel Only Brand', [$apparel]);

        $this->assertSame(
            [$apparel->getKey(), $beauty->getKey()],
            $brand->rootProductCategories()->orderBy('product_categories.id')->pluck('product_categories.id')->all(),
        );

        $this->actingAs($admin)
            ->get(route('admin.master.brands.index'))
            ->assertOk()
            ->assertSee('Applicable Shop Types')
            ->assertSee('Mapped Brand')
            ->assertSee('Apparel')
            ->assertSee('Beauty &amp; Cosmetics', false)
            ->assertDontSee('placeholder="brand-slug"', false);

        $this->actingAs($admin)
            ->get(route('admin.master.brands.index', ['root_product_category_id' => $beauty->getKey()]))
            ->assertOk()
            ->assertSee('Mapped Brand')
            ->assertDontSee($apparelOnlyBrand->name);

        $this->actingAs($admin)
            ->get(route('admin.master.brands.edit', $brand))
            ->assertOk()
            ->assertSee('Applicable Shop Types')
            ->assertSee('value="'.$apparel->getKey().'"', false)
            ->assertSee('value="'.$beauty->getKey().'"', false);

        $this->actingAs($admin)
            ->put(route('admin.master.brands.update', $brand), [
                'name' => 'Mapped Brand Updated',
                'description' => null,
                'website_url' => null,
                'sort_order' => 2,
                'status' => 'active',
                'root_product_category_ids' => [$beauty->getKey()],
            ])
            ->assertRedirect(route('admin.master.brands.edit', $brand));

        $this->assertSame(
            [$beauty->getKey()],
            $brand->fresh()->rootProductCategories()->pluck('product_categories.id')->all(),
        );

        $this->actingAs($admin)
            ->from(route('admin.master.brands.edit', $brand))
            ->put(route('admin.master.brands.update', $brand), [
                'name' => 'Mapped Brand Updated',
                'description' => null,
                'website_url' => null,
                'sort_order' => 2,
                'status' => 'active',
                'root_product_category_ids' => [$makeup->getKey()],
            ])
            ->assertRedirect(route('admin.master.brands.edit', $brand))
            ->assertSessionHasErrors('root_product_category_ids');
    }

    public function test_product_form_filters_and_validates_brands_by_shop_type(): void
    {
        $admin = $this->createAdminUser();
        $apparel = $this->createCategory('Apparel');
        $beauty = $this->createCategory('Beauty & Cosmetics');
        $shirts = $this->createCategory('Shirts', $apparel);
        $makeup = $this->createCategory('Makeup', $beauty);
        $apparelBrand = $this->createBrand('Apparel Brand', [$apparel]);
        $beautyBrand = $this->createBrand('Beauty Brand', [$beauty]);
        $apparelShop = $this->createShop($admin, $apparel);
        $beautyShop = $this->createShop($admin, $beauty);

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('Apparel Brand')
            ->assertSee('Beauty Brand')
            ->assertSee('data-root-category-ids="'.$apparel->getKey().'"', false)
            ->assertSee('data-root-category-ids="'.$beauty->getKey().'"', false);

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'shop_id' => $apparelShop->getKey(),
                'product_category_id' => $shirts->getKey(),
                'brand_id' => $beautyBrand->getKey(),
                'product_name' => 'Wrong Brand Product',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors('brand_id');

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'shop_id' => $apparelShop->getKey(),
                'product_category_id' => $shirts->getKey(),
                'brand_id' => $apparelBrand->getKey(),
                'product_name' => 'Right Brand Product',
                'status' => 'draft',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'product_name' => 'Right Brand Product',
            'brand_id' => $apparelBrand->getKey(),
        ]);

        $legacyProduct = Product::query()->create([
            'merchant_id' => $beautyShop->merchant_id,
            'shop_id' => $beautyShop->getKey(),
            'root_product_category_id' => $beauty->getKey(),
            'product_category_id' => $makeup->getKey(),
            'brand_id' => $apparelBrand->getKey(),
            'product_name' => 'Legacy Product',
            'slug' => 'legacy-product-'.Str::random(6),
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $legacyProduct))
            ->assertOk()
            ->assertSee('Apparel Brand')
            ->assertSee('data-current-selected="1"', false);
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'brand-root-admin-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'slug' => 'super_admin',
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

    private function createCategory(string $name, ?ProductCategory $parent = null): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    /**
     * @param array<int, ProductCategory> $rootCategories
     */
    private function createBrand(string $name, array $rootCategories): Brand
    {
        $brand = Brand::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $brand->rootProductCategories()->sync(collect($rootCategories)->map->getKey()->all());

        return $brand;
    }

    private function createShop(User $user, ProductCategory $rootCategory): Shop
    {
        $merchant = MerchantProfile::query()->firstOrCreate([
            'user_id' => $user->getKey(),
        ], [
            'business_name' => 'Demo Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $rootCategory->getKey(),
            'name' => 'Demo Shop '.Str::random(4),
            'slug' => 'demo-shop-'.Str::random(6),
            'address_line_1' => 'Nashik',
            'status' => 'active',
        ]);
    }
}
