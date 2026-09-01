<?php

namespace App\Services\Promotion\Engine\Data;

class PromotionLineAdjustment
{
    public function __construct(
        public readonly PromotionLineInput $line,
        public readonly int $baseLineSubtotalCents,
        public readonly int $promotionDiscountCents,
        public readonly int $finalLineSubtotalCents,
        public readonly ?AppliedPromotion $winningPromotion = null,
        public readonly array $eligiblePromotions = [],
    ) {
    }

    public function hasPromotion(): bool
    {
        return $this->winningPromotion !== null && $this->promotionDiscountCents > 0;
    }

    public function hasPromotionParticipation(): bool
    {
        return $this->winningPromotion !== null;
    }

    public function discountAmount(): string
    {
        return $this->moneyFromCents($this->promotionDiscountCents);
    }

    public function finalSubtotal(): string
    {
        return $this->moneyFromCents($this->finalLineSubtotalCents);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        if (! $this->hasPromotionParticipation()) {
            return null;
        }

        return [
            'promotion' => $this->winningPromotion?->toMetadata(),
            'eligible_promotions' => $this->eligiblePromotions,
            'base_unit_price' => $this->line->baseUnitPrice,
            'base_line_subtotal' => $this->moneyFromCents($this->baseLineSubtotalCents),
            'discount_amount' => $this->discountAmount(),
            'final_line_subtotal_before_tax' => $this->finalSubtotal(),
        ];
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
