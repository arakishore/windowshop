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
                DB::table('product_attribute_values')->updateOrInsert(
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
    }
}
