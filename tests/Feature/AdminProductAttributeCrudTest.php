<?php

namespace Tests\Feature;

use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductAttributeCrudTest extends TestCase
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

    public function test_admin_can_view_all_product_attribute_groups_without_laravel_pagination(): void
    {
        $admin = $this->createAdminUser();

        $this->createGroup([
            'name' => 'Group 01',
            'code' => 'group-01',
            'sort_order' => 1,
        ]);

        $this->createGroup([
            'name' => 'Group 60',
            'code' => 'group-60',
            'sort_order' => 60,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master.product-attributes.index'));

        $response->assertOk();
        $response->assertSee('Group 01');
        $response->assertSee('Group 60');
        $response->assertSee('product-attributes-table');
        $response->assertSee('DataTable');
        $response->assertDontSee('Showing 1 to', false);
    }

    public function test_admin_can_create_update_and_delete_product_attribute_group(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->post(route('admin.master.product-attributes.store'), [
                'name' => 'Care Instruction',
                'code' => 'Care Instruction',
                'description' => 'Product care labels',
                'selection_type' => 'single',
                'status' => 'active',
                'sort_order' => 30,
            ])
            ->assertRedirect(route('admin.master.product-attributes.index'))
            ->assertSessionHas('success', 'Product attribute group created successfully.');

        $group = ProductAttributeGroup::query()->where('code', 'care-instruction')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.master.product-attributes.update', $group), [
                'name' => 'Care',
                'code' => 'care',
                'description' => 'Updated care labels',
                'selection_type' => 'multiple',
                'status' => 'inactive',
                'sort_order' => 31,
            ])
            ->assertRedirect(route('admin.master.product-attributes.edit', $group))
            ->assertSessionHas('success', 'Product attribute group updated successfully.');

        $this->assertDatabaseHas('product_attribute_groups', [
            'id' => $group->getKey(),
            'name' => 'Care',
            'code' => 'care',
            'selection_type' => 'multiple',
            'status' => 'inactive',
            'sort_order' => 31,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.product-attributes.destroy', $group->fresh()))
            ->assertRedirect(route('admin.master.product-attributes.index'))
            ->assertSessionHas('success', 'Product attribute group deleted successfully.');

        $this->assertDatabaseMissing('product_attribute_groups', [
            'id' => $group->getKey(),
        ]);
    }

    public function test_admin_can_manage_product_attribute_group_values(): void
    {
        $admin = $this->createAdminUser();
        $group = $this->createGroup([
            'name' => 'Length',
            'code' => 'length',
        ]);

        $this->createValue([
            'product_attribute_group_id' => $group->getKey(),
            'name' => 'Short',
            'code' => 'short',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.master.product-attributes.values.index', $group))
            ->assertOk()
            ->assertSee('Short')
            ->assertSee('product-attribute-group-values-table')
            ->assertSee('DataTable');

        $this->actingAs($admin)
            ->post(route('admin.master.product-attributes.values.store', $group), [
                'name' => 'Long',
                'code' => 'Long',
                'description' => 'Long length',
                'status' => 'active',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.master.product-attributes.values.index', $group))
            ->assertSessionHas('success', 'Product attribute group value created successfully.');

        $value = ProductAttributeGroupValue::query()
            ->where('product_attribute_group_id', $group->getKey())
            ->where('code', 'long')
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.master.product-attributes.values.update', [$group, $value]), [
                'name' => 'Extra Long',
                'code' => 'extra-long',
                'description' => null,
                'status' => 'inactive',
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.master.product-attributes.values.edit', [$group, $value]))
            ->assertSessionHas('success', 'Product attribute group value updated successfully.');

        $this->assertDatabaseHas('product_attribute_group_values', [
            'id' => $value->getKey(),
            'name' => 'Extra Long',
            'code' => 'extra-long',
            'status' => 'inactive',
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.product-attributes.values.destroy', [$group, $value->fresh()]))
            ->assertRedirect(route('admin.master.product-attributes.values.index', $group))
            ->assertSessionHas('success', 'Product attribute group value deleted successfully.');

        $this->assertDatabaseMissing('product_attribute_group_values', [
            'id' => $value->getKey(),
        ]);
    }

    public function test_attribute_group_value_codes_are_unique_within_group(): void
    {
        $admin = $this->createAdminUser();
        $group = $this->createGroup([
            'name' => 'Style',
            'code' => 'style',
        ]);

        $this->createValue([
            'product_attribute_group_id' => $group->getKey(),
            'name' => 'Modern',
            'code' => 'modern',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.master.product-attributes.values.create', $group))
            ->post(route('admin.master.product-attributes.values.store', $group), [
                'name' => 'Modern Duplicate',
                'code' => 'modern',
                'status' => 'active',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.master.product-attributes.values.create', $group))
            ->assertSessionHasErrors('code');
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin-crud@example.test',
            'mobile' => '9111111111',
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

    /**
     * @param array<string, mixed> $attributes
     */
    private function createGroup(array $attributes = []): ProductAttributeGroup
    {
        return ProductAttributeGroup::query()->create([
            'name' => $attributes['name'] ?? 'Test Attribute',
            'code' => $attributes['code'] ?? 'test-attribute',
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createValue(array $attributes = []): ProductAttributeGroupValue
    {
        return ProductAttributeGroupValue::query()->create([
            'product_attribute_group_id' => $attributes['product_attribute_group_id'],
            'name' => $attributes['name'] ?? 'Test Value',
            'code' => $attributes['code'] ?? 'test-value',
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);
    }
}
