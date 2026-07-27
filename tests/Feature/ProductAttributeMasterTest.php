<?php

namespace Tests\Feature;

use App\Models\ProductAttributeGroup;
use App\Models\ProductCategory;
use Database\Seeders\MasterData\ProductAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Product\ProductAttributeConfigurationService;
use PDO;
use Tests\TestCase;

class ProductAttributeMasterTest extends TestCase
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

    public function test_product_attribute_seeder_creates_size_group_and_values(): void
    {
        $this->seed(ProductAttributeSeeder::class);

        $group = ProductAttributeGroup::query()
            ->where('code', 'size')
            ->with('values')
            ->firstOrFail();

        $this->assertSame('Size', $group->name);
        $this->assertSame('Apparel product sizes', $group->description);
        $this->assertSame('multiple', $group->selection_type);
        $this->assertSame('active', $group->status);
        $this->assertSame(1, (int) $group->sort_order);

        $this->assertSame([
            'xs',
            's',
            'm',
            'l',
            'xl',
            'xxl',
            '3xl',
            '4xl',
            '5xl',
            '6xl',
            'free-size',
            'shoe-size-5',
            'shoe-size-6',
            'shoe-size-7',
            'shoe-size-8',
            'shoe-size-9',
            'shoe-size-10',
            'shoe-size-11',
            'shoe-size-12',
        ], $group->values->pluck('code')->all());
    }

    public function test_product_attribute_seeder_creates_extended_apparel_groups(): void
    {
        $this->seed(ProductAttributeSeeder::class);

        $this->assertSame([
            'size',
            'color',
            'material',
            'fit',
            'sleeve',
            'neck',
            'pattern',
            'occasion',
            'festival',
            'season',
            'style',
            'length',
            'waist_rise',
            'closure',
            'care',
            'country_of_origin',
            'brand_model',
            'storage',
            'ram',
            'warranty',
            'connectivity',
            'pack_size',
            'net_quantity',
            'flavor',
            'shelf_life',
            'diet_type',
            'food_type',
            'spice_level',
            'portion_size',
            'shade',
            'skin_type',
            'finish',
            'form',
            'dimensions',
            'room_type',
            'assembly_required',
            'sport_type',
            'weight',
            'language',
            'binding',
            'subject',
            'class_standard',
        ], ProductAttributeGroup::query()
            ->orderBy('sort_order')
            ->pluck('code')
            ->all());

        $color = ProductAttributeGroup::query()
            ->where('code', 'color')
            ->with('values')
            ->firstOrFail();

        $this->assertContains('multicolor', $color->values->pluck('code')->all());

        $occasion = ProductAttributeGroup::query()
            ->where('code', 'occasion')
            ->with('values')
            ->firstOrFail();

        $this->assertSame('Daily Wear', $occasion->values->firstWhere('code', 'daily-wear')?->name);

        $festival = ProductAttributeGroup::query()
            ->where('code', 'festival')
            ->with('values')
            ->firstOrFail();

        $this->assertSame('Diwali', $festival->values->firstWhere('code', 'diwali')?->name);

        $shade = ProductAttributeGroup::query()
            ->where('code', 'shade')
            ->with('values')
            ->firstOrFail();

        $this->assertSame('Caramel', $shade->values->firstWhere('code', 'caramel')?->name);
    }

    public function test_apparel_attribute_mapping_marks_only_color_and_size_as_variant(): void
    {
        $apparel = ProductCategory::query()->create([
            'parent_id' => null,
            'name' => 'Apparel',
            'slug' => 'apparel-test',
            'status' => 'active',
        ]);
        $men = ProductCategory::query()->create([
            'parent_id' => $apparel->getKey(),
            'name' => 'Men',
            'slug' => 'men-test',
            'status' => 'active',
        ]);

        $this->seed(ProductAttributeSeeder::class);

        $this->assertTrue(Schema::hasColumn('product_category_attribute_groups', 'is_variant'));
        $this->assertTrue(Schema::hasColumn('product_category_attribute_groups', 'is_image_attribute'));
        $this->assertFalse(Schema::hasColumn('product_attribute_groups', 'is_variant'));

        $mappings = app(ProductAttributeConfigurationService::class)
            ->forCategory($men)
            ->mapWithKeys(fn ($mapping): array => [
                $mapping->group->code => [
                    'selection_type' => $mapping->group->selection_type,
                    'is_required' => $mapping->is_required,
                    'is_variant' => $mapping->is_variant,
                    'is_image_attribute' => $mapping->is_image_attribute,
                ],
            ]);

        $this->assertSame([
            'selection_type' => 'multiple',
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => true,
        ], $mappings->get('color'));
        $this->assertSame([
            'selection_type' => 'multiple',
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => false,
        ], $mappings->get('size'));
        $this->assertSame([
            'selection_type' => 'single',
            'is_required' => false,
            'is_variant' => false,
            'is_image_attribute' => false,
        ], $mappings->get('material'));
        $this->assertFalse($mappings->get('sleeve')['is_variant']);
        $this->assertFalse($mappings->get('neck')['is_variant']);
        $this->assertFalse($mappings->get('pattern')['is_variant']);

        $this->assertSame(
            ['color', 'size'],
            app(ProductAttributeConfigurationService::class)
                ->variantGroupsForCategory($men)
                ->pluck('group.code')
                ->all(),
        );
    }

    public function test_attribute_seeder_maps_relevant_attributes_to_each_root_shop_type(): void
    {
        foreach ([
            'Apparel',
            'Footwear',
            'Mobile & Electronics',
            'Beauty & Cosmetics',
            'Jewellery & Accessories',
            'Grocery & Daily Needs',
            'Cafe & Restaurant',
            'Home & Furniture',
            'Sports & Fitness',
            'Books & Stationery',
            'Other',
        ] as $index => $name) {
            ProductCategory::query()->create([
                'parent_id' => null,
                'name' => $name,
                'slug' => 'root-'.($index + 1),
                'status' => 'active',
                'sort_order' => $index + 1,
            ]);
        }

        $this->seed(ProductAttributeSeeder::class);

        $mappedRoots = DB::table('product_category_attribute_groups as mappings')
            ->join('product_categories as roots', 'roots.id', '=', 'mappings.root_product_category_id')
            ->join('product_attribute_groups as groups', 'groups.id', '=', 'mappings.product_attribute_group_id')
            ->select('roots.name as root_name', 'groups.code')
            ->get()
            ->groupBy('root_name')
            ->map(fn ($rows) => $rows->pluck('code')->all());

        $this->assertContains('shade', $mappedRoots->get('Beauty & Cosmetics'));
        $this->assertContains('storage', $mappedRoots->get('Mobile & Electronics'));
        $this->assertContains('diet_type', $mappedRoots->get('Grocery & Daily Needs'));
        $this->assertContains('food_type', $mappedRoots->get('Cafe & Restaurant'));
        $this->assertContains('room_type', $mappedRoots->get('Home & Furniture'));
        $this->assertContains('sport_type', $mappedRoots->get('Sports & Fitness'));
        $this->assertContains('language', $mappedRoots->get('Books & Stationery'));
        $this->assertContains('warranty', $mappedRoots->get('Other'));
    }
}
