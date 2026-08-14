<?php

namespace Database\Seeders\MasterData;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $apparel = $this->createCategory('Apparel', null, 1, 'active', 'apparel');

            $men = $this->createCategory('Men', $apparel, 1);
            $this->createChildren($men, [
                'T-Shirts',
                'Shirts',
                'Polo T-Shirts',
                'Sweatshirts',
                'Jackets',
                'Jeans',
                'Trousers',
                'Shorts',
                'Track Pants',
                'Kurtas',
                'Kurta Sets',
                'Sherwanis',
                'Innerwear',
                'Sleepwear',
                'Winter Wear',
            ]);

            $women = $this->createCategory('Women', $apparel, 2);
            $this->createChildren($women, [
                'T-Shirts',
                'Tops',
                'Shirts',
                'Kurtis',
                'Kurta Sets',
                'Sarees',
                'Lehengas',
                'Dresses',
                'Jeans',
                'Leggings',
                'Skirts',
                'Trousers',
                'Innerwear',
                'Sleepwear',
                'Winter Wear',
            ]);

            $this->createCategory('Boys', $apparel, 3);
            $this->createCategory('Girls', $apparel, 4);
            $this->createCategory('Baby Clothing', $apparel, 5);
            $this->createCategory('Unisex', $apparel, 6);

            $footwear = $this->createCategory('Footwear', null, 2, 'active', 'footwear');
            $this->createChildren($footwear, [
                'Men',
                'Women',
                'Kids',
            ]);

            $electronics = $this->createCategory('Mobile & Electronics', null, 3, 'active', 'mobile-electronics');
            $this->createChildren($electronics, [
                'Mobile Phones',
                'Laptops',
                'Accessories',
                'Audio',
                'Smart Watches',
            ]);

            $beauty = $this->createCategory('Beauty & Cosmetics', null, 4, 'active', 'beauty-cosmetics');
            $this->createChildren($beauty, [
                'Makeup',
                'Skin Care',
                'Hair Care',
            ]);

            $jewellery = $this->createCategory('Jewellery & Accessories', null, 5, 'active', 'jewellery-accessories');
            $this->createChildren($jewellery, [
                'Fashion Jewellery',
                'Bags',
                'Accessories',
            ]);

            $grocery = $this->createCategory('Grocery & Daily Needs', null, 6, 'inactive', 'grocery-daily-needs');
            $this->createChildren($grocery, [
                'Staples',
                'Snacks',
                'Beverages',
                'Personal Care',
                'Household',
            ]);

            $home = $this->createCategory('Home & Furniture', null, 8, 'active', 'home-furniture');
            $this->createChildren($home, [
                'Furniture',
                'Home Decor',
                'Kitchen',
                'Dining',
                'Bedding',
            ]);

            $sports = $this->createCategory('Sports & Fitness', null, 9, 'active', 'sports-fitness');
            $this->createChildren($sports, [
                'Fitness Equipment',
                'Sportswear',
                'Sports Shoes',
                'Accessories',
            ]);

            $books = $this->createCategory('Books & Stationery', null, 10, 'active', 'books-stationery');
            $this->createChildren($books, [
                'Books',
                'Notebooks',
                'Pens',
                'Art Supplies',
                'Office Supplies',
            ]);

            $this->ensureOtherChildForParentCategories();
        });
    }

    private function createChildren(ProductCategory $parent, array $names): void
    {
        foreach ($names as $index => $name) {
            $this->createCategory($name, $parent, $index + 1);
        }
    }

    private function createCategory(
        string $name,
        ?ProductCategory $parent,
        int $sortOrder,
        string $status = 'active',
        ?string $imageAsset = null,
    ): ProductCategory
    {
        $category = ProductCategory::query()
            ->whereNull('deleted_at')
            ->where('parent_id', $parent?->getKey())
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->first();

        if (! $category) {
            $category = new ProductCategory([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $parent?->getKey(),
                'name' => $name,
                'slug' => 'pending-'.Str::uuid()->toString(),
                'status' => $status,
            ]);
        }

        $category->forceFill([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'short_code' => $this->shortCodeFor($name, $parent),
            'sort_order' => $sortOrder,
            'status' => $status,
        ])->save();

        $category->updateQuietly([
            'slug' => (Str::slug($category->name) ?: 'category').'-'.$category->getKey(),
        ]);

        if ($imageAsset !== null) {
            $imagePath = $this->seedImage($category, $imageAsset);

            if ($imagePath !== null) {
                $category->forceFill(['image_path' => $imagePath])->save();
            }
        }

        return $category->refresh();
    }

    private function seedImage(ProductCategory $category, string $asset): ?string
    {
        $sourceDirectory = database_path("seeders/assets/product-categories/{$asset}");

        if (! File::isDirectory($sourceDirectory)) {
            return null;
        }

        $targetDirectory = "product-categories/{$category->getKey()}/image";

        foreach (File::files($sourceDirectory) as $file) {
            Storage::disk('public')->put(
                "{$targetDirectory}/{$file->getFilename()}",
                File::get($file->getPathname()),
            );
        }

        return "{$targetDirectory}/web.webp";
    }

    private function ensureOtherChildForParentCategories(): void
    {
        ProductCategory::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['other'])
            ->whereHas('children', fn ($query) => $query->whereNull('deleted_at')->whereRaw('LOWER(TRIM(name)) <> ?', ['other']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (ProductCategory $parent): void {
                $this->createCategory('Other', $parent, 99);
            });
    }

    private function shortCodeFor(string $name, ?ProductCategory $parent): string
    {
        if ($parent === null) {
            return [
                'Apparel' => 'APP',
                'Footwear' => 'FW',
                'Mobile & Electronics' => 'ME',
                'Beauty & Cosmetics' => 'BCOS',
                'Jewellery & Accessories' => 'JA',
                'Grocery & Daily Needs' => 'GDN',
                'Home & Furniture' => 'HF',
                'Sports & Fitness' => 'SF',
                'Books & Stationery' => 'BS',
            ][$name] ?? $this->fallbackShortCode($name);
        }

        $codesByParent = [
            'Apparel' => [
                'Men' => 'MEN',
                'Women' => 'WOM',
                'Boys' => 'BOY',
                'Girls' => 'GRL',
                'Baby Clothing' => 'BC',
                'Unisex' => 'UNI',
                'Other' => 'APPO',
            ],
            'Men' => [
                'T-Shirts' => 'MT',
                'Shirts' => 'MS',
                'Polo T-Shirts' => 'MPT',
                'Sweatshirts' => 'MSW',
                'Jackets' => 'MJKT',
                'Jeans' => 'MJ',
                'Trousers' => 'MTR',
                'Shorts' => 'MSH',
                'Track Pants' => 'MTP',
                'Kurtas' => 'MK',
                'Kurta Sets' => 'MKS',
                'Sherwanis' => 'MSR',
                'Innerwear' => 'MIN',
                'Sleepwear' => 'MSL',
                'Winter Wear' => 'MWW',
                'Other' => 'MO',
            ],
            'Women' => [
                'T-Shirts' => 'WT',
                'Tops' => 'WTO',
                'Shirts' => 'WS',
                'Kurtis' => 'WK',
                'Kurta Sets' => 'WKS',
                'Sarees' => 'WSA',
                'Lehengas' => 'WL',
                'Dresses' => 'WD',
                'Jeans' => 'WJ',
                'Leggings' => 'WLG',
                'Skirts' => 'WSK',
                'Trousers' => 'WTR',
                'Innerwear' => 'WIN',
                'Sleepwear' => 'WSL',
                'Winter Wear' => 'WWW',
                'Other' => 'WO',
            ],
            'Footwear' => [
                'Men' => 'FM',
                'Women' => 'FWO',
                'Kids' => 'FK',
                'Other' => 'FWOH',
            ],
            'Mobile & Electronics' => [
                'Mobile Phones' => 'MP',
                'Laptops' => 'LAP',
                'Accessories' => 'MEA',
                'Audio' => 'AUD',
                'Smart Watches' => 'SW',
                'Other' => 'MEO',
            ],
            'Beauty & Cosmetics' => [
                'Makeup' => 'MKP',
                'Skin Care' => 'SC',
                'Hair Care' => 'HC',
                'Other' => 'BCO',
            ],
            'Jewellery & Accessories' => [
                'Fashion Jewellery' => 'FJ',
                'Bags' => 'BAG',
                'Accessories' => 'JAA',
                'Other' => 'JAO',
            ],
            'Grocery & Daily Needs' => [
                'Staples' => 'STP',
                'Snacks' => 'SNK',
                'Beverages' => 'BEV',
                'Personal Care' => 'PC',
                'Household' => 'HH',
                'Other' => 'GDNO',
            ],
            'Home & Furniture' => [
                'Furniture' => 'FUR',
                'Home Decor' => 'HD',
                'Kitchen' => 'KIT',
                'Dining' => 'DIN',
                'Bedding' => 'BED',
                'Other' => 'HFO',
            ],
            'Sports & Fitness' => [
                'Fitness Equipment' => 'FE',
                'Sportswear' => 'SPW',
                'Sports Shoes' => 'SPS',
                'Accessories' => 'SFA',
                'Other' => 'SFO',
            ],
            'Books & Stationery' => [
                'Books' => 'BKS',
                'Notebooks' => 'NB',
                'Pens' => 'PEN',
                'Art Supplies' => 'AS',
                'Office Supplies' => 'OS',
                'Other' => 'BSO',
            ],
        ];

        return $codesByParent[$parent->name][$name] ?? $this->fallbackShortCode($name);
    }

    private function fallbackShortCode(string $name): string
    {
        return Str::of($name)
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->join('') ?: 'CAT';
    }
}
