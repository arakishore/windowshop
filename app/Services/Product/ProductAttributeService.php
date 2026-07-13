<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductAttributeService
{
    /**
     * @return array<int, array<int, int>>
     */
    public function selectedValues(Product $product): array
    {
        return $product->attributes()
            ->select(['product_attribute_group_id', 'product_attribute_group_value_id'])
            ->get()
            ->groupBy('product_attribute_group_id')
            ->map(fn ($attributes) => $attributes
                ->pluck('product_attribute_group_value_id')
                ->map(fn ($valueId): int => (int) $valueId)
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param array<int, array<int, int>> $selectedAttributes
     */
    public function sync(Product $product, array $selectedAttributes): void
    {
        DB::transaction(function () use ($product, $selectedAttributes): void {
            $product->attributes()->delete();

            $rows = [];
            $now = now();

            foreach ($selectedAttributes as $groupId => $valueIds) {
                foreach (array_unique($valueIds) as $valueId) {
                    $rows[] = [
                        'product_id' => $product->getKey(),
                        'product_attribute_group_id' => (int) $groupId,
                        'product_attribute_group_value_id' => (int) $valueId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('product_attributes')->insert($rows);
            }
        });
    }
}
