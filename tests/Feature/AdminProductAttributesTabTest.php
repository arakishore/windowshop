<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductAttributesTabTest extends TestCase
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

    public function test_attributes_tab_loads_category_mapped_attributes(): void
    {
        [$admin, $product, $color, $material] = $this->productWithAttributeMappings();

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertOk()
            ->assertSee('Color')
            ->assertSee('Red')
            ->assertSee('Blue')
            ->assertSee('Material')
            ->assertSee('Cotton')
            ->assertSee('Linen')
            ->assertSee('type="checkbox"', false)
            ->assertSee('type="radio"', false)
            ->assertSee('Variant Attributes')
            ->assertSee('Other Attributes')
            ->assertSee('Variant')
            ->assertSee('Save Attributes')
            ->assertDontSee($color->code)
            ->assertDontSee($material->code);
    }

    public function test_admin_can_save_product_attributes(): void
    {
        [$admin, $product, $color, $material] = $this->productWithAttributeMappings();
        $red = $color->values->firstWhere('name', 'Red');
        $blue = $color->values->firstWhere('name', 'Blue');
        $cotton = $material->values->firstWhere('name', 'Cotton');

        $this->actingAs($admin)
            ->put(route('admin.products.attributes.update', $product), [
                'attributes' => [
                    $color->id => [$red->id, $blue->id],
                    $material->id => $cotton->id,
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertSessionHas('success', 'Product attributes updated successfully.');

        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $blue->getKey(),
        ]);
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_id' => $material->getKey(),
            'product_attribute_group_value_id' => $cotton->getKey(),
        ]);
    }

    public function test_existing_product_attributes_are_preselected_and_editable(): void
    {
        [$admin, $product, $color, $material] = $this->productWithAttributeMappings();
        $red = $color->values->firstWhere('name', 'Red');
        $blue = $color->values->firstWhere('name', 'Blue');
        $cotton = $material->values->firstWhere('name', 'Cotton');
        $linen = $material->values->firstWhere('name', 'Linen');

        $product->attributes()->create([
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $product->attributes()->create([
            'product_attribute_group_id' => $material->getKey(),
            'product_attribute_group_value_id' => $cotton->getKey(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertOk()
            ->assertSee('checked', false)
            ->assertSee('Red')
            ->assertSee('Cotton');

        $this->actingAs($admin)
            ->put(route('admin.products.attributes.update', $product), [
                'attributes' => [
                    $color->id => [$blue->id],
                    $material->id => $linen->id,
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']));

        $this->assertDatabaseMissing('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_value_id' => $red->getKey(),
        ]);
        $this->assertDatabaseMissing('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_value_id' => $cotton->getKey(),
        ]);
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_value_id' => $blue->getKey(),
        ]);
        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->getKey(),
            'product_attribute_group_value_id' => $linen->getKey(),
        ]);
    }

    public function test_required_attribute_groups_must_have_selection(): void
    {
        [$admin, $product, $color, $material] = $this->productWithAttributeMappings();
        $cotton = $material->values->firstWhere('name', 'Cotton');

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->put(route('admin.products.attributes.update', $product), [
                'attributes' => [
                    $material->id => $cotton->id,
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertSessionHasErrors("attributes.{$color->id}");
    }

    public function test_single_selection_attribute_rejects_multiple_values(): void
    {
        [$admin, $product, $color, $material] = $this->productWithAttributeMappings();
        $red = $color->values->firstWhere('name', 'Red');
        $cotton = $material->values->firstWhere('name', 'Cotton');
        $linen = $material->values->firstWhere('name', 'Linen');

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->put(route('admin.products.attributes.update', $product), [
                'attributes' => [
                    $color->id => [$red->id],
                    $material->id => [$cotton->id, $linen->id],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertSessionHasErrors("attributes.{$material->id}");
    }

    /**
     * @return array{0: User, 1: Product, 2: ProductAttributeGroup, 3: ProductAttributeGroup}
     */
    private function productWithAttributeMappings(): array
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel', null);
        $category = $this->createCategory('T-Shirts', $root);
        $product = $this->createProduct($admin, $root, $category);
        $color = $this->createGroup('Color', 'multiple', ['Red', 'Blue']);
        $material = $this->createGroup('Material', 'single', ['Cotton', 'Linen']);

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $color->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 1,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $material->getKey(),
            'is_required' => false,
            'is_variant' => false,
            'sort_order' => 2,
        ]);

        return [$admin, $product, $color->load('values'), $material->load('values')];
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'attribute-admin@example.test',
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

    private function createCategory(string $name, ?ProductCategory $parent): ProductCategory
    {
        return ProductCategory::query()->create([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
        ]);
    }

    private function createProduct(User $user, ProductCategory $root, ProductCategory $category): Product
    {
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Demo Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Demo Shop',
            'slug' => 'demo-shop-'.Str::random(6),
            'address_line_1' => 'Nashik',
            'status' => 'active',
        ]);

        return Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Cotton T-Shirt',
            'slug' => 'cotton-t-shirt-'.Str::random(6),
            'status' => 'draft',
        ]);
    }

    /**
     * @param array<int, string> $values
     */
    private function createGroup(string $name, string $selectionType, array $values): ProductAttributeGroup
    {
        $group = ProductAttributeGroup::query()->create([
            'name' => $name,
            'code' => Str::slug($name),
            'selection_type' => $selectionType,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        foreach ($values as $index => $value) {
            ProductAttributeGroupValue::query()->create([
                'product_attribute_group_id' => $group->getKey(),
                'name' => $value,
                'code' => Str::slug($value),
                'status' => 'active',
                'sort_order' => $index + 1,
            ]);
        }

        return $group;
    }
}
