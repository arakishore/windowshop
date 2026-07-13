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
        return ProductCategoryAttributeGroup::query()
            ->with(['group.values' => fn ($query) => $query->where('status', 'active')])
            ->where('root_product_category_id', $category->rootCategoryId())
            ->orderBy('sort_order')
            ->get()
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

}
