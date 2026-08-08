<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class NavigationService
{
    /**
     * @return EloquentCollection<int, ProductCategory>
     */
    public function getMarketplaceCategories(): EloquentCollection
    {
        return $this->activeRootTreeQuery()->get();
    }

    /**
     * @return EloquentCollection<int, ProductCategory>
     */
    public function getMerchantCategories(Shop $shop): EloquentCollection
    {
        $activeProductCategoryIds = Product::query()
            ->where('shop_id', $shop->getKey())
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('product_category_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($activeProductCategoryIds->isEmpty()) {
            return new EloquentCollection();
        }

        $roots = $this->activeRootTreeQuery()
            ->whereKey($shop->root_product_category_id)
            ->get();

        return $this->filterTreeToCategoryIds($roots, $activeProductCategoryIds);
    }

    private function activeRootTreeQuery()
    {
        return ProductCategory::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with([
                'children' => fn ($query) => $query
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->with([
                        'children' => fn ($query) => $query
                            ->where('status', 'active')
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ]),
            ]);
    }

    /**
     * @param EloquentCollection<int, ProductCategory> $roots
     * @param Collection<int, int> $categoryIds
     * @return EloquentCollection<int, ProductCategory>
     */
    private function filterTreeToCategoryIds(EloquentCollection $roots, Collection $categoryIds): EloquentCollection
    {
        return $roots
            ->map(fn (ProductCategory $root): ?ProductCategory => $this->filterCategory($root, $categoryIds))
            ->filter()
            ->values();
    }

    private function filterCategory(ProductCategory $category, Collection $categoryIds): ?ProductCategory
    {
        $loadedChildren = $category->relationLoaded('children') ? $category->children : collect();

        $children = $loadedChildren
            ->map(fn (ProductCategory $child): ?ProductCategory => $this->filterCategory($child, $categoryIds))
            ->filter()
            ->values();

        $isDirectlyUsed = $categoryIds->contains((int) $category->getKey());

        if (! $isDirectlyUsed && $children->isEmpty()) {
            return null;
        }

        $category->setRelation('children', new EloquentCollection($children->all()));

        return $category;
    }
}
