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
}
