<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use App\Models\TaxClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ProductCategoryDefaultTaxService
{
    public function activeTaxClasses(): Collection
    {
        return TaxClass::query()
            ->active()
            ->orderBy('code')
            ->orderBy('name')
            ->get();
    }

    public function taxClassesForForm(?ProductCategory $category = null): Collection
    {
        $taxClasses = $this->activeTaxClasses();
        $currentId = $category?->default_tax_class_id;

        if ($currentId === null || $taxClasses->contains('id', $currentId)) {
            return $taxClasses;
        }

        $current = TaxClass::withTrashed()->find($currentId);

        if (! $current instanceof TaxClass) {
            return $taxClasses;
        }

        return $taxClasses
            ->push($current->setAttribute('is_current_unavailable', true))
            ->sortBy([
                ['code', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    public function canStoreDefaultTaxClass(?int $parentId, ?ProductCategory $category = null): bool
    {
        if ($parentId === null) {
            return false;
        }

        if (! $category instanceof ProductCategory) {
            return true;
        }

        return ! $category->children()
            ->whereNull('deleted_at')
            ->exists();
    }

    public function defaultTaxClassIdForSave(array $data, ?int $parentId, ?ProductCategory $category = null): ?int
    {
        if (! $this->canStoreDefaultTaxClass($parentId, $category)) {
            return null;
        }

        return isset($data['default_tax_class_id']) ? (int) $data['default_tax_class_id'] : null;
    }

    public function clearDefaultTaxClassForGroupingCategory(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        ProductCategory::query()
            ->whereKey($categoryId)
            ->whereNotNull('default_tax_class_id')
            ->update([
                'default_tax_class_id' => null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }
}
