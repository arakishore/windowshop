<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductAttributeGroupValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductSkuGenerator
{
    public function shouldRegenerate(?string $sku): bool
    {
        $sku = trim((string) $sku);

        return $sku === '' || Str::of($sku)->upper()->contains('DEMO');
    }

    /**
     * @param array<int, array{group_id: int, value_id: int}> $values
     */
    public function forCombination(Product $product, array $values): string
    {
        return $this->build($product, $this->variantValueCodes($values));
    }

    public function forVariant(Product $product, ProductVariant $variant): ?string
    {
        $variant->loadMissing('attributes.value.group');

        $values = $variant->attributes
            ->filter(fn ($attribute): bool => $attribute->value !== null)
            ->map(fn ($attribute): array => [
                'group_id' => (int) $attribute->product_attribute_group_id,
                'value_id' => (int) $attribute->product_attribute_group_value_id,
            ])
            ->all();

        return $this->forCombination($product, $values);
    }

    /**
     * @param array<int, string> $variantCodes
     */
    private function build(Product $product, array $variantCodes): string
    {
        $product->loadMissing(['brand', 'category.parent', 'rootProductCategory']);

        $leafCategory = $product->category;
        $parentCategory = $leafCategory?->parent ?: $product->rootProductCategory;

        $parts = [
            $this->code($product->brand?->short_code, $product->brand?->name, 'GEN'),
            $this->code($parentCategory?->short_code, $parentCategory?->name, 'CAT'),
            $this->code($leafCategory?->short_code, $leafCategory?->name, 'PRD'),
            str_pad((string) $product->getKey(), 3, '0', STR_PAD_LEFT),
            ...$variantCodes,
        ];

        return implode('-', array_values(array_filter($parts)));
    }

    private function code(?string $shortCode, ?string $fallback, string $default): string
    {
        $value = trim((string) ($shortCode ?: $fallback ?: $default));
        $code = Str::of($value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->toString();

        return $code !== '' ? $code : $default;
    }

    /**
     * @param array<int, array{group_id: int, value_id: int}> $values
     * @return array<int, string>
     */
    private function variantValueCodes(array $values): array
    {
        $valueIds = collect($values)
            ->pluck('value_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->all();

        if ($valueIds === []) {
            return [];
        }

        /** @var Collection<int, ProductAttributeGroupValue> $attributeValues */
        $attributeValues = ProductAttributeGroupValue::query()
            ->with('group')
            ->whereIn('id', $valueIds)
            ->get()
            ->keyBy('id');

        return collect($values)
            ->map(fn (array $value): ?ProductAttributeGroupValue => $attributeValues[(int) $value['value_id']] ?? null)
            ->filter()
            ->sortBy([
                fn (ProductAttributeGroupValue $left, ProductAttributeGroupValue $right): int => $this->groupRank($left) <=> $this->groupRank($right),
                fn (ProductAttributeGroupValue $left, ProductAttributeGroupValue $right): int => ((int) $left->sort_order) <=> ((int) $right->sort_order),
                fn (ProductAttributeGroupValue $left, ProductAttributeGroupValue $right): int => ((int) $left->getKey()) <=> ((int) $right->getKey()),
            ])
            ->map(fn (ProductAttributeGroupValue $value): string => $this->code($value->short_code, $value->name, 'VAR'))
            ->values()
            ->all();
    }

    private function groupRank(ProductAttributeGroupValue $value): int
    {
        return match ($value->group?->code) {
            'size' => 10,
            'color' => 20,
            default => 100 + (int) ($value->group?->sort_order ?? 0),
        };
    }
}
