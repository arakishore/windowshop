<?php

namespace App\Services\Tax\Data;

final class MerchantTaxContext
{
    public function __construct(
        public readonly int $merchantId,
        public readonly bool $taxEnabled,
        public readonly bool $pricesIncludeTax,
        public readonly ?int $defaultTaxClassId,
        public readonly ?int $businessCountryId,
        public readonly bool $settingsFound,
    ) {
    }
}
