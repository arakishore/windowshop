<?php

namespace App\Services\Tax\Data;

use Carbon\CarbonInterface;

final class PricingResult
{
    public function __construct(
        public readonly TaxResolutionResult $resolution,
        public readonly ?EffectiveTaxRateResult $effectiveRate,
        public readonly TaxCalculationResult $calculation,
        public readonly CarbonInterface $calculatedAt,
    ) {
    }

    public function toArray(): array
    {
        $resolution = $this->resolution->toArray();
        $rate = $this->effectiveRate?->toArray() ?? [];
        $calculation = $this->calculation->toArray();

        return [
            'tax_enabled' => $resolution['tax_enabled'],
            'resolution_source' => $resolution['resolution_source'],
            'tax_class_id' => $resolution['tax_class_id'],
            'tax_class_code' => $resolution['tax_class_code'],
            'tax_class_name' => $resolution['tax_class_name'],
            'tax_rate_id' => $rate['tax_rate_id'] ?? null,
            'tax_rate_name' => $rate['tax_rate_name'] ?? null,
            'total_rate' => $rate['total_rate'] ?? $calculation['total_rate'],
            'price_mode' => $calculation['price_mode'],
            'unit_price' => $calculation['unit_price'],
            'quantity' => $calculation['quantity'],
            'line_subtotal' => $calculation['line_subtotal'],
            'discount_amount' => $calculation['discount_amount'],
            'taxable_amount' => $calculation['taxable_amount'],
            'tax_amount' => $calculation['tax_amount'],
            'component_amounts' => $calculation['component_amounts'],
            'line_total' => $calculation['line_total'],
            'effective_at' => $resolution['effective_at'],
            'calculated_at' => $this->calculatedAt->toDateTimeString(),
            'resolution' => $resolution,
            'effective_rate' => $this->effectiveRate?->toArray(),
            'calculation' => $calculation,
        ];
    }
}
