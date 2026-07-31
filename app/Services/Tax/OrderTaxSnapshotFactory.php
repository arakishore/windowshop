<?php

namespace App\Services\Tax;

use App\Services\Order\Data\OrderItemTaxComponentSnapshot;
use App\Services\Order\Data\OrderItemTaxSnapshot;
use App\Services\Tax\Data\PricingResult;
use App\Services\Tax\Data\TaxComponentAmount;

class OrderTaxSnapshotFactory
{
    public function fromPricingResult(PricingResult $pricingResult): OrderItemTaxSnapshot
    {
        return new OrderItemTaxSnapshot(
            taxEnabled: $pricingResult->calculation->taxEnabled,
            resolutionSource: $pricingResult->resolution->resolutionSource,
            taxClassId: $pricingResult->resolution->taxClassId,
            taxClassCode: $pricingResult->resolution->taxClassCode,
            taxClassName: $pricingResult->resolution->taxClassName,
            taxRateId: $pricingResult->effectiveRate?->taxRateId,
            taxRateName: $pricingResult->effectiveRate?->taxRateName,
            totalRate: $pricingResult->effectiveRate?->totalRate ?? $pricingResult->calculation->totalRate,
            priceMode: $pricingResult->calculation->priceMode,
            lineSubtotal: $pricingResult->calculation->lineSubtotal,
            discountAmount: $pricingResult->calculation->discountAmount,
            taxableAmount: $pricingResult->calculation->taxableAmount,
            taxAmount: $pricingResult->calculation->taxAmount,
            lineTotal: $pricingResult->calculation->lineTotal,
            components: $this->components($pricingResult),
        );
    }

    /**
     * @return array<int, OrderItemTaxComponentSnapshot>
     */
    private function components(PricingResult $pricingResult): array
    {
        return array_map(
            fn (TaxComponentAmount $component, int $index): OrderItemTaxComponentSnapshot => new OrderItemTaxComponentSnapshot(
                taxComponentId: $component->componentId,
                componentCode: $component->code,
                componentName: $component->name,
                jurisdictionType: $component->jurisdictionType,
                rate: $component->rate,
                amount: $component->amount,
                sortOrder: $index,
            ),
            $pricingResult->calculation->componentAmounts,
            array_keys($pricingResult->calculation->componentAmounts),
        );
    }
}
