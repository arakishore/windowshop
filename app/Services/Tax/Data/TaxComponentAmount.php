<?php

namespace App\Services\Tax\Data;

final class TaxComponentAmount
{
    public function __construct(
        public readonly int $componentId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $rate,
        public readonly string $amount,
        public readonly ?string $jurisdictionType = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'component_id' => $this->componentId,
            'code' => $this->code,
            'name' => $this->name,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'jurisdiction_type' => $this->jurisdictionType,
        ];
    }
}
