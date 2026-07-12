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
            $now = now();
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

            ShopCategory::query()
                ->whereNotNull('parent_id')
                ->whereDoesntHave('shops')
                ->update([
                    'status' => 'deleted',
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        });
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
