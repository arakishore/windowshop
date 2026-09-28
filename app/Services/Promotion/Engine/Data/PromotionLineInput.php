<?php

namespace App\Services\Promotion\Engine\Data;

class PromotionLineInput
{
    /**
     * @param array<int, int> $categoryIds
     * @param array<int, int> $collectionIds
     */
    public function __construct(
        public readonly int $variantId,
        public readonly int $productId,
        public readonly int $shopId,
        public readonly string $quantity,
        public readonly string $baseUnitPrice,
        public readonly array $categoryIds = [],
        public readonly ?int $brandId = null,
        public readonly array $collectionIds = [],
    ) {
    }
}
