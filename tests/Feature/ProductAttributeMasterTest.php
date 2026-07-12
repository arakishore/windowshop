<?php

namespace Tests\Feature;

use App\Models\ProductAttributeGroup;
use Database\Seeders\MasterData\ProductAttributeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAttributeMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_attribute_seeder_creates_size_group_and_values(): void
    {
        $this->seed(ProductAttributeSeeder::class);

        $group = ProductAttributeGroup::query()
            ->where('code', 'size')
            ->with('values')
            ->firstOrFail();

        $this->assertSame('Size', $group->name);
        $this->assertSame('Apparel product sizes', $group->description);
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
        ], $group->values->pluck('code')->all());
    }

    public function test_product_attribute_seeder_creates_extended_apparel_groups(): void
    {
        $this->seed(ProductAttributeSeeder::class);

        $this->assertSame([
            'size',
            'color',
            'material',
            'fabric',
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
}
