<?php

namespace App\Services\Promotion\Engine\Data;

class GeneratedPromotionGift
{
    public function __construct(
        public readonly AppliedPromotion $promotion,
        public readonly int $productId,
        public readonly int $variantId,
        public readonly string $productName,
        public readonly ?string $productImage,
        public readonly string $variantName,
        public readonly ?string $sku,
        public readonly ?string $barcode,
        public readonly string $quantity,
        public readonly string $unitPrice,
        public readonly int $baseLineSubtotalCents,
        public readonly int $promotionDiscountCents,
        public readonly int $finalLineSubtotalCents,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [
            'promotion' => $this->promotion->toMetadata(),
            'eligible_promotions' => [$this->promotion->toMetadata()],
            'base_unit_price' => $this->unitPrice,
            'base_line_subtotal' => $this->moneyFromCents($this->baseLineSubtotalCents),
            'discount_amount' => $this->moneyFromCents($this->promotionDiscountCents),
            'final_line_subtotal_before_tax' => $this->moneyFromCents($this->finalLineSubtotalCents),
        ];
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
