<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopAudienceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $audiences = [
            'Women',
            'Men',
            'Kids',
            'Baby',
            'Unisex',
        ];

        foreach ($audiences as $index => $name) {
            $slug = Str::slug($name);

            DB::table('shop_audiences')->updateOrInsert(
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
