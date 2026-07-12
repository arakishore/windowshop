<?php

namespace Tests\Feature;

use App\Models\ProductDescriptionTemplate;
use App\Models\ShopCategory;
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
                'shop_category_id' => $category->getKey(),
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
                'shop_category_id' => $category->getKey(),
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

    private function createCategory(string $name, string $slug): ShopCategory
    {
        return ShopCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createTemplate(ShopCategory $category, array $attributes = []): ProductDescriptionTemplate
    {
        return ProductDescriptionTemplate::query()->create([
            'shop_category_id' => $category->getKey(),
            'name' => $attributes['name'] ?? 'Default Template',
            'short_description_template' => $attributes['short_description_template'] ?? '{product_name} {category}',
            'description_template' => $attributes['description_template'] ?? '{product_name} from {shop_name}.',
            'status' => $attributes['status'] ?? 'active',
            'sort_order' => $attributes['sort_order'] ?? 1,
        ]);
    }
}
