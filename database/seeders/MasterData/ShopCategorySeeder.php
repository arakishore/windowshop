<?php

namespace Database\Seeders\MasterData;

use App\Models\ShopCategory;
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
        DB::transaction(function (): void {
            $rootCategories = [
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

            foreach ($rootCategories as $index => $name) {
                $this->createCategory($name, null, $index + 1);
            }

            $apparel = $this->createCategory('Apparel', null, 1);

            $men = $this->createCategory('Men', $apparel, 1);
            $menTopWear = $this->createCategory('Top Wear', $men, 1);
            $this->createChildren($menTopWear, ['T-Shirts', 'Shirts', 'Polo T-Shirts', 'Sweatshirts', 'Jackets']);

            $menBottomWear = $this->createCategory('Bottom Wear', $men, 2);
            $this->createChildren($menBottomWear, ['Jeans', 'Trousers', 'Shorts', 'Track Pants']);

            $menEthnicWear = $this->createCategory('Ethnic Wear', $men, 3);
            $this->createChildren($menEthnicWear, ['Kurtas', 'Kurta Sets', 'Sherwanis']);
            $this->createCategory('Innerwear', $men, 4);
            $this->createCategory('Sleepwear', $men, 5);
            $this->createCategory('Winter Wear', $men, 6);

            $women = $this->createCategory('Women', $apparel, 2);
            $womenWesternWear = $this->createCategory('Western Wear', $women, 1);
            $this->createChildren($womenWesternWear, ['Tops', 'T-Shirts', 'Dresses', 'Jumpsuits']);

            $womenBottomWear = $this->createCategory('Bottom Wear', $women, 2);
            $this->createChildren($womenBottomWear, ['Jeans', 'Trousers', 'Skirts', 'Leggings']);

            $womenEthnicWear = $this->createCategory('Ethnic Wear', $women, 3);
            $this->createChildren($womenEthnicWear, ['Kurtis', 'Kurta Sets', 'Sarees', 'Salwar Suits', 'Lehengas']);
            $this->createCategory('Innerwear', $women, 4);
            $this->createCategory('Sleepwear', $women, 5);
            $this->createCategory('Winter Wear', $women, 6);

            $this->createCategory('Boys', $apparel, 3);
            $this->createCategory('Girls', $apparel, 4);
            $this->createCategory('Baby Clothing', $apparel, 5);
            $this->createCategory('Unisex', $apparel, 6);
        });
    }

    private function createChildren(ShopCategory $parent, array $names): void
    {
        foreach ($names as $index => $name) {
            $this->createCategory($name, $parent, $index + 1);
        }
    }

    private function createCategory(string $name, ?ShopCategory $parent, int $sortOrder): ShopCategory
    {
        $category = ShopCategory::query()
            ->whereNull('deleted_at')
            ->where('parent_id', $parent?->getKey())
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->first();

        if (! $category) {
            $category = new ShopCategory([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $parent?->getKey(),
                'name' => $name,
                'slug' => 'pending-'.Str::uuid()->toString(),
                'description' => null,
                'status' => 'active',
            ]);
        }

        $category->forceFill([
            'parent_id' => $parent?->getKey(),
            'name' => $name,
            'sort_order' => $sortOrder,
            'status' => 'active',
        ])->save();

        $category->updateQuietly([
            'slug' => $this->slugForCategory($category),
        ]);

        return $category->refresh();
    }

    private function slugForCategory(ShopCategory $category): string
    {
        return (Str::slug($category->name) ?: 'category').'-'.$category->getKey();
    }
}
