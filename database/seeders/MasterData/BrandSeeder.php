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
            'boAt',
            'Cambridge',
            'Caprese',
            'Chhabra 555',
            'Colorbar',
            'Classmate',
            'Decathlon',
            'Fabindia',
            'Flying Machine',
            'Gini & Jony',
            'Global Desi',
            'Globus',
            'Indian Terrain',
            'J. Hampstead',
            'John Players',
            'Kalyan Silks',
            'Kent',
            'Libas',
            'Louis Philippe',
            'Lux Cozi',
            'Milton',
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
            'Prestige',
            'Rangriti',
            'Raymond',
            'Relaxo',
            'Samsung',
            'Lavie',
            'Lakme',
            'Lino Perros',
            'Mochi',
            'Shahi Exports',
            'Siyaram\'s',
            'Soch',
            'Spykar',
            'Tata Sampann',
            'Swayamvar',
            'Trends',
            'Van Heusen',
            'Vardhman',
            'Wildcraft',
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
                    'short_code' => $this->shortCodeFor($name),
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

    private function shortCodeFor(string $name): string
    {
        return [
            'Allen Solly' => 'AS',
            'Anokhi' => 'ANK',
            'Aurelia' => 'AUR',
            'Bata' => 'BATA',
            'Being Human' => 'BH',
            'Biba' => 'BIBA',
            'Blackberrys' => 'BBY',
            'Bombay Dyeing' => 'BD',
            'boAt' => 'BOAT',
            'Cambridge' => 'CAM',
            'Caprese' => 'CAP',
            'Chhabra 555' => 'C555',
            'Colorbar' => 'CBR',
            'Classmate' => 'CLS',
            'Decathlon' => 'DEC',
            'Fabindia' => 'FAB',
            'Flying Machine' => 'FM',
            'Gini & Jony' => 'GJ',
            'Global Desi' => 'GD',
            'Globus' => 'GLO',
            'Indian Terrain' => 'IT',
            'J. Hampstead' => 'JH',
            'John Players' => 'JP',
            'Kalyan Silks' => 'KS',
            'Kent' => 'KENT',
            'Libas' => 'LIB',
            'Louis Philippe' => 'LP',
            'Lux Cozi' => 'LC',
            'Milton' => 'MLT',
            'Maybelline' => 'MAY',
            'Manyavar' => 'MNY',
            'Max Fashion' => 'MAX',
            'Meena Bazaar' => 'MB',
            'Monte Carlo' => 'MC',
            'Mufti' => 'MFT',
            'Nykaa Cosmetics' => 'NYK',
            'Nalli Silks' => 'NS',
            'Neeru\'s' => 'NEE',
            'Numero Uno' => 'NU',
            'Oxemberg' => 'OXB',
            'Pantaloons' => 'PAN',
            'Park Avenue' => 'PA',
            'Peter England' => 'PE',
            'Pothys' => 'POT',
            'Prestige' => 'PRE',
            'Rangriti' => 'RAN',
            'Raymond' => 'RAY',
            'Relaxo' => 'RLX',
            'Samsung' => 'SAM',
            'Lavie' => 'LAV',
            'Lakme' => 'LAK',
            'Lino Perros' => 'LPR',
            'Mochi' => 'MOC',
            'Shahi Exports' => 'SE',
            'Siyaram\'s' => 'SIY',
            'Soch' => 'SOCH',
            'Spykar' => 'SPY',
            'Tata Sampann' => 'TS',
            'Swayamvar' => 'SWY',
            'Trends' => 'TRD',
            'Van Heusen' => 'VH',
            'Vardhman' => 'VAR',
            'Wildcraft' => 'WLD',
            'Westside' => 'WST',
            'W for Woman' => 'WFW',
            'Zodiac' => 'ZOD',
            'Zudio' => 'ZUD',
            'Other' => 'OTH',
        ][$name] ?? Str::of($name)
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->join('');
    }

    private function seedBrandCategoryMappings(): void
    {
        $rootCategories = ProductCategory::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->pluck('id', 'name');

        if ($rootCategories->isEmpty()) {
            return;
        }

        $beautyBrands = ['Colorbar', 'Lakme', 'Maybelline', 'Nykaa Cosmetics'];
        $bagBrands = ['Caprese', 'Lavie', 'Lino Perros', 'Mochi'];
        $footwearBrands = ['Bata', 'Relaxo', 'Mochi'];
        $electronicsBrands = ['boAt', 'Kent', 'Samsung'];
        $groceryBrands = ['Tata Sampann'];
        $homeBrands = ['Bombay Dyeing', 'Milton', 'Prestige'];
        $sportsBrands = ['Decathlon', 'Wildcraft'];
        $booksBrands = ['Classmate'];
        $genericBrands = ['Other'];

        Brand::query()
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Brand $brand) use (
                $rootCategories,
                $beautyBrands,
                $bagBrands,
                $footwearBrands,
                $electronicsBrands,
                $groceryBrands,
                $homeBrands,
                $sportsBrands,
                $booksBrands,
                $genericBrands,
            ): void {
                $categoryNames = [];

                if (in_array($brand->name, $beautyBrands, true)) {
                    $categoryNames[] = 'Beauty & Cosmetics';
                }

                if (in_array($brand->name, $bagBrands, true)) {
                    $categoryNames[] = 'Jewellery & Accessories';
                }

                if (in_array($brand->name, $footwearBrands, true)) {
                    $categoryNames[] = 'Footwear';
                }

                if (in_array($brand->name, $electronicsBrands, true)) {
                    $categoryNames[] = 'Mobile & Electronics';
                }

                if (in_array($brand->name, $groceryBrands, true)) {
                    $categoryNames[] = 'Grocery & Daily Needs';
                }

                if (in_array($brand->name, $homeBrands, true)) {
                    $categoryNames[] = 'Home & Furniture';
                }

                if (in_array($brand->name, $sportsBrands, true)) {
                    $categoryNames[] = 'Sports & Fitness';
                }

                if (in_array($brand->name, $booksBrands, true)) {
                    $categoryNames[] = 'Books & Stationery';
                }

                if (in_array($brand->name, $genericBrands, true)) {
                    $categoryNames = $rootCategories->keys()->all();
                }

                if ($categoryNames === []) {
                    $categoryNames = ['Apparel'];
                }

                $ids = collect($categoryNames)
                    ->unique()
                    ->map(fn (string $name) => $rootCategories[$name] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $brand->rootProductCategories()->sync($ids);
            });
    }
}
