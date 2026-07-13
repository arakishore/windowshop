<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use Illuminate\Support\Facades\DB;

class ProductCategoryAttributeMappingService
{
    /**
     * @param array<int|string, array<string, mixed>> $mappings
     */
    public function sync(ProductCategory $category, array $mappings): void
    {
        $rootCategoryId = $category->rootCategoryId();

        DB::transaction(function () use ($rootCategoryId, $mappings): void {
            $enabledGroupIds = [];

            foreach ($mappings as $mapping) {
                $groupId = (int) ($mapping['product_attribute_group_id'] ?? 0);

                if ($groupId <= 0 || ! (bool) ($mapping['enabled'] ?? false)) {
                    continue;
                }

                $enabledGroupIds[] = $groupId;

                ProductCategoryAttributeGroup::query()->updateOrCreate(
                    [
                        'root_product_category_id' => $rootCategoryId,
                        'product_attribute_group_id' => $groupId,
                    ],
                    [
                        'is_required' => (bool) ($mapping['is_required'] ?? false),
                        'is_variant' => (bool) ($mapping['is_variant'] ?? false),
                        'sort_order' => (int) ($mapping['sort_order'] ?? 0),
                    ],
                );
            }

            ProductCategoryAttributeGroup::query()
                ->where('root_product_category_id', $rootCategoryId)
                ->when(
                    $enabledGroupIds !== [],
                    fn ($query) => $query->whereNotIn('product_attribute_group_id', $enabledGroupIds),
                )
                ->delete();
        });
    }
}
