<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\Product\ProductVariantGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminProductVariantGenerationTest extends TestCase
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

    public function test_variant_preview_uses_only_variant_mapped_groups(): void
    {
        [$admin, $product, $color, $size, $material] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M', 'L']],
            [$material, ['Cotton']],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertOk()
            ->assertSee('Variant Preview')
            ->assertSee('4 new variants available')
            ->assertSee('Red / M')
            ->assertSee('Blue / L')
            ->assertDontSee('Red / M / Cotton');
    }

    public function test_variants_tab_without_selected_variant_attributes_guides_to_attributes(): void
    {
        [$admin, $product] = $this->productWithVariantSetup();

        $response = $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']));

        $response->assertOk()
            ->assertSee('This product currently has one sellable item.')
            ->assertSee('Go to Attributes')
            ->assertSee(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']), false)
            ->assertSee('<table', false)
            ->assertSee($product->variants()->where('is_default', true)->firstOrFail()->name);
    }

    public function test_variants_tab_with_selected_attributes_shows_generate_guidance(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M', 'L']],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']));

        $response->assertOk()
            ->assertSee('4 variants can be generated.')
            ->assertSee('Red / M')
            ->assertSee('Blue / L')
            ->assertSee('Showing 4 of 4 combinations.')
            ->assertSee(route('admin.products.variants.generate', $product), false)
            ->assertSee('Generate Variants')
            ->assertSee('<table', false);
    }

    public function test_variants_tab_with_selected_attributes_and_no_base_pricing_allows_generation(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $leaf = $this->createCategory('T-Shirts', $root);
        $product = $this->createProduct($admin, $root, $leaf);
        $finish = $this->createGroup('Finish', 'multiple', ['Matte', 'Glossy']);

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 1,
        ]);
        $this->selectValues($product, [[$finish, ['Matte', 'Glossy']]]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertOk()
            ->assertSee('2 variants can be generated.')
            ->assertSee('Generate Variants')
            ->assertDontSee('Base pricing is missing.');
    }

    public function test_variants_tab_examples_are_generated_from_mapping_not_attribute_names(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $leaf = $this->createCategory('Kurtas', $root);
        $product = $this->createProduct($admin, $root, $leaf);
        $this->createBaseVariant($product);
        $finish = $this->createGroup('Finish', 'multiple', ['Matte', 'Glossy']);

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 1,
        ]);
        $this->selectValues($product, [[$finish, ['Matte', 'Glossy']]]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertOk()
            ->assertSee('2 variants can be generated.')
            ->assertSee('Matte')
            ->assertSee('Glossy')
            ->assertDontSee('Color')
            ->assertDontSee('Size')
            ->assertSee('<table', false);
    }

    public function test_two_variant_groups_generate_four_variants_with_attributes(): void
    {
        [$admin, $product, $color, $size, $material] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M', 'L']],
            [$material, ['Cotton']],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.variants.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHas('success', '4 product variants generated successfully.');

        $this->assertSame(4, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'name' => 'Red / M',
            'mrp' => 1949,
            'selling_price' => 1539,
            'cost_price' => 1100,
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'is_default' => true,
            'status' => 'active',
        ]);
        $this->assertSame(8, DB::table('product_variant_attributes')->count());
        $this->assertDatabaseMissing('product_variant_attributes', [
            'product_attribute_group_id' => $material->getKey(),
        ]);
    }

    public function test_variants_tab_with_generated_variants_shows_table(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red']],
            [$size, ['M']],
        ]);

        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Variant')
            ->assertSee('SKU')
            ->assertSee('MRP <span class="text-danger">*</span>', false)
            ->assertSee('Selling Price <span class="text-danger">*</span>', false)
            ->assertSee('Cost Price <span class="text-muted fw-normal">(Optional)</span>', false)
            ->assertSee('title="Used for profit and margin reports. Leave blank if not required."', false)
            ->assertSee('title="Enter only the fields you want to change. Blank fields are ignored. Apply to Selected updates checked rows; Apply to All updates every variant for this product."', false)
            ->assertSee('Red / M')
            ->assertDontSee('No product variants available.')
            ->assertDontSee('Variants are ready to be generated.');
    }

    public function test_one_variant_group_with_three_values_generates_three_variants(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        ProductCategoryAttributeGroup::query()
            ->where('product_attribute_group_id', $size->getKey())
            ->delete();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue', 'Black']],
            [$size, ['M', 'L']],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.variants.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']));

        $this->assertSame(
            ['Red', 'Blue', 'Black'],
            $product->variants()->pluck('name')->all(),
        );
    }

    public function test_required_variant_group_without_selection_blocks_generation(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red']],
        ]);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->post(route('admin.products.variants.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertSessionHasErrors('variants');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_duplicate_generation_creates_only_missing_variants_and_preserves_existing_data(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M', 'L']],
        ]);

        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));
        $variant = $product->variants()->where('name', 'Red / M')->firstOrFail();
        $variant->forceFill([
            'sku' => 'CUSTOM-SKU',
            'selling_price' => 1234,
            'stock_quantity' => 9,
        ])->save();

        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));

        $this->assertSame(4, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'sku' => 'CUSTOM-SKU',
            'selling_price' => 1234,
            'stock_quantity' => 9,
        ]);

        $black = $color->values->firstWhere('name', 'Black');
        $product->attributes()->create([
            'product_attribute_group_id' => $color->getKey(),
            'product_attribute_group_value_id' => $black->getKey(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.variants.generate', $product))
            ->assertSessionHas('success', '2 product variants generated successfully.');

        $this->assertSame(6, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'name' => 'Black / M',
            'mrp' => 1949,
            'selling_price' => 1234,
            'cost_price' => 1100,
            'low_stock_threshold' => 5,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'name' => 'Black / L',
            'mrp' => 1949,
            'selling_price' => 1234,
            'cost_price' => 1100,
            'low_stock_threshold' => 5,
        ]);
    }

    public function test_existing_default_variant_remains_default(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $existing = $product->variants()->where('is_default', true)->firstOrFail();
        $this->selectValues($product, [
            [$color, ['Red']],
            [$size, ['M']],
        ]);

        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));

        $this->assertTrue($existing->fresh()->is_default);
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
        $this->assertDatabaseHas('product_variants', [
            'id' => $existing->getKey(),
            'name' => 'Red / M',
            'stock_quantity' => 0,
            'mrp' => 1949,
            'selling_price' => 1539,
            'cost_price' => 1100,
        ]);
    }

    public function test_combination_limit_is_enforced(): void
    {
        config(['products.max_variant_combinations' => 3]);
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M', 'L']],
        ]);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->post(route('admin.products.variants.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->assertSessionHasErrors('variants');

        $this->assertSame(1, $product->variants()->count());
    }

    public function test_generation_creates_zero_priced_draft_variants_when_base_pricing_is_zero(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $leaf = $this->createCategory('T-Shirts', $root);
        $product = $this->createProduct($admin, $root, $leaf);
        $finish = $this->createGroup('Finish', 'multiple', ['Matte']);

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $root->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 1,
        ]);
        $this->selectValues($product, [[$finish, ['Matte']]]);

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'attributes']))
            ->post(route('admin.products.variants.generate', $product))
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHas('success', '1 product variants generated successfully.');

        $this->assertSame(1, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->getKey(),
            'name' => 'Matte',
            'mrp' => 0,
            'selling_price' => 0,
            'stock_quantity' => 0,
            'is_default' => true,
        ]);
    }

    public function test_category_specific_variant_mapping_does_not_depend_on_attribute_name(): void
    {
        $admin = $this->createAdminUser();
        $apparel = $this->createCategory('Apparel');
        $furniture = $this->createCategory('Furniture');
        $apparelLeaf = $this->createCategory('Kurtas', $apparel);
        $furnitureLeaf = $this->createCategory('Chairs', $furniture);
        $finish = $this->createGroup('Finish', 'multiple', ['Matte', 'Glossy']);
        $apparelProduct = $this->createProduct($admin, $apparel, $apparelLeaf);
        $furnitureProduct = $this->createProduct($this->createAdminUser(), $furniture, $furnitureLeaf);

        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $apparel->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => true,
            'is_variant' => true,
            'sort_order' => 1,
        ]);
        ProductCategoryAttributeGroup::query()->create([
            'root_product_category_id' => $furniture->getKey(),
            'product_attribute_group_id' => $finish->getKey(),
            'is_required' => false,
            'is_variant' => false,
            'sort_order' => 1,
        ]);

        $this->selectValues($apparelProduct, [[$finish, ['Matte', 'Glossy']]]);
        $this->selectValues($furnitureProduct, [[$finish, ['Matte', 'Glossy']]]);

        $service = app(ProductVariantGenerationService::class);

        $this->assertSame(2, $service->preview($apparelProduct)['total']);
        $this->assertSame(0, $service->preview($furnitureProduct)['total']);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', ['product' => $furnitureProduct, 'tab' => 'attributes']))
            ->assertOk()
            ->assertDontSee('Generate Variants');
    }

    public function test_individual_variant_row_editing_updates_only_owned_variant(): void
    {
        [$admin, $product] = $this->productWithVariantSetup();
        $variant = $product->variants()->where('is_default', true)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.products.variants.update', $product), [
                'variants' => [
                    $variant->getKey() => [
                        'sku' => 'LW-001',
                        'barcode' => 'BAR-001',
                        'mrp' => 999,
                        'selling_price' => 799,
                        'cost_price' => 500,
                        'stock_quantity' => 10,
                        'low_stock_threshold' => 2,
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHas('success', '1 variant rows updated successfully.');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->getKey(),
            'sku' => 'LW-001',
            'barcode' => 'BAR-001',
            'mrp' => 999,
            'selling_price' => 799,
            'cost_price' => 500,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
            'status' => 'active',
        ]);
    }

    public function test_bulk_update_applies_only_supplied_fields_to_selected_variants(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M']],
        ]);
        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));

        $selected = $product->variants()->where('name', 'Blue / M')->firstOrFail();
        $untouched = $product->variants()->where('name', 'Red / M')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.products.variants.bulk-update', $product), [
                'scope' => 'selected',
                'variant_ids' => [$selected->getKey()],
                'changes' => [
                    'stock_quantity' => 7,
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHas('success', '1 variant rows updated successfully.');

        $this->assertSame(7, (int) $selected->fresh()->stock_quantity);
        $this->assertSame(0, (int) $untouched->fresh()->stock_quantity);
        $this->assertSame('1539.00', (string) $selected->fresh()->selling_price);
    }

    public function test_bulk_update_can_apply_supplied_fields_to_all_variants(): void
    {
        [$admin, $product, $color, $size] = $this->productWithVariantSetup();
        $this->selectValues($product, [
            [$color, ['Red', 'Blue']],
            [$size, ['M']],
        ]);
        $this->actingAs($admin)->post(route('admin.products.variants.generate', $product));

        $this->actingAs($admin)
            ->put(route('admin.products.variants.bulk-update', $product), [
                'scope' => 'all',
                'changes' => [
                    'mrp' => 1200,
                    'selling_price' => 999,
                    'status' => 'inactive',
                ],
            ])
            ->assertSessionHas('success', '2 variant rows updated successfully.');

        $this->assertSame(2, $product->variants()->where('mrp', 1200)->where('selling_price', 999)->where('status', 'inactive')->count());
    }

    public function test_variant_updates_reject_invalid_stock_and_foreign_variant_ids(): void
    {
        [$admin, $product] = $this->productWithVariantSetup();
        [$otherAdmin, $otherProduct] = $this->productWithVariantSetup();
        $variant = $product->variants()->where('is_default', true)->firstOrFail();
        $foreignVariant = $otherProduct->variants()->where('is_default', true)->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->put(route('admin.products.variants.update', $product), [
                'variants' => [
                    $variant->getKey() => [
                        'sku' => null,
                        'barcode' => null,
                        'mrp' => 999,
                        'selling_price' => 799,
                        'cost_price' => null,
                        'stock_quantity' => 1.5,
                        'low_stock_threshold' => 0,
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHasErrors('variants.'.$variant->getKey().'.stock_quantity');

        $this->actingAs($otherAdmin);
        $this->actingAs($admin)
            ->from(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->put(route('admin.products.variants.update', $product), [
                'variants' => [
                    $foreignVariant->getKey() => [
                        'sku' => 'FOREIGN',
                        'barcode' => null,
                        'mrp' => 999,
                        'selling_price' => 799,
                        'cost_price' => null,
                        'stock_quantity' => 1,
                        'low_stock_threshold' => 0,
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', ['product' => $product, 'tab' => 'variants']))
            ->assertSessionHasErrors('variants');
    }

    public function test_product_publishing_requires_priced_active_variants_but_allows_zero_stock(): void
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $leaf = $this->createCategory('T-Shirts', $root);
        $product = $this->createProduct($admin, $root, $leaf);
        $variant = $this->createBaseVariant($product);
        $variant->forceFill([
            'mrp' => 0,
            'selling_price' => 0,
            'stock_quantity' => 0,
        ])->save();

        $payload = [
            'shop_id' => $product->shop_id,
            'product_category_id' => $leaf->getKey(),
            'brand_id' => null,
            'product_name' => $product->product_name,
            'status' => 'active',
        ];

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors([
                'status' => 'This product cannot be published because one or more active variants have incomplete pricing.',
            ]);

        $variant->forceFill([
            'mrp' => 999,
            'selling_price' => 799,
            'stock_quantity' => 0,
        ])->save();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $payload)
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHas('success', 'Product basic information updated successfully.');

        $this->assertSame('active', $product->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Product, 2: ProductAttributeGroup, 3: ProductAttributeGroup, 4: ProductAttributeGroup}
     */
    private function productWithVariantSetup(): array
    {
        $admin = $this->createAdminUser();
        $root = $this->createCategory('Apparel');
        $leaf = $this->createCategory('T-Shirts', $root);
        $product = $this->createProduct($admin, $root, $leaf);
        $this->createBaseVariant($product);
        $color = $this->createGroup('Color', 'multiple', ['Red', 'Blue', 'Black']);
        $size = $this->createGroup('Size', 'multiple', ['M', 'L']);
        $material = $this->createGroup('Material', 'single', ['Cotton']);

        foreach ([
            [$color, true, true, 1],
            [$size, true, true, 2],
            [$material, false, false, 3],
        ] as [$group, $required, $variant, $sort]) {
            ProductCategoryAttributeGroup::query()->create([
                'root_product_category_id' => $root->getKey(),
                'product_attribute_group_id' => $group->getKey(),
                'is_required' => $required,
                'is_variant' => $variant,
                'sort_order' => $sort,
            ]);
        }

        return [$admin, $product, $color->load('values'), $size->load('values'), $material->load('values')];
    }

    private function createBaseVariant(Product $product): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->getKey(),
            'shop_id' => $product->shop_id,
            'sku' => 'BASE-'.$product->getKey(),
            'barcode' => null,
            'name' => $product->product_name,
            'mrp' => 1949,
            'selling_price' => 1539,
            'cost_price' => 1100,
            'stock_quantity' => 88,
            'low_stock_threshold' => 5,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
        ]);
    }

    /**
     * @param array<int, array{0: ProductAttributeGroup, 1: array<int, string>}> $selection
     */
    private function selectValues(Product $product, array $selection): void
    {
        foreach ($selection as [$group, $valueNames]) {
            $group->loadMissing('values');

            foreach ($valueNames as $name) {
                $value = $group->values->firstWhere('name', $name);

                $product->attributes()->create([
                    'product_attribute_group_id' => $group->getKey(),
                    'product_attribute_group_value_id' => $value->getKey(),
                ]);
            }
        }
    }

    private function createAdminUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'variant-admin-'.Str::random(6).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->where('slug', 'super_admin')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
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

    private function createProduct(User $user, ProductCategory $root, ProductCategory $category): Product
    {
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Demo Merchant '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Demo Shop '.Str::random(4),
            'slug' => 'demo-shop-'.Str::random(6),
            'address_line_1' => 'Nashik',
            'status' => 'active',
        ]);

        return Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Cotton Product '.Str::random(4),
            'slug' => 'cotton-product-'.Str::random(6),
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
            'code' => Str::slug($name).'-'.Str::random(4),
            'selection_type' => $selectionType,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        foreach ($values as $index => $value) {
            ProductAttributeGroupValue::query()->create([
                'product_attribute_group_id' => $group->getKey(),
                'name' => $value,
                'code' => Str::slug($value).'-'.Str::random(4),
                'status' => 'active',
                'sort_order' => $index + 1,
            ]);
        }

        return $group;
    }
}
