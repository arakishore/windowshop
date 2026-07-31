<?php

namespace App\Services\Order\Data;

final class OrderItemTaxSnapshot
{
    /**
     * @param array<int, OrderItemTaxComponentSnapshot> $components
     */
    public function __construct(
        public readonly bool $taxEnabled,
        public readonly string $resolutionSource,
        public readonly ?int $taxClassId,
        public readonly ?string $taxClassCode,
        public readonly ?string $taxClassName,
        public readonly ?int $taxRateId,
        public readonly ?string $taxRateName,
        public readonly ?string $totalRate,
        public readonly string $priceMode,
        public readonly string $lineSubtotal,
        public readonly string $discountAmount,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $lineTotal,
        public readonly array $components,
    ) {
    }

    public function toOrderItemAttributes(): array
    {
        return [
            'tax_enabled' => $this->taxEnabled,
            'tax_resolution_source' => $this->resolutionSource,
            'tax_class_id' => $this->taxClassId,
            'tax_class_code' => $this->taxClassCode,
            'tax_class_name' => $this->taxClassName,
            'tax_rate_id' => $this->taxRateId,
            'tax_rate_name' => $this->taxRateName,
            'tax_rate' => $this->totalRate,
            'price_mode' => $this->priceMode,
            'taxable_amount' => $this->taxableAmount,
            'line_subtotal' => $this->lineSubtotal,
            'line_discount' => $this->discountAmount,
            'line_tax' => $this->taxAmount,
            'line_total' => $this->lineTotal,
        ];
    }

    public function componentAttributes(): array
    {
        return array_map(
            fn (OrderItemTaxComponentSnapshot $component): array => $component->toAttributes(),
            $this->components,
        );
    }
}
