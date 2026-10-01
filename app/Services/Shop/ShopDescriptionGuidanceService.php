<?php

namespace App\Services\Shop;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

class ShopDescriptionGuidanceService
{
    /**
     * @param Collection<int, ProductCategory> $shopTypes
     * @return array{descriptionSuggestionKeys: array<int, string>, descriptionSuggestions: array<string, array<string, string>>}
     */
    public function forShopTypes(Collection $shopTypes): array
    {
        $configuredSuggestions = config('shop_description_guidance', []);
        $descriptionSuggestionKeys = $shopTypes->mapWithKeys(function (ProductCategory $shopType): array {
            return [
                (int) $shopType->getKey() => $shopType->status === 'active'
                    ? $this->suggestionKey($shopType)
                    : '',
            ];
        });

        return [
            'descriptionSuggestionKeys' => $descriptionSuggestionKeys->all(),
            'descriptionSuggestions' => $shopTypes
                ->mapWithKeys(function (ProductCategory $shopType) use ($configuredSuggestions, $descriptionSuggestionKeys): array {
                    $key = $descriptionSuggestionKeys->get((int) $shopType->getKey());
                    $suggestion = $key ? ($configuredSuggestions[$key] ?? null) : null;

                    return is_array($suggestion) ? [$key => $suggestion] : [];
                })
                ->all(),
        ];
    }

    private function suggestionKey(ProductCategory $shopType): string
    {
        $slug = (string) $shopType->slug;
        $idSuffix = '-'.$shopType->getKey();

        return str_ends_with($slug, $idSuffix)
            ? substr($slug, 0, -strlen($idSuffix))
            : $slug;
    }
}
