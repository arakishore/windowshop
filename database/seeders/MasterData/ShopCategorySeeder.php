<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $categories = [
            'Apparel',
            'Footwear',
            'Jewellery & Accessories',
            'Beauty & Cosmetics',
            'Mobile & Electronics',
            'Grocery & Daily Needs',
            'Cafe & Restaurant',
            'Home & Furniture',
            'Sports & Fitness',
            'Books & Stationery',
            'Other',
        ];

        foreach ($categories as $index => $name) {
            $slug = Str::slug($name);

            DB::table('shop_categories')->updateOrInsert(
                ['slug' => $slug],
                fn (bool $exists) => [
                    'name' => $name,
                    'description' => null,
                    'sort_order' => $index + 1,
                    'status' => 'active',
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
