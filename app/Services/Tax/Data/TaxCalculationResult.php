<?php

namespace App\Services\Tax\Data;

final class TaxCalculationResult
{
    /**
     * @param array<int, TaxComponentAmount> $componentAmounts
     */
    public function __construct(
        public readonly bool $taxEnabled,
        public readonly string $priceMode,
        public readonly string $unitPrice,
        public readonly string $quantity,
        public readonly string $lineSubtotal,
        public readonly string $discountAmount,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $lineTotal,
        public readonly ?string $totalRate,
        public readonly array $componentAmounts,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tax_enabled' => $this->taxEnabled,
            'price_mode' => $this->priceMode,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'line_subtotal' => $this->lineSubtotal,
            'discount_amount' => $this->discountAmount,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
            'line_total' => $this->lineTotal,
            'total_rate' => $this->totalRate,
            'component_amounts' => array_map(fn (TaxComponentAmount $component): array => $component->toArray(), $this->componentAmounts),
        ];
    }
}
