<?php

namespace Database\Seeders\MasterData;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $apparel = $this->createCategory('Apparel', null, 1);

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
        });
    }

    private function createChildren(ProductCategory $parent, array $names): void
    {
        foreach ($names as $index => $name) {
            $this->createCategory($name, $parent, $index + 1);
        }
    }

    private function createCategory(string $name, ?ProductCategory $parent, int $sortOrder): ProductCategory
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
            'slug' => (Str::slug($category->name) ?: 'category').'-'.$category->getKey(),
        ]);

        return $category->refresh();
    }
}
