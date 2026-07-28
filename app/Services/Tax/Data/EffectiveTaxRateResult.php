<?php

namespace App\Services\Tax\Data;

use Carbon\CarbonInterface;

final class EffectiveTaxRateResult
{
    /**
     * @param array<int, TaxComponentAmount> $components
     */
    public function __construct(
        public readonly int $taxRateId,
        public readonly string $taxRateName,
        public readonly string $totalRate,
        public readonly ?string $effectiveFrom,
        public readonly ?string $effectiveTo,
        public readonly CarbonInterface $effectiveAt,
        public readonly array $components,
    ) {
    }

    public function toArray(): array
    {
        return [
            'tax_rate_id' => $this->taxRateId,
            'tax_rate_name' => $this->taxRateName,
            'total_rate' => $this->totalRate,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'effective_at' => $this->effectiveAt->toDateTimeString(),
            'components' => array_map(fn (TaxComponentAmount $component): array => $component->toArray(), $this->components),
        ];
    }
}
