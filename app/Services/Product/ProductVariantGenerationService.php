<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductCategoryAttributeGroup;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantGenerationService
{
    public function __construct(
        private readonly ProductAttributeConfigurationService $attributeConfigurationService,
        private readonly ProductVariantManagementService $variantManagementService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Product $product): array
    {
        $variantGroups = $this->variantGroups($product);
        $selectedByGroup = $this->selectedVariantValues($product, $variantGroups);
        $errors = $this->validationErrors($variantGroups, $selectedByGroup);
        $combinations = $errors === [] ? $this->cartesianCombinations($variantGroups, $selectedByGroup) : [];
        $existingKeys = $this->existingCombinationKeys($product, $variantGroups);
        $currentKeys = collect($combinations)->pluck('key')->all();

        return [
            'variant_groups' => $variantGroups,
            'combinations' => $combinations,
            'total' => count($combinations),
            'selected_variant_value_count' => collect($selectedByGroup)->sum(fn (Collection $values): int => $values->count()),
            'new_count' => collect($combinations)->reject(fn (array $combination): bool => isset($existingKeys[$combination['key']]))->count(),
            'existing_count' => collect($combinations)->filter(fn (array $combination): bool => isset($existingKeys[$combination['key']]))->count(),
            'stale_existing_count' => collect(array_keys($existingKeys))->reject(fn (string $key): bool => in_array($key, $currentKeys, true))->count(),
            'errors' => $errors,
            'limit' => $this->combinationLimit(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function generate(Product $product, User $actor): array
    {
        $preview = $this->preview($product);

        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages(['variants' => $preview['errors']]);
        }

        if ($preview['total'] === 0) {
            throw ValidationException::withMessages(['variants' => 'No variant combinations are available to generate.']);
        }

        if ($preview['total'] > $preview['limit']) {
            throw ValidationException::withMessages([
                'variants' => "This selection would create {$preview['total']} variants. The maximum allowed is {$preview['limit']}.",
            ]);
        }

        return DB::transaction(function () use ($product, $actor, $preview): array {
            $product->loadMissing('shop');
            $baseVariant = $this->variantManagementService->ensureBaseVariant($product, $actor);
            $existingKeys = $this->existingCombinationKeys($product, $preview['variant_groups']);
            $nextSortOrder = ((int) $product->variants()->max('sort_order')) + 1;
            $missingCombinations = collect($preview['combinations'])
                ->reject(fn (array $combination): bool => isset($existingKeys[$combination['key']]))
                ->values();
            $variantDefaults = $missingCombinations->isNotEmpty()
                ? $this->resolveVariantDefaults($product)
                : null;
            $created = 0;

            if ($missingCombinations->isNotEmpty() && $baseVariant->attributes()->count() === 0) {
                $firstCombination = $missingCombinations->shift();

                $baseVariant->forceFill([
                    'sku' => $baseVariant->sku ?: $this->uniqueSku($product, $firstCombination, $baseVariant),
                    'barcode' => $baseVariant->barcode,
                    'name' => $firstCombination['name'],
                    'mrp' => $variantDefaults['mrp'],
                    'selling_price' => $variantDefaults['selling_price'],
                    'cost_price' => $variantDefaults['cost_price'],
                    'stock_quantity' => 0,
                    'low_stock_threshold' => $variantDefaults['low_stock_threshold'],
                    'is_default' => true,
                    'sort_order' => 0,
                    'status' => $variantDefaults['status'],
                    'updated_by' => $actor->getKey(),
                ])->save();

                $product->variants()
                    ->whereKeyNot($baseVariant->getKey())
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                        'updated_by' => $actor->getKey(),
                        'updated_at' => now(),
                    ]);

                $this->syncVariantAttributes($baseVariant, $firstCombination);
                $created++;
            }

            foreach ($missingCombinations as $combination) {
                $variant = ProductVariant::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $product->getKey(),
                    'shop_id' => $product->shop_id,
                    'sku' => $this->uniqueSku($product, $combination),
                    'barcode' => null,
                    'name' => $combination['name'],
                    'mrp' => $variantDefaults['mrp'],
                    'selling_price' => $variantDefaults['selling_price'],
                    'cost_price' => $variantDefaults['cost_price'],
                    'stock_quantity' => 0,
                    'low_stock_threshold' => $variantDefaults['low_stock_threshold'],
                    'is_default' => false,
                    'sort_order' => $nextSortOrder++,
                    'status' => $variantDefaults['status'],
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);

                $this->syncVariantAttributes($variant, $combination);

                $created++;
            }

            $this->variantManagementService->ensureDefaultVariant($product, $actor);

            return [
                'created_count' => $created,
                'skipped_existing_count' => (int) $preview['existing_count'],
                'total_current_variants' => $product->variants()->count(),
            ];
        });
    }

    /**
     * @return Collection<int, ProductCategoryAttributeGroup>
     */
    private function variantGroups(Product $product): Collection
    {
        if (! $product->category) {
            return collect();
        }

        return $this->attributeConfigurationService
            ->variantGroupsForCategory($product->category)
            ->filter(fn (ProductCategoryAttributeGroup $mapping): bool => $mapping->group !== null)
            ->values();
    }

    /**
     * @param Collection<int, ProductCategoryAttributeGroup> $variantGroups
     * @return array<int, Collection<int, object>>
     */
    private function selectedVariantValues(Product $product, Collection $variantGroups): array
    {
        $variantGroupIds = $variantGroups
            ->pluck('product_attribute_group_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($variantGroupIds === []) {
            return [];
        }

        return $product->attributes()
            ->with('value')
            ->whereIn('product_attribute_group_id', $variantGroupIds)
            ->get()
            ->filter(fn ($attribute): bool => $attribute->value !== null
                && (int) $attribute->value->product_attribute_group_id === (int) $attribute->product_attribute_group_id)
            ->groupBy('product_attribute_group_id')
            ->map(fn ($attributes) => $attributes
                ->pluck('value')
                ->unique('id')
                ->sortBy('sort_order')
                ->values())
            ->all();
    }

    /**
     * @param Collection<int, ProductCategoryAttributeGroup> $variantGroups
     * @param array<int, Collection<int, object>> $selectedByGroup
     * @return array<int, string>
     */
    private function validationErrors(Collection $variantGroups, array $selectedByGroup): array
    {
        if ($variantGroups->isEmpty()) {
            return ['No variant attributes are configured for this product category.'];
        }

        $errors = [];

        foreach ($variantGroups as $mapping) {
            $groupId = (int) $mapping->product_attribute_group_id;

            if ($mapping->is_required && empty($selectedByGroup[$groupId])) {
                $errors[] = "{$mapping->group->name} is required before variants can be generated.";
            }
        }

        return $errors;
    }

    /**
     * @param Collection<int, ProductCategoryAttributeGroup> $variantGroups
     * @param array<int, Collection<int, object>> $selectedByGroup
     * @return array<int, array{key: string, name: string, values: array<int, array{group_id: int, group_name: string, value_id: int, value_name: string}>}>
     */
    private function cartesianCombinations(Collection $variantGroups, array $selectedByGroup): array
    {
        $sets = [];

        foreach ($variantGroups as $mapping) {
            $groupId = (int) $mapping->product_attribute_group_id;
            $values = $selectedByGroup[$groupId] ?? collect();

            if ($values->isEmpty()) {
                continue;
            }

            $sets[] = $values->map(fn ($value): array => [
                'group_id' => $groupId,
                'group_name' => $mapping->group->name,
                'value_id' => (int) $value->getKey(),
                'value_name' => $value->name,
            ])->all();
        }

        if ($sets === []) {
            return [];
        }

        $combinations = [[]];

        foreach ($sets as $set) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($set as $value) {
                    $next[] = [...$combination, $value];
                }
            }

            $combinations = $next;
        }

        return collect($combinations)
            ->map(fn (array $values): array => [
                'key' => $this->combinationKey($values),
                'name' => collect($values)->pluck('value_name')->implode(' / '),
                'values' => $values,
            ])
            ->all();
    }

    /**
     * @param Collection<int, ProductCategoryAttributeGroup> $variantGroups
     * @return array<string, int>
     */
    private function existingCombinationKeys(Product $product, Collection $variantGroups): array
    {
        $variantGroupIds = $variantGroups
            ->pluck('product_attribute_group_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($variantGroupIds === []) {
            return [];
        }

        return $product->variants()
            ->with(['attributes' => fn ($query) => $query->whereIn('product_attribute_group_id', $variantGroupIds)])
            ->get()
            ->mapWithKeys(function (ProductVariant $variant): array {
                if ($variant->attributes->isEmpty()) {
                    return [];
                }

                $values = $variant->attributes
                    ->map(fn ($attribute): array => [
                        'group_id' => (int) $attribute->product_attribute_group_id,
                        'value_id' => (int) $attribute->product_attribute_group_value_id,
                    ])
                    ->all();

                return [$this->combinationKey($values) => $variant->getKey()];
            })
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $values
     */
    private function combinationKey(array $values): string
    {
        return collect($values)
            ->map(fn (array $value): string => ((int) $value['group_id']).':'.((int) $value['value_id']))
            ->sort()
            ->implode('|');
    }

    /**
     * @return array{mrp: mixed, selling_price: mixed, cost_price: mixed, low_stock_threshold: int, status: string}
     */
    private function resolveVariantDefaults(Product $product): array
    {
        $baseVariant = $this->baseVariant($product);

        return [
            'mrp' => $baseVariant?->mrp ?? 0,
            'selling_price' => $baseVariant?->selling_price ?? 0,
            'cost_price' => $baseVariant?->cost_price,
            'low_stock_threshold' => (int) ($baseVariant?->low_stock_threshold ?? 0),
            'status' => $baseVariant?->status ?? 'active',
        ];
    }

    private function baseVariant(Product $product): ?ProductVariant
    {
        $baseVariant = $product->variants()
            ->where('is_default', true)
            ->first();

        return $baseVariant ?? $product->variants()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param array{key: string, name: string, values: array<int, array{group_id: int, group_name: string, value_id: int, value_name: string}>} $combination
     */
    private function uniqueSku(Product $product, array $combination, ?ProductVariant $ignoreVariant = null): string
    {
        $base = Str::upper(Str::slug($product->slug ?: $product->product_name, '-'));
        $suffix = Str::upper(Str::slug(collect($combination['values'])->pluck('value_name')->implode('-'), '-'));
        $skuBase = Str::limit(trim("{$base}-{$suffix}", '-'), 80, '');
        $sku = $skuBase !== '' ? $skuBase : 'VARIANT-'.$product->getKey();
        $counter = 2;

        while (ProductVariant::query()
            ->where('shop_id', $product->shop_id)
            ->where('sku', $sku)
            ->when($ignoreVariant, fn ($query) => $query->whereKeyNot($ignoreVariant->getKey()))
            ->exists()) {
            $sku = Str::limit($skuBase, 72, '').'-'.$counter++;
        }

        return $sku;
    }

    private function combinationLimit(): int
    {
        return max(1, (int) config('products.max_variant_combinations', 100));
    }

    /**
     * @param array{key: string, name: string, values: array<int, array{group_id: int, group_name: string, value_id: int, value_name: string}>} $combination
     */
    private function syncVariantAttributes(ProductVariant $variant, array $combination): void
    {
        foreach ($combination['values'] as $value) {
            ProductVariantAttribute::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->getKey(),
                    'product_attribute_group_id' => $value['group_id'],
                ],
                [
                    'product_attribute_group_value_id' => $value['value_id'],
                    'updated_at' => now(),
                ],
            );
        }
    }
}
