<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\TaxRateComponent;
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

    public function test_merchant_create_product_shows_only_active_shop_categories(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();
        $shopOtherCategory = ProductCategory::query()->create([
            'parent_id' => $shop->root_product_category_id,
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherRoot = ProductCategory::query()->create([
            'name' => 'Beauty '.Str::random(4),
            'slug' => 'beauty-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherCategory = ProductCategory::query()->create([
            'parent_id' => $otherRoot->getKey(),
            'name' => 'Lipstick '.Str::random(4),
            'slug' => 'lipstick-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.create'))
            ->assertOk()
            ->assertSee('value="'.$shop->getKey().'"', false)
            ->assertSee('data-root-category-id="'.$shop->root_product_category_id.'"', false)
            ->assertSee($category->name)
            ->assertSee($shopOtherCategory->name)
            ->assertDontSee($otherCategory->name);
    }

    public function test_merchant_can_create_product_under_active_shop_other_category(): void
    {
        [$user, $shop] = $this->merchantFixture();
        $shopOtherCategory = ProductCategory::query()->create([
            'parent_id' => $shop->root_product_category_id,
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $shopOtherCategory->getKey(),
                'brand_id' => null,
                'product_name' => 'Merchant Other Product',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $shopOtherCategory->getKey(),
            'product_name' => 'Merchant Other Product',
        ]);
    }

    public function test_parent_categories_can_have_selectable_other_children(): void
    {
        [$user, $shop] = $this->merchantFixture();
        $men = ProductCategory::query()->create([
            'parent_id' => $shop->root_product_category_id,
            'name' => 'Men',
            'slug' => 'men-'.Str::random(6),
            'status' => 'active',
        ]);
        $shirt = ProductCategory::query()->create([
            'parent_id' => $men->getKey(),
            'name' => 'Shirts',
            'slug' => 'shirts-'.Str::random(6),
            'status' => 'active',
        ]);
        $menOther = ProductCategory::query()->create([
            'parent_id' => $men->getKey(),
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'status' => 'active',
            'sort_order' => 99,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.products.create'))
            ->assertOk()
            ->assertSee($shirt->name)
            ->assertSee('Men &gt; Other', false);

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Men &gt; Other'),
            strpos($content, 'Men &gt; Shirts'),
        );

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.products.store'), [
                'shop_id' => $shop->getKey(),
                'product_category_id' => $menOther->getKey(),
                'brand_id' => null,
                'product_name' => 'Merchant Men Other Product',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->getKey(),
            'product_category_id' => $menOther->getKey(),
            'product_name' => 'Merchant Men Other Product',
        ]);
    }

    public function test_merchant_can_view_read_only_categories_and_attributes_for_active_shop_type(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();
        $taxClass = $this->createTaxClassWithRate('GST5', 'GST 5%', '5.0000', '2.5000');
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();
        $shopOtherCategory = ProductCategory::query()->create([
            'parent_id' => $shop->root_product_category_id,
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherRoot = ProductCategory::query()->create([
            'name' => 'Beauty '.Str::random(4),
            'slug' => 'beauty-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherCategory = ProductCategory::query()->create([
            'parent_id' => $otherRoot->getKey(),
            'name' => 'Lipstick '.Str::random(4),
            'slug' => 'lipstick-'.Str::random(6),
            'status' => 'active',
        ]);
        $size = ProductAttributeGroup::query()->create([
            'name' => 'Size',
            'code' => 'size',
            'selection_type' => 'single',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $size->getKey(),
            'name' => 'Large',
            'code' => 'large',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $shop->root_product_category_id,
            'product_attribute_group_id' => $size->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => false,
            'sort_order' => 1,
        ]);
        $shade = ProductAttributeGroup::query()->create([
            'name' => 'Shade',
            'code' => 'shade',
            'selection_type' => 'multiple',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $otherRoot->getKey(),
            'product_attribute_group_id' => $shade->getKey(),
            'is_required' => false,
            'is_variant' => false,
            'is_image_attribute' => false,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.catalogue-masters.index'))
            ->assertOk()
            ->assertSee('Categories &amp; Attributes', false)
            ->assertSee($category->name)
            ->assertSee($shopOtherCategory->name)
            ->assertSee('Default Tax Class')
            ->assertSee('GST5 / GST 5% - 5.0000% (CGST 2.5000% + SGST 2.5000%)')
            ->assertSee('No default')
            ->assertSee('Can Select in Product')
            ->assertSee('Size')
            ->assertSee('Large')
            ->assertSee('Required')
            ->assertSee('Variant')
            ->assertDontSee($otherCategory->name)
            ->assertDontSee('<div class="fw-semibold">Shade</div>', false);
    }

    public function test_merchant_can_request_missing_catalogue_master_and_admin_can_review(): void
    {
        [$user, $shop, $category] = $this->merchantFixture();
        $admin = $this->createAdminUser();

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->post(route('merchant.catalogue-masters.requests.store'), [
                'request_type' => 'category',
                'suggested_name' => 'Formal Shirts',
                'parent_product_category_id' => $category->getKey(),
                'example_product_name' => 'Oxford White Shirt',
                'description' => 'Needed for office wear products.',
            ])
            ->assertRedirect(route('merchant.catalogue-masters.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('catalogue_master_requests', [
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $shop->root_product_category_id,
            'request_type' => 'category',
            'suggested_name' => 'Formal Shirts',
            'parent_product_category_id' => $category->getKey(),
            'status' => 'pending',
        ]);

        $requestModel = \App\Models\CatalogueMasterRequest::query()
            ->where('suggested_name', 'Formal Shirts')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.master.catalogue-requests.index'))
            ->assertOk()
            ->assertSee('Formal Shirts')
            ->assertSee('Oxford White Shirt')
            ->assertSee('Pending');

        $this->actingAs($admin)
            ->put(route('admin.master.catalogue-requests.update', $requestModel), [
                'status' => 'needs_info',
                'admin_note' => 'Please share two sample products.',
            ])
            ->assertRedirect(route('admin.master.catalogue-requests.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('catalogue_master_requests', [
            'id' => $requestModel->getKey(),
            'status' => 'needs_info',
            'admin_note' => 'Please share two sample products.',
            'reviewed_by' => $admin->getKey(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.master.catalogue-requests.update', $requestModel->refresh()), [
                'status' => 'approved',
                'admin_note' => 'Created under Men.',
            ])
            ->assertRedirect(route('admin.master.catalogue-requests.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_categories', [
            'parent_id' => $category->getKey(),
            'name' => 'Formal Shirts',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->get(route('merchant.catalogue-masters.index'))
            ->assertOk()
            ->assertSee('Formal Shirts')
            ->assertSee('Created under Men.')
            ->assertSee('Approved');
    }

    public function test_merchant_catalogue_request_parent_must_belong_to_active_shop_type(): void
    {
        [$user, $shop] = $this->merchantFixture();
        $otherRoot = ProductCategory::query()->create([
            'name' => 'Beauty '.Str::random(4),
            'slug' => 'beauty-'.Str::random(6),
            'status' => 'active',
        ]);
        $otherCategory = ProductCategory::query()->create([
            'parent_id' => $otherRoot->getKey(),
            'name' => 'Lipstick '.Str::random(4),
            'slug' => 'lipstick-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['active_shop_id' => $shop->getKey()])
            ->from(route('merchant.catalogue-masters.index'))
            ->post(route('merchant.catalogue-masters.requests.store'), [
                'request_type' => 'category',
                'suggested_name' => 'Office Lipstick',
                'parent_product_category_id' => $otherCategory->getKey(),
            ])
            ->assertRedirect(route('merchant.catalogue-masters.index'))
            ->assertSessionHasErrors('parent_product_category_id');
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

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin-'.Str::random(6).'@example.test',
            'mobile' => '91000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleId = DB::table('auth_roles')->where('slug', 'admin')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin',
                'slug' => 'admin',
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

    private function createTaxClassWithRate(string $code, string $name, string $totalRate, string $componentRate): TaxClass
    {
        $countryId = DB::table('loc_countries')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tax Country '.Str::random(6),
            'iso3' => strtoupper(Str::random(3)),
            'iso2' => strtoupper(Str::random(2)),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taxClass = TaxClass::query()->create([
            'country_id' => $countryId,
            'code' => $code,
            'name' => $name,
            'status' => TaxClass::STATUS_ACTIVE,
        ]);

        $taxRate = $taxClass->rates()->create([
            'name' => $name,
            'total_rate' => $totalRate,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $taxRate->components()->create([
            'code' => 'CGST',
            'name' => 'CGST',
            'rate' => $componentRate,
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
            'priority' => 1,
        ]);
        $taxRate->components()->create([
            'code' => 'SGST',
            'name' => 'SGST',
            'rate' => $componentRate,
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE,
            'priority' => 2,
        ]);

        return $taxClass;
    }
}
