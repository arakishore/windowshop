<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantManagementService
{
    /**
     * Ensure every product has exactly one base sellable row.
     */
    public function ensureBaseVariant(Product $product, ?User $actor = null): ProductVariant
    {
        return DB::transaction(function () use ($product, $actor): ProductVariant {
            $defaultVariant = $product->variants()
                ->where('is_default', true)
                ->first();

            if ($defaultVariant instanceof ProductVariant) {
                return $defaultVariant;
            }

            $firstVariant = $product->variants()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($firstVariant instanceof ProductVariant) {
                $firstVariant->forceFill([
                    'is_default' => true,
                    'updated_by' => $actor?->getKey(),
                ])->save();

                return $firstVariant->refresh();
            }

            return ProductVariant::query()->create([
                'product_id' => $product->getKey(),
                'shop_id' => $product->shop_id,
                'sku' => null,
                'barcode' => null,
                'name' => $product->product_name,
                'mrp' => 0,
                'selling_price' => 0,
                'cost_price' => null,
                'stock_quantity' => 0,
                'low_stock_threshold' => 0,
                'is_default' => true,
                'sort_order' => 0,
                'status' => 'active',
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
            ]);
        });
    }

    /**
     * @param array<int|string, array<string, mixed>> $variants
     */
    public function updateVariants(Product $product, array $variants, User $actor): int
    {
        return DB::transaction(function () use ($product, $variants, $actor): int {
            $ids = collect(array_keys($variants))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->values();

            $ownedVariants = $this->variantsForProduct($product, $ids);
            $this->ensureAllVariantsBelongToProduct($ids, $ownedVariants);

            $updated = 0;

            foreach ($ownedVariants as $variant) {
                $changes = $this->normalizeRowChanges($variants[$variant->getKey()] ?? []);
                $this->assertPricingIsValid($variant, $changes);

                $variant->forceFill([
                    ...$changes,
                    'updated_by' => $actor->getKey(),
                ]);

                if ($variant->isDirty()) {
                    $variant->save();
                    $updated++;
                }
            }

            return $updated;
        });
    }

    /**
     * @param array<int, int> $variantIds
     * @param array<string, mixed> $changes
     */
    public function bulkUpdate(Product $product, array $variantIds, array $changes, User $actor, bool $applyAll = false): int
    {
        $changes = $this->normalizeBulkChanges($changes);

        if ($changes === []) {
            throw ValidationException::withMessages(['bulk' => 'Enter at least one value to update.']);
        }

        return DB::transaction(function () use ($product, $variantIds, $changes, $actor, $applyAll): int {
            $query = $product->variants()->getQuery();

            if (! $applyAll) {
                $ids = collect($variantIds)
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->values();

                if ($ids->isEmpty()) {
                    throw ValidationException::withMessages(['variant_ids' => 'Select at least one variant.']);
                }

                $query->whereIn('id', $ids);
            }

            /** @var EloquentCollection<int, ProductVariant> $variants */
            $variants = $query->orderBy('sort_order')->orderBy('id')->get();

            if (! $applyAll) {
                $this->ensureAllVariantsBelongToProduct(collect($variantIds)->map(fn ($id): int => (int) $id)->filter()->values(), $variants);
            }

            $updated = 0;

            foreach ($variants as $variant) {
                $this->assertPricingIsValid($variant, $changes);

                $variant->forceFill([
                    ...$changes,
                    'updated_by' => $actor->getKey(),
                ]);

                if ($variant->isDirty()) {
                    $variant->save();
                    $updated++;
                }
            }

            return $updated;
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, ProductVariant>
     */
    public function variantsForDisplay(Product $product, array $filters = []): Collection
    {
        $query = $product->variants()
            ->with(['attributes.group', 'attributes.value'])
            ->getQuery();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $attributeFilters = collect($filters['attributes'] ?? [])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        foreach ($attributeFilters as $groupId => $valueId) {
            $query->whereHas('attributes', function ($query) use ($groupId, $valueId): void {
                $query->where('product_attribute_group_id', (int) $groupId)
                    ->where('product_attribute_group_value_id', $valueId);
            });
        }

        return $query->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{group_id: int, group_name: string, values: Collection<int, array{id: int, name: string}>}>
     */
    public function filterOptions(Product $product): Collection
    {
        return $product->variants()
            ->with(['attributes.group', 'attributes.value'])
            ->get()
            ->flatMap(fn (ProductVariant $variant) => $variant->attributes)
            ->filter(fn ($attribute): bool => $attribute->group !== null && $attribute->value !== null)
            ->groupBy('product_attribute_group_id')
            ->map(function (Collection $attributes): array {
                $first = $attributes->first();

                return [
                    'group_id' => (int) $first->product_attribute_group_id,
                    'group_name' => $first->group->name,
                    'values' => $attributes
                        ->map(fn ($attribute): array => [
                            'id' => (int) $attribute->product_attribute_group_value_id,
                            'name' => $attribute->value->name,
                        ])
                        ->unique('id')
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values(),
                ];
            })
            ->sortBy('group_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function assertProductCanBePublished(Product $product): void
    {
        $this->ensureBaseVariant($product);

        $hasIncompletePricing = $product->variants()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('mrp', '<=', 0)
                    ->orWhere('selling_price', '<=', 0);
            })
            ->exists();

        if ($hasIncompletePricing) {
            throw ValidationException::withMessages([
                'status' => 'This product cannot be published because one or more active variants have incomplete pricing.',
            ]);
        }
    }

    /**
     * @param Collection<int, int> $ids
     * @return EloquentCollection<int, ProductVariant>
     */
    private function variantsForProduct(Product $product, Collection $ids): EloquentCollection
    {
        return $product->variants()
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @param Collection<int, int> $ids
     * @param EloquentCollection<int, ProductVariant> $variants
     */
    private function ensureAllVariantsBelongToProduct(Collection $ids, EloquentCollection $variants): void
    {
        if ($ids->unique()->count() !== $variants->count()) {
            throw ValidationException::withMessages(['variants' => 'One or more selected variants do not belong to this product.']);
        }
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function normalizeRowChanges(array $changes): array
    {
        return [
            'sku' => $this->nullableString($changes['sku'] ?? null),
            'barcode' => $this->nullableString($changes['barcode'] ?? null),
            'mrp' => $this->decimalValue($changes['mrp'] ?? 0),
            'selling_price' => $this->decimalValue($changes['selling_price'] ?? 0),
            'cost_price' => $this->nullableDecimalValue($changes['cost_price'] ?? null),
            'stock_quantity' => (int) ($changes['stock_quantity'] ?? 0),
            'low_stock_threshold' => (int) ($changes['low_stock_threshold'] ?? 0),
            'status' => $changes['status'] ?? 'active',
        ];
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function normalizeBulkChanges(array $changes): array
    {
        $normalized = [];

        foreach (['mrp', 'selling_price', 'cost_price'] as $field) {
            if (array_key_exists($field, $changes) && trim((string) $changes[$field]) !== '') {
                $normalized[$field] = $field === 'cost_price'
                    ? $this->nullableDecimalValue($changes[$field])
                    : $this->decimalValue($changes[$field]);
            }
        }

        foreach (['stock_quantity', 'low_stock_threshold'] as $field) {
            if (array_key_exists($field, $changes) && trim((string) $changes[$field]) !== '') {
                $normalized[$field] = (int) $changes[$field];
            }
        }

        if (array_key_exists('status', $changes) && in_array($changes['status'], ['active', 'inactive'], true)) {
            $normalized['status'] = $changes['status'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function assertPricingIsValid(ProductVariant $variant, array $changes): void
    {
        $mrp = array_key_exists('mrp', $changes) ? (float) $changes['mrp'] : (float) $variant->mrp;
        $sellingPrice = array_key_exists('selling_price', $changes) ? (float) $changes['selling_price'] : (float) $variant->selling_price;

        if ($sellingPrice > $mrp) {
            throw ValidationException::withMessages([
                'variants' => 'Selling price must be less than or equal to MRP.',
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function decimalValue(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDecimalValue(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->decimalValue($value);
    }
}
