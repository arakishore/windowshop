<?php

namespace Tests\Feature;

use App\Models\ProductAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductCategoryAttributeMappingTest extends TestCase
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

    public function test_admin_can_save_category_mapping_with_variant_enabled(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel');
        $finish = $this->createAttributeGroup('Finish');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $category), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '1',
                        'is_variant' => '1',
                        'sort_order' => '10',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $category))
            ->assertSessionHas('success', 'Category attribute mappings updated successfully.');

        $this->assertDatabaseHas('product_category_attribute_groups', [
            'root_product_category_id' => $category->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_update_variant_mapping_from_true_to_false(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel');
        $finish = $this->createAttributeGroup('Finish');

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $category->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $category), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '1',
                        'is_variant' => '0',
                        'sort_order' => '20',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $category));

        $this->assertDatabaseHas('product_category_attribute_groups', [
            'root_product_category_id' => $category->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => false,
            'sort_order' => 20,
        ]);
    }

    public function test_unchecked_variant_checkbox_saves_as_false(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Furniture');
        $finish = $this->createAttributeGroup('Finish');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $category), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '0',
                        'is_variant' => '0',
                        'sort_order' => '30',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $category));

        $this->assertDatabaseHas('product_category_attribute_groups', [
            'root_product_category_id' => $category->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => false,
            'is_variant' => false,
            'sort_order' => 30,
        ]);
    }

    public function test_same_attribute_group_can_be_variant_for_one_category_and_not_another(): void
    {
        $admin = $this->createAdminUser();
        $apparel = $this->createCategory('Apparel');
        $furniture = $this->createCategory('Furniture');
        $finish = $this->createAttributeGroup('Finish');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $apparel), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '0',
                        'is_variant' => '1',
                        'sort_order' => '10',
                    ],
                ],
            ]);

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $furniture), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '0',
                        'is_variant' => '0',
                        'sort_order' => '10',
                    ],
                ],
            ]);

        $this->assertTrue(ProductCategoryAttributeGroup::query()
            ->where('root_product_category_id', $apparel->getKey())
            ->where('product_attribute_group_id', $finish->getKey())
            ->value('is_variant'));
        $this->assertFalse((bool) ProductCategoryAttributeGroup::query()
            ->where('root_product_category_id', $furniture->getKey())
            ->where('product_attribute_group_id', $finish->getKey())
            ->value('is_variant'));
    }

    public function test_existing_mapping_without_variant_checked_continues_to_save(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel');
        $occasion = $this->createAttributeGroup('Occasion', 'multiple');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $category), [
                'mappings' => [
                    $occasion->id => [
                        'product_attribute_group_id' => $occasion->id,
                        'enabled' => '1',
                        'is_required' => '1',
                        'sort_order' => '40',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $category));

        $this->assertDatabaseHas('product_category_attribute_groups', [
            'root_product_category_id' => $category->getKey(),
            'product_attribute_group_id' => $occasion->getKey(),
            'is_required' => true,
            'is_variant' => false,
            'sort_order' => 40,
        ]);
    }

    public function test_mapping_opened_from_child_category_is_saved_against_root_category(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $child = $this->createCategory('Kurtas', $root);
        $finish = $this->createAttributeGroup('Finish');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.attribute-groups.update', $child), [
                'mappings' => [
                    $finish->id => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '1',
                        'is_variant' => '1',
                        'sort_order' => '15',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $root));

        $this->assertDatabaseHas('product_category_attribute_groups', [
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 15,
        ]);
    }

    public function test_duplicate_group_mapping_is_rejected_before_unique_constraint(): void
    {
        $admin = $this->createAdminUser();
        $category = $this->createCategory('Apparel');
        $finish = $this->createAttributeGroup('Finish');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.attribute-groups.edit', $category))
            ->put(route('admin.master.product-categories.attribute-groups.update', $category), [
                'mappings' => [
                    'first' => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '0',
                        'is_variant' => '1',
                        'sort_order' => '10',
                    ],
                    'second' => [
                        'product_attribute_group_id' => $finish->id,
                        'enabled' => '1',
                        'is_required' => '0',
                        'is_variant' => '0',
                        'sort_order' => '20',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.master.product-categories.attribute-groups.edit', $category))
            ->assertSessionHasErrors('mappings');
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'mapping-admin@example.test',
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
        ]);
    }

    private function createAttributeGroup(string $name, string $selectionType = 'single'): ProductAttributeGroup
    {
        return ProductAttributeGroup::query()->create([
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::random(4),
            'selection_type' => $selectionType,
            'status' => 'active',
            'sort_order' => 10,
        ]);
    }
}
