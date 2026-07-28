<?php

namespace App\Services\Tax\Data;

use Carbon\CarbonInterface;

final class TaxResolutionResult
{
    public const SOURCE_TAX_DISABLED = 'tax_disabled';
    public const SOURCE_PRODUCT_EXEMPT = 'product_exempt';
    public const SOURCE_PRODUCT_OVERRIDE = 'product_override';
    public const SOURCE_CATEGORY_DEFAULT = 'category_default';
    public const SOURCE_MERCHANT_DEFAULT = 'merchant_default';
    public const SOURCE_NO_TAX_CLASS = 'no_tax_class';

    public function __construct(
        public readonly bool $taxEnabled,
        public readonly string $resolutionSource,
        public readonly ?int $taxClassId,
        public readonly ?string $taxClassCode,
        public readonly ?string $taxClassName,
        public readonly CarbonInterface $effectiveAt,
        public readonly ?string $diagnostic = null,
    ) {
    }

    public static function noTax(string $source, CarbonInterface $effectiveAt, ?string $diagnostic = null): self
    {
        return new self(
            taxEnabled: false,
            resolutionSource: $source,
            taxClassId: null,
            taxClassCode: null,
            taxClassName: null,
            effectiveAt: $effectiveAt,
            diagnostic: $diagnostic,
        );
    }

    public static function resolved(
        string $source,
        int $taxClassId,
        string $taxClassCode,
        string $taxClassName,
        CarbonInterface $effectiveAt,
    ): self
    {
        return new self(
            taxEnabled: true,
            resolutionSource: $source,
            taxClassId: $taxClassId,
            taxClassCode: $taxClassCode,
            taxClassName: $taxClassName,
            effectiveAt: $effectiveAt,
        );
    }

    public function toArray(): array
    {
        return [
            'tax_enabled' => $this->taxEnabled,
            'resolution_source' => $this->resolutionSource,
            'tax_class_id' => $this->taxClassId,
            'tax_class_code' => $this->taxClassCode,
            'tax_class_name' => $this->taxClassName,
            'effective_at' => $this->effectiveAt->toDateTimeString(),
            'diagnostic' => $this->diagnostic,
        ];
    }
}
