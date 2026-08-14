<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\TaxClass;
use App\Models\TaxRateComponent;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function test_admin_can_create_category_with_seo_fields(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Apparel', 'apparel');

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women',
                'short_code' => 'wj',
                'description' => 'Women fashion',
                'meta_title' => "Women's Fashion & Clothing | WindowShop",
                'meta_description' => "Explore women's fashion and clothing from local shops.",
                'product_disclaimer' => 'Colors and fit may vary slightly.',
                'sort_order' => 2,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_categories', [
            'parent_id' => $parent->getKey(),
            'name' => 'Women',
            'short_code' => 'WJ',
            'meta_title' => "Women's Fashion & Clothing | WindowShop",
            'meta_description' => "Explore women's fashion and clothing from local shops.",
            'product_disclaimer' => 'Colors and fit may vary slightly.',
        ]);
    }

    public function test_admin_can_update_category_seo_fields(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Apparel', 'apparel');
        $category = $this->createCategory('T-Shirts', 't-shirts', $parent);

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $parent->getKey(),
                'name' => 'T-Shirts',
                'short_code' => 'mtj',
                'description' => null,
                'meta_title' => "Women's T-Shirts Online | WindowShop",
                'meta_description' => "Explore women's T-shirts from local shops.",
                'product_disclaimer' => 'Check size and fabric before purchase.',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertSame("Women's T-Shirts Online | WindowShop", $category->meta_title);
        $this->assertSame('MTJ', $category->short_code);
        $this->assertSame("Explore women's T-shirts from local shops.", $category->meta_description);
        $this->assertSame('Check size and fabric before purchase.', $category->product_disclaimer);
    }

    public function test_category_seo_fields_are_optional(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => null,
                'name' => 'Footwear',
                'description' => null,
                'meta_title' => null,
                'meta_description' => null,
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHasNoErrors();

        $category = ProductCategory::query()->where('name', 'Footwear')->firstOrFail();

        $this->assertNull($category->meta_title);
        $this->assertNull($category->meta_description);
    }

    public function test_category_seo_fields_enforce_max_lengths(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Apparel', 'apparel');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women',
                'meta_title' => str_repeat('a', 256),
                'meta_description' => str_repeat('b', 501),
                'product_disclaimer' => str_repeat('c', 1001),
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors(['meta_title', 'meta_description', 'product_disclaimer']);
    }

    public function test_category_short_code_must_be_alphanumeric_and_max_twenty_characters(): void
    {
        $admin = $this->createAdminUser();
        $parent = $this->createCategory('Apparel', 'apparel');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women Jeans',
                'short_code' => 'women-jeans',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('short_code');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Women Shirts',
                'short_code' => str_repeat('A', 21),
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('short_code');
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

    public function test_selectable_leaf_category_can_store_update_and_clear_default_tax_class(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClassWithRate('GST18', 'GST 18%', '18.0000', '9.0000');
        $otherTaxClass = $this->createTaxClass('GST5', 'GST 5%');
        $parent = $this->createCategory('Fashion', 'fashion');

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $taxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('success', 'Product category created successfully.');

        $category = ProductCategory::query()->where('name', 'T-Shirts')->firstOrFail();
        $this->assertSame($taxClass->getKey(), $category->default_tax_class_id);
        $this->assertTrue($category->defaultTaxClass->is($taxClass));

        $this->actingAs($admin)
            ->get(route('admin.master.product-categories.index'))
            ->assertOk()
            ->assertSee('Default Tax Class')
            ->assertSee('GST18 / GST 18% - 18.0000% (CGST 9.0000% + SGST 9.0000%)');

        $this->actingAs($admin)
            ->get(route('admin.master.product-categories.show', $category))
            ->assertOk()
            ->assertSee('Default Tax Class')
            ->assertSee('GST18 / GST 18%');

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $parent->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $otherTaxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category));

        $this->assertSame($otherTaxClass->getKey(), $category->fresh()->default_tax_class_id);

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $parent->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => null,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category));

        $this->assertNull($category->fresh()->default_tax_class_id);
    }

    public function test_inactive_and_soft_deleted_tax_classes_are_rejected_for_category_default(): void
    {
        $admin = $this->createAdminUser();
        $inactiveTaxClass = $this->createTaxClass('GST18', 'GST 18%', 'inactive');
        $deletedTaxClass = $this->createTaxClass('GST5', 'GST 5%');
        $deletedTaxClass->delete();
        $parent = $this->createCategory('Fashion', 'fashion');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $inactiveTaxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('default_tax_class_id');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $parent->getKey(),
                'name' => 'Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $deletedTaxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('default_tax_class_id');
    }

    public function test_grouping_categories_cannot_store_default_tax_class_and_clear_when_becoming_grouping(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.create'))
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => null,
                'name' => 'Root With Tax',
                'sort_order' => 1,
                'default_tax_class_id' => $taxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.create'))
            ->assertSessionHasErrors('default_tax_class_id');

        $leaf = $this->createCategory('T-Shirts', 't-shirts', $root);
        $leaf->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.store'), [
                'parent_id' => $leaf->getKey(),
                'name' => 'Round Neck',
                'sort_order' => 1,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.index'));

        $this->assertNull($leaf->fresh()->default_tax_class_id);

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.edit', $root))
            ->put(route('admin.master.product-categories.update', $root), [
                'parent_id' => null,
                'name' => 'Fashion',
                'sort_order' => 1,
                'default_tax_class_id' => $taxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $root))
            ->assertSessionHasErrors('default_tax_class_id');
    }

    public function test_referenced_tax_class_cannot_be_force_deleted(): void
    {
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();

        $this->expectException(QueryException::class);

        $taxClass->forceDelete();
    }

    public function test_edit_page_displays_current_inactive_tax_class_assignment(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%', TaxClass::STATUS_INACTIVE);
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();

        $this->actingAs($admin)
            ->get(route('admin.master.product-categories.edit', $category))
            ->assertOk()
            ->assertSee('GST18 / GST 18% - Inactive (current assignment)')
            ->assertSee('This category currently uses a tax class that is inactive.');
    }

    public function test_edit_page_displays_current_soft_deleted_tax_class_assignment(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();
        $taxClass->delete();

        $this->actingAs($admin)
            ->get(route('admin.master.product-categories.edit', $category))
            ->assertOk()
            ->assertSee('GST18 / GST 18% - Deleted (current assignment)')
            ->assertSee('This category currently uses a tax class that is deleted.');
    }

    public function test_unrelated_update_preserves_current_inactive_tax_class_assignment(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%', TaxClass::STATUS_INACTIVE);
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $root->getKey(),
                'name' => 'T-Shirts Updated',
                'sort_order' => 2,
                'default_tax_class_id' => $taxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('T-Shirts Updated', $category->name);
        $this->assertSame($taxClass->getKey(), $category->default_tax_class_id);
    }

    public function test_unrelated_update_preserves_current_soft_deleted_tax_class_assignment(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();
        $taxClass->delete();

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $root->getKey(),
                'name' => 'T-Shirts Updated',
                'sort_order' => 2,
                'default_tax_class_id' => $taxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertSame('T-Shirts Updated', $category->name);
        $this->assertSame($taxClass->getKey(), $category->default_tax_class_id);
    }

    public function test_changing_from_active_tax_class_to_inactive_tax_class_is_rejected(): void
    {
        $admin = $this->createAdminUser();
        $activeTaxClass = $this->createTaxClass('GST18', 'GST 18%');
        $inactiveTaxClass = $this->createTaxClass('GST5', 'GST 5%', TaxClass::STATUS_INACTIVE);
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $activeTaxClass->getKey()])->save();

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.edit', $category))
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $root->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $inactiveTaxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasErrors('default_tax_class_id');

        $this->assertSame($activeTaxClass->getKey(), $category->fresh()->default_tax_class_id);
    }

    public function test_changing_from_unavailable_current_tax_class_to_active_tax_class_succeeds(): void
    {
        $admin = $this->createAdminUser();
        $inactiveTaxClass = $this->createTaxClass('GST18', 'GST 18%', TaxClass::STATUS_INACTIVE);
        $activeTaxClass = $this->createTaxClass('GST5', 'GST 5%');
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $inactiveTaxClass->getKey()])->save();

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $root->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => $activeTaxClass->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasNoErrors();

        $this->assertSame($activeTaxClass->getKey(), $category->fresh()->default_tax_class_id);
    }

    public function test_explicitly_clearing_unavailable_current_tax_class_succeeds(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%', TaxClass::STATUS_INACTIVE);
        $root = $this->createCategory('Fashion', 'fashion');
        $category = $this->createCategory('T-Shirts', 't-shirts', $root);
        $category->forceFill(['default_tax_class_id' => $taxClass->getKey()])->save();

        $this->actingAs($admin)
            ->put(route('admin.master.product-categories.update', $category), [
                'parent_id' => $root->getKey(),
                'name' => 'T-Shirts',
                'sort_order' => 1,
                'default_tax_class_id' => null,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.product-categories.edit', $category))
            ->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->default_tax_class_id);
    }

    public function test_admin_can_bulk_assign_and_clear_default_tax_class_for_leaf_categories(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');
        $shirts = $this->createCategory('Shirts', 'shirts', $root);
        $dresses = $this->createCategory('Dresses', 'dresses', $root);

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.bulk-tax-class'), [
                'bulk_tax_action' => 'assign',
                'tax_class_id' => $taxClass->getKey(),
                'category_ids' => [$shirts->getKey(), $dresses->getKey()],
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('success', 'Default tax class assigned to 2 leaf category/categories.');

        $this->assertSame($taxClass->getKey(), $shirts->fresh()->default_tax_class_id);
        $this->assertSame($taxClass->getKey(), $dresses->fresh()->default_tax_class_id);

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.bulk-tax-class'), [
                'bulk_tax_action' => 'clear',
                'category_ids' => [$shirts->getKey(), $dresses->getKey()],
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('success', 'Default tax class cleared from 2 leaf category/categories.');

        $this->assertNull($shirts->fresh()->default_tax_class_id);
        $this->assertNull($dresses->fresh()->default_tax_class_id);
    }

    public function test_bulk_tax_class_skips_grouping_categories(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');
        $shirts = $this->createCategory('Shirts', 'shirts', $root);

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.bulk-tax-class'), [
                'bulk_tax_action' => 'assign',
                'tax_class_id' => $taxClass->getKey(),
                'category_ids' => [$root->getKey(), $shirts->getKey()],
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('success', 'Default tax class assigned to 1 leaf category/categories. 1 grouping category/categories were skipped.');

        $this->assertNull($root->fresh()->default_tax_class_id);
        $this->assertSame($taxClass->getKey(), $shirts->fresh()->default_tax_class_id);
    }

    public function test_bulk_tax_class_requires_leaf_category_selection(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass('GST18', 'GST 18%');
        $root = $this->createCategory('Fashion', 'fashion');

        $this->actingAs($admin)
            ->post(route('admin.master.product-categories.bulk-tax-class'), [
                'bulk_tax_action' => 'assign',
                'tax_class_id' => $taxClass->getKey(),
                'category_ids' => [$root->getKey()],
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHas('error', 'Select at least one leaf category. Tax classes can only be assigned to selectable leaf categories.');

        $this->assertNull($root->fresh()->default_tax_class_id);
    }

    public function test_bulk_tax_class_rejects_inactive_tax_class(): void
    {
        $admin = $this->createAdminUser();
        $inactiveTaxClass = $this->createTaxClass('GST18', 'GST 18%', TaxClass::STATUS_INACTIVE);
        $root = $this->createCategory('Fashion', 'fashion');
        $shirts = $this->createCategory('Shirts', 'shirts', $root);

        $this->actingAs($admin)
            ->from(route('admin.master.product-categories.index'))
            ->post(route('admin.master.product-categories.bulk-tax-class'), [
                'bulk_tax_action' => 'assign',
                'tax_class_id' => $inactiveTaxClass->getKey(),
                'category_ids' => [$shirts->getKey()],
            ])
            ->assertRedirect(route('admin.master.product-categories.index'))
            ->assertSessionHasErrors('tax_class_id');

        $this->assertNull($shirts->fresh()->default_tax_class_id);
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

    private function createTaxClass(string $code, string $name, string $status = TaxClass::STATUS_ACTIVE): TaxClass
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

        return TaxClass::query()->create([
            'country_id' => $countryId,
            'code' => $code,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function createTaxClassWithRate(string $code, string $name, string $totalRate, string $componentRate): TaxClass
    {
        $taxClass = $this->createTaxClass($code, $name);
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
