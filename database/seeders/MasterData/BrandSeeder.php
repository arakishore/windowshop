<?php

namespace Database\Seeders\MasterData;

use App\Models\Brand;
use App\Models\ProductCategory;
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

        $this->seedBrandCategoryMappings();
    }

    private function seedBrandCategoryMappings(): void
    {
        $rootCategories = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('id', 'name');

        if ($rootCategories->isEmpty()) {
            return;
        }

        $beautyBrands = ['Colorbar', 'Lakme', 'Maybelline', 'Nykaa Cosmetics'];
        $bagBrands = ['Caprese', 'Lavie', 'Lino Perros', 'Mochi'];
        $footwearBrands = ['Bata', 'Relaxo', 'Mochi'];
        $homeBrands = ['Bombay Dyeing'];
        $genericBrands = ['Other'];

        Brand::query()
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Brand $brand) use ($rootCategories, $beautyBrands, $bagBrands, $footwearBrands, $homeBrands, $genericBrands): void {
                $categoryNames = ['Apparel'];

                if (in_array($brand->name, $beautyBrands, true)) {
                    $categoryNames = ['Beauty & Cosmetics'];
                } elseif (in_array($brand->name, $bagBrands, true)) {
                    $categoryNames = ['Jewellery & Accessories'];
                } elseif (in_array($brand->name, $footwearBrands, true)) {
                    $categoryNames = ['Footwear'];
                } elseif (in_array($brand->name, $homeBrands, true)) {
                    $categoryNames = ['Home & Furniture'];
                } elseif (in_array($brand->name, $genericBrands, true)) {
                    $categoryNames = $rootCategories->keys()->all();
                }

                $ids = collect($categoryNames)
                    ->map(fn (string $name) => $rootCategories[$name] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $brand->rootProductCategories()->sync($ids);
            });
    }
}
