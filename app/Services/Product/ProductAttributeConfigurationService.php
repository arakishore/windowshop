<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use Illuminate\Support\Collection;

class ProductAttributeConfigurationService
{
    /**
     * Return attribute mappings for a selected category.
     *
     * Mappings are category-specific because the same group, such as Color,
     * may generate variants for Apparel but be descriptive for another root.
     *
     * @return Collection<int, ProductCategoryAttributeGroup>
     */
    public function forCategory(ProductCategory $category): Collection
    {
        $categoryIds = $this->ancestorIds($category);

        $mappings = ProductCategoryAttributeGroup::query()
            ->with('group.values')
            ->whereIn('product_category_id', $categoryIds)
            ->get()
            ->sortBy(fn (ProductCategoryAttributeGroup $mapping): int => array_search((int) $mapping->product_category_id, $categoryIds, true) ?: 0);

        return $mappings
            ->keyBy('product_attribute_group_id')
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * Only mappings marked as variant attributes should drive variant creation.
     *
     * @return Collection<int, ProductCategoryAttributeGroup>
     */
    public function variantGroupsForCategory(ProductCategory $category): Collection
    {
        return $this->forCategory($category)
            ->filter(fn (ProductCategoryAttributeGroup $mapping): bool => $mapping->is_variant)
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function ancestorIds(ProductCategory $category): array
    {
        $ids = [];
        $visited = [];
        $current = $category;

        while ($current && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            array_unshift($ids, (int) $current->getKey());
            $current = $current->parent;
        }

        return $ids;
    }
}
