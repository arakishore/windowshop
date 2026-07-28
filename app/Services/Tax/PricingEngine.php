<?php

namespace App\Services\Tax;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Services\Tax\Data\PricingResult;
use Carbon\CarbonInterface;

class PricingEngine
{
    public function __construct(
        private readonly TaxResolver $taxResolver,
        private readonly EffectiveTaxRateResolver $rateResolver,
        private readonly TaxCalculator $calculator,
    ) {
    }

    public function calculateProductLine(
        Product $product,
        MerchantProfile $merchant,
        float|string|int $unitPrice,
        float|string|int $quantity,
        CarbonInterface $effectiveAt,
        float|string|int $discountAmount = 0,
    ): PricingResult {
        $context = $this->taxResolver->merchantTaxContext($merchant);
        $resolution = $this->taxResolver->resolve($product, $merchant, $effectiveAt, $context);
        $effectiveRate = $this->rateResolver->resolve($resolution, $effectiveAt);

        return new PricingResult(
            resolution: $resolution,
            effectiveRate: $effectiveRate,
            calculation: $this->calculator->calculateLine(
                unitPrice: $unitPrice,
                quantity: $quantity,
                pricesIncludeTax: $context->pricesIncludeTax,
                effectiveRate: $effectiveRate,
                discountAmount: $discountAmount,
            ),
            calculatedAt: $effectiveAt,
        );
    }
}
