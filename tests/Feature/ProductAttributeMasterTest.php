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
        $this->assertFalse(Schema::hasColumn('product_attribute_groups', 'is_variant'));

        $mappings = app(ProductAttributeConfigurationService::class)
            ->forCategory($men)
            ->mapWithKeys(fn ($mapping): array => [
                $mapping->group->code => [
                    'selection_type' => $mapping->group->selection_type,
                    'is_required' => $mapping->is_required,
                    'is_variant' => $mapping->is_variant,
                ],
            ]);

        $this->assertSame([
            'selection_type' => 'multiple',
            'is_required' => true,
            'is_variant' => true,
        ], $mappings->get('color'));
        $this->assertSame([
            'selection_type' => 'multiple',
            'is_required' => true,
            'is_variant' => true,
        ], $mappings->get('size'));
        $this->assertSame([
            'selection_type' => 'single',
            'is_required' => false,
            'is_variant' => false,
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
}
