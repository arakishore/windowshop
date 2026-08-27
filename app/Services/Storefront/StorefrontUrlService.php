<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

class StorefrontUrlService
{
    public function product(Product $product): string
    {
        $categoryPath = $this->categoryPath($product->category);

        if ($categoryPath === '') {
            return route('storefront.product.show', $product->slug);
        }

        return url('/category/'.$categoryPath.'/products/'.$product->slug);
    }

    public function category(ProductCategory $category): string
    {
        $path = $this->categoryPath($category);

        if (! str_contains($path, '/')) {
            return route('storefront.category.show', $category->slug);
        }

        return url('/category/'.$path);
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function categoryBreadcrumbs(?ProductCategory $category): array
    {
        return $this->categoryAncestors($category)
            ->map(fn (ProductCategory $category): array => [
                'name' => $category->name,
                'url' => $this->category($category),
            ])
            ->values()
            ->all();
    }

    public function categoryPath(?ProductCategory $category): string
    {
        return $this->categoryAncestors($category)
            ->pluck('slug')
            ->filter()
            ->implode('/');
    }

    public function categoryMatchesPath(ProductCategory $category, string $path): bool
    {
        return trim($path, '/') === $this->categoryPath($category);
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function categoryAncestors(?ProductCategory $category): Collection
    {
        if (! $category instanceof ProductCategory) {
            return collect();
        }

        $items = collect();
        $visited = [];
        $current = $category;

        while ($current instanceof ProductCategory && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            $items->prepend($current);

            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $current = $current->parent;
        }

        return $items->values();
    }
}
