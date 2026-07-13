<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $groups = [
            [
                'name' => 'Size',
                'code' => 'size',
                'description' => 'Apparel product sizes',
                'selection_type' => 'multiple',
                'values' => [
                    'XS',
                    'S',
                    'M',
                    'L',
                    'XL',
                    'XXL',
                    '3XL',
                    '4XL',
                    '5XL',
                    '6XL',
                    'Free Size',
                ],
            ],
            [
                'name' => 'Color',
                'code' => 'color',
                'description' => 'Product colors',
                'selection_type' => 'multiple',
                'values' => [
                    'Black',
                    'White',
                    'Red',
                    'Blue',
                    'Green',
                    'Yellow',
                    'Pink',
                    'Purple',
                    'Orange',
                    'Brown',
                    'Grey',
                    'Beige',
                    'Navy',
                    'Maroon',
                    'Gold',
                    'Silver',
                    'Multicolor',
                ],
            ],
            [
                'name' => 'Material',
                'code' => 'material',
                'description' => 'Primary product materials',
                'values' => [
                    'Cotton',
                    'Polyester',
                    'Rayon',
                    'Silk',
                    'Wool',
                    'Linen',
                    'Denim',
                    'Leather',
                    'Faux Leather',
                    'Nylon',
                    'Viscose',
                    'Acrylic',
                    'Spandex',
                    'Blended',
                ],
            ],
            [
                'name' => 'Fabric',
                'code' => 'fabric',
                'description' => 'Apparel fabric types',
                'values' => [
                    'Cotton',
                    'Silk',
                    'Georgette',
                    'Chiffon',
                    'Crepe',
                    'Rayon',
                    'Linen',
                    'Denim',
                    'Velvet',
                    'Satin',
                    'Net',
                    'Organza',
                    'Jacquard',
                    'Khadi',
                ],
            ],
            [
                'name' => 'Fit',
                'code' => 'fit',
                'description' => 'Apparel fit types',
                'values' => [
                    'Regular Fit',
                    'Slim Fit',
                    'Relaxed Fit',
                    'Loose Fit',
                    'Oversized Fit',
                    'Tailored Fit',
                    'Straight Fit',
                    'Skinny Fit',
                ],
            ],
            [
                'name' => 'Sleeve',
                'code' => 'sleeve',
                'description' => 'Sleeve styles and lengths',
                'values' => [
                    'Sleeveless',
                    'Short Sleeve',
                    'Half Sleeve',
                    'Three Quarter Sleeve',
                    'Full Sleeve',
                    'Cap Sleeve',
                    'Bell Sleeve',
                    'Puff Sleeve',
                    'Raglan Sleeve',
                ],
            ],
            [
                'name' => 'Neck',
                'code' => 'neck',
                'description' => 'Neckline styles',
                'values' => [
                    'Round Neck',
                    'V Neck',
                    'Collared Neck',
                    'Mandarin Collar',
                    'Boat Neck',
                    'Square Neck',
                    'Halter Neck',
                    'Scoop Neck',
                    'High Neck',
                    'Sweetheart Neck',
                ],
            ],
            [
                'name' => 'Pattern',
                'code' => 'pattern',
                'description' => 'Product patterns and prints',
                'values' => [
                    'Solid',
                    'Printed',
                    'Striped',
                    'Checked',
                    'Floral',
                    'Geometric',
                    'Embroidered',
                    'Textured',
                    'Polka Dot',
                    'Paisley',
                    'Abstract',
                ],
            ],
            [
                'name' => 'Occasion',
                'code' => 'occasion',
                'description' => 'Suitable product occasions',
                'selection_type' => 'multiple',
                'values' => [
                    'Casual',
                    'Formal',
                    'Party',
                    'Festive',
                    'Wedding',
                    'Work',
                    'Sports',
                    'Travel',
                    'Daily Wear',
                    'Ethnic',
                ],
            ],
        ];

        foreach ($groups as $groupIndex => $group) {
            DB::table('product_attribute_groups')->updateOrInsert(
                ['code' => $group['code']],
                fn (bool $exists) => [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'selection_type' => $group['selection_type'] ?? 'single',
                    'status' => 'active',
                    'sort_order' => $groupIndex + 1,
                    'updated_at' => $now,
                    ...($exists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'created_at' => $now,
                    ]),
                ],
            );

            $groupId = (int) DB::table('product_attribute_groups')
                ->where('code', $group['code'])
                ->value('id');

            foreach ($group['values'] as $valueIndex => $name) {
                DB::table('product_attribute_group_values')->updateOrInsert(
                    [
                        'product_attribute_group_id' => $groupId,
                        'code' => Str::slug($name),
                    ],
                    fn (bool $exists) => [
                        'name' => $name,
                        'description' => null,
                        'status' => 'active',
                        'sort_order' => $valueIndex + 1,
                        'updated_at' => $now,
                        ...($exists ? [] : [
                            'uuid' => (string) Str::uuid(),
                            'created_at' => $now,
                        ]),
                    ],
                );
            }
        }

        $this->seedCategoryMappings($now);
    }

    private function seedCategoryMappings(mixed $now): void
    {
        $apparelId = DB::table('product_categories')
            ->whereNull('parent_id')
            ->where('name', 'Apparel')
            ->whereNull('deleted_at')
            ->value('id');

        if ($apparelId === null) {
            return;
        }

        $mappings = [
            ['code' => 'color', 'is_required' => true, 'is_variant' => true, 'sort_order' => 1],
            ['code' => 'size', 'is_required' => true, 'is_variant' => true, 'sort_order' => 2],
            ['code' => 'material', 'is_required' => false, 'is_variant' => false, 'sort_order' => 3],
            ['code' => 'sleeve', 'is_required' => false, 'is_variant' => false, 'sort_order' => 4],
            ['code' => 'neck', 'is_required' => false, 'is_variant' => false, 'sort_order' => 5],
            ['code' => 'pattern', 'is_required' => false, 'is_variant' => false, 'sort_order' => 6],
            ['code' => 'fabric', 'is_required' => false, 'is_variant' => false, 'sort_order' => 7],
            ['code' => 'fit', 'is_required' => false, 'is_variant' => false, 'sort_order' => 8],
            ['code' => 'occasion', 'is_required' => false, 'is_variant' => false, 'sort_order' => 9],
        ];

        foreach ($mappings as $mapping) {
            $groupId = DB::table('product_attribute_groups')
                ->where('code', $mapping['code'])
                ->value('id');

            if ($groupId === null) {
                continue;
            }

            DB::table('product_category_attribute_groups')->updateOrInsert(
                [
                    'product_category_id' => $apparelId,
                    'product_attribute_group_id' => $groupId,
                ],
                fn (bool $exists) => [
                    'is_required' => $mapping['is_required'],
                    'is_variant' => $mapping['is_variant'],
                    'sort_order' => $mapping['sort_order'],
                    'updated_at' => $now,
                    ...($exists ? [] : ['created_at' => $now]),
                ],
            );
        }
    }
}
