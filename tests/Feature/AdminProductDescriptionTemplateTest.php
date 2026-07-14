<?php

namespace Tests\Feature;

use App\Models\ProductDescriptionTemplate;
use App\Models\Brand;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Product\ProductDescriptionTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductDescriptionTemplateTest extends TestCase
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

    public function test_admin_can_manage_description_templates_without_laravel_pagination(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel', 'apparel');
        $template = $this->createTemplate($category, [
            'name' => 'Apparel Default',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.master.description-templates.index'))
            ->assertOk()
            ->assertSee('Apparel Default')
            ->assertSee('description-templates-table')
            ->assertSee('DataTable')
            ->assertDontSee('Showing 1 to', false);

        $this->actingAs($admin)
            ->put(route('admin.master.description-templates.update', $template), [
                'product_category_id' => $category->getKey(),
                'name' => 'Updated Apparel Default',
                'short_description_template' => 'Buy {product_name}',
                'description_template' => 'Buy {product_name} from {shop_name}.',
                'status' => 'active',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.master.description-templates.edit', $template))
            ->assertSessionHas('success', 'Description template updated successfully.');

        $this->assertDatabaseHas('product_description_templates', [
            'id' => $template->getKey(),
            'name' => 'Updated Apparel Default',
            'sort_order' => 5,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.description-templates.destroy', $template->fresh()))
            ->assertRedirect(route('admin.master.description-templates.index'))
            ->assertSessionHas('success', 'Description template deleted successfully.');

        $this->assertDatabaseMissing('product_description_templates', [
            'id' => $template->getKey(),
        ]);
    }

    public function test_only_one_active_template_is_allowed_per_category(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel', 'apparel');

        $this->createTemplate($category, [
            'name' => 'First Active',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.master.description-templates.create'))
            ->post(route('admin.master.description-templates.store'), [
                'product_category_id' => $category->getKey(),
                'name' => 'Second Active',
                'short_description_template' => '{product_name}',
                'description_template' => '{product_name}',
                'status' => 'active',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.master.description-templates.create'))
            ->assertSessionHasErrors('status');
    }

    public function test_service_generates_descriptions_and_handles_missing_values(): void
    {
        $category = $this->createCategory('Kurtis', 'kurtis');
        $this->createTemplate($category, [
            'short_description_template' => '{product_name} {color} {category} by {brand}.',
            'description_template' => 'Made from {material}. Sizes: {sizes}. Sold by {shop_name}.',
            'status' => 'active',
        ]);

        $result = app(ProductDescriptionTemplateService::class)->generateForCategory($category, [
            'product_name' => 'Cotton Kurti',
            'color' => 'Red',
            'brand' => '',
            'material' => 'Cotton',
        ]);

        $this->assertTrue($result['found']);
        $this->assertSame('Cotton Kurti Red Kurtis.', $result['short_description']);
        $this->assertSame('Made from Cotton. Sizes. Sold.', $result['description']);
    }

    public function test_service_returns_clear_result_when_no_active_template_exists(): void
    {
        $category = $this->createCategory('Footwear', 'footwear');

        $result = app(ProductDescriptionTemplateService::class)->generateForCategory($category, [
            'product_name' => 'Sneaker',
        ]);

        $this->assertFalse($result['found']);
        $this->assertSame('No active description template is available for the selected category.', $result['message']);
        $this->assertNull($result['short_description']);
        $this->assertNull($result['description']);
    }

    public function test_admin_can_preview_generated_description(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel', 'apparel');
        $template = $this->createTemplate($category, [
            'short_description_template' => '{product_name} in {color}',
            'description_template' => '{product_name} made from {material} at {shop_name}.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.master.description-templates.preview.generate', $template), [
                'product_name' => 'Linen Shirt',
                'color' => 'Blue',
                'material' => 'Linen',
                'shop_name' => 'Urban Shop',
            ])
            ->assertOk()
            ->assertSee('Linen Shirt in Blue')
            ->assertSee('Linen Shirt made from Linen at Urban Shop.');
    }

    public function test_product_creation_generates_description_from_exact_category_template(): void
    {
        $admin = $this->createAdminUser();
        $rootCategory = $this->createCategory('Apparel', 'apparel');
        $category = $this->createCategory('T-Shirts', 't-shirts', $rootCategory);
        $shop = $this->createShop($admin, $rootCategory);
        $brand = $this->createBrand('Acme');

        $this->createTemplate($category, [
            'short_description_template' => '{product_name} by {brand} at {shop_name}.',
            'description_template' => "{product_name}\n- Category: {category_path}\n- Material: {material}",
            'meta_title_template' => '{product_name} | {brand}',
            'meta_description_template' => 'Buy {product_name} from {shop_name}.',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $category->getKey(),
                'brand_id' => $brand->getKey(),
                'product_name' => 'Premium Cotton Tee',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $product = Product::query()->where('product_name', 'Premium Cotton Tee')->firstOrFail();

        $this->assertSame('draft', $product->status);
        $this->assertSame($rootCategory->getKey(), $product->root_product_category_id);
        $this->assertSame('Premium Cotton Tee by Acme at Demo Shop.', $product->short_description);
        $this->assertStringContainsString('Premium Cotton Tee', (string) $product->description);
        $this->assertStringNotContainsString('{material}', (string) $product->description);
        $this->assertSame('Premium Cotton Tee | Acme', $product->meta_title);
        $this->assertSame('Buy Premium Cotton Tee from Demo Shop.', $product->meta_description);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'name' => 'Premium Cotton Tee',
            'mrp' => 0,
            'selling_price' => 0,
            'stock_quantity' => 0,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSeeInOrder([
                'Basic Information',
                'Attributes',
                'Variants &amp; Inventory',
                'Images',
                'Description',
                'SEO',
            ], false);
    }

    public function test_product_create_dropdown_disables_root_categories(): void
    {
        $admin = $this->createAdminUser();
        $rootCategory = $this->createCategory('Apparel', 'apparel');
        $this->createCategory('T-Shirts', 't-shirts', $rootCategory);

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('value="'.$rootCategory->getKey().'"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('Product Type')
            ->assertSee('Apparel > T-Shirts');
    }

    public function test_product_basic_information_updates_without_product_type(): void
    {
        $admin = $this->createAdminUser();
        $rootCategory = $this->createCategory('Apparel', 'apparel');
        $category = $this->createCategory('T-Shirts', 't-shirts', $rootCategory);
        $shop = $this->createShop($admin, $rootCategory);
        $brand = $this->createBrand('Updated Brand');
        $product = $this->createProduct($category, [
            'product_name' => 'Original Product',
        ]);

        $product->forceFill([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $rootCategory->getKey(),
            'product_category_id' => $category->getKey(),
            'brand_id' => null,
        ])->save();
        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $shop->getKey(),
            'sku' => 'UPDATED-BASE',
            'name' => $product->product_name,
            'mrp' => 999,
            'selling_price' => 799,
            'stock_quantity' => 0,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $category->getKey(),
                'brand_id' => $brand->getKey(),
                'product_name' => 'Updated Product',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHas('success', 'Product basic information updated successfully.');

        $this->assertDatabaseHas('products', [
            'id' => $product->getKey(),
            'product_name' => 'Updated Product',
            'brand_id' => $brand->getKey(),
            'status' => 'active',
        ]);
    }

    public function test_product_creation_rejects_root_product_category(): void
    {
        $admin = $this->createAdminUser();
        $rootCategory = $this->createCategory('Apparel', 'apparel');
        $shop = $this->createShop($admin, $rootCategory);

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $rootCategory->getKey(),
                'brand_id' => null,
                'product_name' => 'Root Category Product',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors('product_category_id');
    }

    public function test_product_creation_rejects_category_outside_selected_shop_type(): void
    {
        $admin = $this->createAdminUser();
        $apparel = $this->createCategory('Apparel', 'apparel');
        $footwear = $this->createCategory('Footwear', 'footwear');
        $sneakers = $this->createCategory('Sneakers', 'sneakers', $footwear);
        $shop = $this->createShop($admin, $apparel);

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $sneakers->getKey(),
                'brand_id' => null,
                'product_name' => 'Wrong Root Product',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors('product_category_id');
    }

    public function test_parent_category_template_fallback_is_used_when_exact_template_is_missing(): void
    {
        $parent = $this->createCategory('Men', 'men');
        $child = $this->createCategory('Shirts', 'shirts', $parent);
        $product = $this->createProduct($child);

        $this->createTemplate($parent, [
            'short_description_template' => '{product_name} parent fallback for {product_category}.',
            'description_template' => 'Path: {category_path}.',
            'status' => 'active',
        ]);

        app(ProductDescriptionTemplateService::class)->applyToProduct($product);

        $product->refresh();

        $this->assertSame('Oxford Shirt parent fallback for Shirts.', $product->short_description);
        $this->assertSame('Path: Men > Shirts.', $product->description);
    }

    public function test_missing_template_does_not_block_product_creation(): void
    {
        $admin = $this->createAdminUser();
        $rootCategory = $this->createCategory('Footwear', 'footwear');
        $category = $this->createCategory('Sneakers', 'sneakers', $rootCategory);
        $shop = $this->createShop($admin, $rootCategory);
        $brand = $this->createBrand('Acme');

        $this->actingAs($admin)
            ->post(route('admin.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $category->getKey(),
                'brand_id' => $brand->getKey(),
                'product_name' => 'Canvas Sneaker',
                'status' => 'draft',
            ])
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertDatabaseHas('products', [
            'product_name' => 'Canvas Sneaker',
            'short_description' => null,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);
    }

    public function test_unresolved_placeholders_are_removed_from_generated_output(): void
    {
        $category = $this->createCategory('Kurtis', 'kurtis');
        $product = $this->createProduct($category, ['product_name' => 'Daily Kurti']);

        $this->createTemplate($category, [
            'short_description_template' => '{product_name} in {material}.',
            'description_template' => "Details\n- Material: {material}\n- Fit: {fit}\nSold by {shop_name}.",
            'status' => 'active',
        ]);

        app(ProductDescriptionTemplateService::class)->applyToProduct($product);

        $product->refresh();

        $this->assertStringNotContainsString('{material}', (string) $product->short_description);
        $this->assertStringNotContainsString('{fit}', (string) $product->description);
        $this->assertStringNotContainsString('Material:', (string) $product->description);
        $this->assertStringContainsString('Sold by Demo Shop.', (string) $product->description);
    }

    public function test_existing_descriptions_are_not_overwritten_without_overwrite(): void
    {
        $category = $this->createCategory('Jeans', 'jeans');
        $product = $this->createProduct($category, [
            'short_description' => 'Manual short',
            'description' => 'Manual description',
            'meta_title' => 'Manual title',
            'meta_description' => 'Manual meta',
        ]);

        $this->createTemplate($category, [
            'short_description_template' => 'Generated short',
            'description_template' => 'Generated description',
            'meta_title_template' => 'Generated title',
            'meta_description_template' => 'Generated meta',
        ]);

        app(ProductDescriptionTemplateService::class)->applyToProduct($product);

        $product->refresh();

        $this->assertSame('Manual short', $product->short_description);
        $this->assertSame('Manual description', $product->description);
        $this->assertSame('Manual title', $product->meta_title);
        $this->assertSame('Manual meta', $product->meta_description);
    }

    public function test_regeneration_updates_descriptions_after_variant_changes(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Jeans', 'jeans');
        $product = $this->createProduct($category, ['short_description' => 'Old price']);

        $this->createTemplate($category, [
            'short_description_template' => '{product_name} now at {selling_price}.',
            'description_template' => 'MRP {mrp}; selling price {selling_price}.',
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => 'JEANS-001',
            'name' => 'Default',
            'mrp' => 1999,
            'selling_price' => 1499,
            'stock_quantity' => 10,
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.description-seo.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'description']));

        $product->refresh();

        $this->assertSame('Oxford Shirt now at 1499.00.', $product->short_description);
        $this->assertSame('MRP 1999.00; selling price 1499.00.', $product->description);
    }

    public function test_inactive_templates_are_ignored(): void
    {
        $category = $this->createCategory('Shirts', 'shirts');
        $product = $this->createProduct($category);

        $this->createTemplate($category, [
            'short_description_template' => 'Inactive generated',
            'description_template' => 'Inactive generated',
            'status' => 'inactive',
        ]);

        app(ProductDescriptionTemplateService::class)->applyToProduct($product);

        $product->refresh();

        $this->assertNull($product->short_description);
        $this->assertNull($product->description);
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'description-admin@example.test',
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

    private function createCategory(string $name, string $slug, ?ProductCategory $parent = null): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createTemplate(ProductCategory $category, array $attributes = []): ProductDescriptionTemplate
    {
        return ProductDescriptionTemplate::query()->create([
            'product_category_id' => $category->getKey(),
            'name' => $attributes['name'] ?? 'Default Template',
            'short_description_template' => $attributes['short_description_template'] ?? '{product_name} {category}',
            'description_template' => $attributes['description_template'] ?? '{product_name} from {shop_name}.',
            'meta_title_template' => $attributes['meta_title_template'] ?? null,
            'meta_description_template' => $attributes['meta_description_template'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'sort_order' => $attributes['sort_order'] ?? 1,
        ]);
    }

    private function createBrand(string $name = 'Acme'): Brand
    {
        return Brand::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    private function createShop(User $user, ?ProductCategory $rootCategory = null): Shop
    {
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Demo Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);

        $shopTypeCategoryId = $rootCategory?->rootCategoryId() ?? DB::table('product_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'parent_id' => null,
            'name' => 'Apparel',
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $shopTypeCategoryId,
            'name' => 'Demo Shop',
            'slug' => 'demo-shop-'.Str::random(6),
            'address_line_1' => 'Nashik',
            'status' => 'active',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createProduct(ProductCategory $category, array $attributes = []): Product
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant User',
            'email' => 'merchant-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $shop = $this->createShop($user, $category);
        $brand = $this->createBrand();

        return Product::query()->create([
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $category->getKey(),
            'brand_id' => $brand->getKey(),
            'product_name' => $attributes['product_name'] ?? 'Oxford Shirt',
            'slug' => 'product-'.Str::random(8),
            'short_description' => $attributes['short_description'] ?? null,
            'description' => $attributes['description'] ?? null,
            'meta_title' => $attributes['meta_title'] ?? null,
            'meta_description' => $attributes['meta_description'] ?? null,
            'status' => 'draft',
        ]);
    }
}
