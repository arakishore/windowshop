<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $brands = [
            'Allen Solly',
            'Anokhi',
            'Aurelia',
            'Bata',
            'Being Human',
            'Biba',
            'Blackberrys',
            'Bombay Dyeing',
            'Cambridge',
            'Caprese',
            'Chhabra 555',
            'Colorbar',
            'Fabindia',
            'Flying Machine',
            'Gini & Jony',
            'Global Desi',
            'Globus',
            'Indian Terrain',
            'J. Hampstead',
            'John Players',
            'Kalyan Silks',
            'Libas',
            'Louis Philippe',
            'Lux Cozi',
            'Maybelline',
            'Manyavar',
            'Max Fashion',
            'Meena Bazaar',
            'Monte Carlo',
            'Mufti',
            'Nykaa Cosmetics',
            'Nalli Silks',
            'Neeru\'s',
            'Numero Uno',
            'Oxemberg',
            'Pantaloons',
            'Park Avenue',
            'Peter England',
            'Pothys',
            'Rangriti',
            'Raymond',
            'Relaxo',
            'Lavie',
            'Lakme',
            'Lino Perros',
            'Mochi',
            'Shahi Exports',
            'Siyaram\'s',
            'Soch',
            'Spykar',
            'Swayamvar',
            'Trends',
            'Van Heusen',
            'Vardhman',
            'Westside',
            'W for Woman',
            'Zodiac',
            'Zudio',
            'Other',
        ];

        foreach ($brands as $index => $name) {
            $slug = Str::slug($name);

            DB::table('brands')->updateOrInsert(
                ['slug' => $slug],
                fn(bool $exists) => [
                    'name' => $name,
                    'description' => null,
                    'website_url' => null,
                    'sort_order' => $index + 1,
                    'status' => 'active',
                    'deleted_at' => null,
                    'updated_at' => $now,
                    ...($exists ? [] : [
                        'uuid' => (string) Str::uuid(),
                        'logo_path' => null,
                        'created_at' => $now,
                    ]),
                ],
            );
        }
    }
}
