<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductCategoryHierarchyTest extends TestCase
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

    public function test_admin_can_create_child_product_category(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Fashion', 'fashion');

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women',
                'description' => 'Women fashion',
                'sort_order' => 2,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('success', 'Product category created successfully.');

        $category = ProductCategory::query()->where('name', 'Women')->where('parent_id', $parent->getKey())->firstOrFail();

        $this->assertDatabaseHas('product_categories', [
            'parent_id' => $parent->getKey(),
            'name' => 'Women',
            'slug' => 'women-'.$category->getKey(),
        ]);
    }

    public function test_category_name_is_unique_within_same_parent(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Fashion', 'fashion');
        $otherParent = $this->createCategory('Lifestyle', 'lifestyle');
        $this->createCategory('Women', 'women', $parent);

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors(['name' => 'A category with this name already exists under the selected parent.']);

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $otherParent->getKey(),
                'name' => 'Women',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'));

        $this->assertDatabaseHas('product_categories', [
            'parent_id' => $otherParent->getKey(),
            'name' => 'Women',
        ]);
    }

    public function test_category_cannot_be_moved_under_its_child(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Fashion', 'fashion');
        $child = $this->createCategory('Women', 'women', $parent);

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.edit', $parent))
            ->put(route('admin.master.product-categories.update', $parent), [
                'parent_id' => $child->getKey(),
                'name' => 'Fashion',
                'description' => null,
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $parent))
            ->assertSessionHasErrors('parent_id');
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Fashion', 'fashion');
        $this->createCategory('Women', 'women', $parent);

        $this->actingAs($admin)
            ->delete(route('admin.master.product-categories.destroy', $parent))
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('error', 'This category cannot be deleted because it has child categories. Move or delete the child categories first.');

        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_admin_can_view_category_details_with_children(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Fashion', 'fashion');
        $this->createCategory('Women', 'women', $parent);

        $this->actingAs($admin)
            ->get(route('admin.master.product-categories.show', $parent))
            ->assertOk()
            ->assertSee('Complete Category Path')
            ->assertSee('Fashion')
            ->assertSee('Women');
    }

    public function test_unfiltered_category_list_is_flattened_as_tree(): void
    {
        $admin = $this->createAdminUser();
        $fashion = $this->createCategory('Fashion', 'fashion');
        $electronics = $this->createCategory('Electronics', 'electronics');
        $women = $this->createCategory('Women', 'women', $fashion);
        $this->createCategory('Dresses', 'dresses', $women);
        $this->createCategory('Mobiles', 'mobiles', $electronics);

        $response = $this->actingAs($admin)
            ->get(route('admin.master.product-categories.index'));

        $response->assertOk();
        $response->assertSee('Showing 5 entries');
        $response->assertDontSee('Showing 1 to', false);
        $response->assertSee(route('admin.master.product-categories.attribute-groups.edit', $fashion), false);
        $response->assertDontSee(route('admin.master.product-categories.attribute-groups.edit', $women), false);
        $response->assertSee('Manage Attribute Mapping');
        $response->assertSee('ph-sliders-horizontal');
        $response->assertSeeInOrder([
            'Electronics',
            'Mobiles',
            'Fashion',
            'Women',
            'Dresses',
        ]);
    }

    public function test_category_depth_is_limited_to_three_levels(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel', 'apparel');
        $levelTwo = $this->createCategory('Men', 'men', $root);
        $levelThree = $this->createCategory('T-Shirts', 't-shirts', $levelTwo);

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $levelThree->getKey(),
                'name' => 'Round Neck',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('parent_id');
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'category-admin@example.test',
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
}
