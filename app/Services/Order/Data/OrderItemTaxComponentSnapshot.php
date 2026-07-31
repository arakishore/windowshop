<?php

namespace App\Services\Order\Data;

final class OrderItemTaxComponentSnapshot
{
    public function __construct(
        public readonly ?int $taxComponentId,
        public readonly string $componentCode,
        public readonly string $componentName,
        public readonly ?string $jurisdictionType,
        public readonly string $rate,
        public readonly string $amount,
        public readonly int $sortOrder,
    ) {
    }

    public function toAttributes(): array
    {
        return [
            'tax_component_id' => $this->taxComponentId,
            'component_code' => $this->componentCode,
            'component_name' => $this->componentName,
            'jurisdiction_type' => $this->jurisdictionType,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'sort_order' => $this->sortOrder,
        ];
    }
}
