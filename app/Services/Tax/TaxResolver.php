<?php

namespace App\Services\Tax;

use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\TaxClass;
use App\Services\Tax\Data\MerchantTaxContext;
use App\Services\Tax\Data\TaxResolutionResult;
use App\Services\Tax\Exceptions\TaxConfigurationException;
use Carbon\CarbonInterface;

class TaxResolver
{
    public function merchantTaxContext(MerchantProfile $merchant): MerchantTaxContext
    {
        $setting = $merchant->taxSetting()->first();

        return new MerchantTaxContext(
            merchantId: (int) $merchant->getKey(),
            taxEnabled: (bool) $setting?->tax_enabled,
            pricesIncludeTax: (bool) ($setting?->prices_include_tax ?? true),
            defaultTaxClassId: $setting?->default_tax_class_id ? (int) $setting->default_tax_class_id : null,
            businessCountryId: $merchant->businessAddress()->value('country_id') ?: null,
            settingsFound: (bool) $setting,
        );
    }

    public function resolve(
        Product $product,
        MerchantProfile $merchant,
        CarbonInterface $effectiveAt,
        ?MerchantTaxContext $context = null,
    ): TaxResolutionResult {
        $context ??= $this->merchantTaxContext($merchant);

        if (! $context->settingsFound || ! $context->taxEnabled) {
            return TaxResolutionResult::noTax(
                TaxResolutionResult::SOURCE_TAX_DISABLED,
                $effectiveAt,
                'Merchant tax settings are missing or tax is disabled.',
            );
        }

        if ($product->tax_mode === 'exempt') {
            return TaxResolutionResult::noTax(
                TaxResolutionResult::SOURCE_PRODUCT_EXEMPT,
                $effectiveAt,
                'Product is marked tax exempt.',
            );
        }

        if ($product->tax_mode === 'override') {
            $taxClass = $this->validatedTaxClass($product->tax_class_id, $context, 'product override');

            return TaxResolutionResult::resolved(
                TaxResolutionResult::SOURCE_PRODUCT_OVERRIDE,
                taxClassId: (int) $taxClass->getKey(),
                taxClassCode: $taxClass->code,
                taxClassName: $taxClass->name,
                effectiveAt: $effectiveAt,
            );
        }

        $categoryDefaultId = $product->relationLoaded('category')
            ? $product->category?->default_tax_class_id
            : $product->category()->value('default_tax_class_id');

        if ($categoryDefaultId) {
            $taxClass = $this->validatedTaxClass((int) $categoryDefaultId, $context, 'category default');

            return TaxResolutionResult::resolved(
                TaxResolutionResult::SOURCE_CATEGORY_DEFAULT,
                taxClassId: (int) $taxClass->getKey(),
                taxClassCode: $taxClass->code,
                taxClassName: $taxClass->name,
                effectiveAt: $effectiveAt,
            );
        }

        if ($context->defaultTaxClassId) {
            $taxClass = $this->validatedTaxClass($context->defaultTaxClassId, $context, 'merchant default');

            return TaxResolutionResult::resolved(
                TaxResolutionResult::SOURCE_MERCHANT_DEFAULT,
                taxClassId: (int) $taxClass->getKey(),
                taxClassCode: $taxClass->code,
                taxClassName: $taxClass->name,
                effectiveAt: $effectiveAt,
            );
        }

        return TaxResolutionResult::noTax(
            TaxResolutionResult::SOURCE_NO_TAX_CLASS,
            $effectiveAt,
            'No product, category, or merchant tax class is configured.',
        );
    }

    private function validatedTaxClass(?int $taxClassId, MerchantTaxContext $context, string $sourceLabel): TaxClass
    {
        if (! $taxClassId) {
            throw new TaxConfigurationException("Missing tax class for {$sourceLabel}.");
        }

        $taxClass = TaxClass::withTrashed()->find($taxClassId);

        if (! $taxClass) {
            throw new TaxConfigurationException("Tax class for {$sourceLabel} does not exist.");
        }

        if ($taxClass->trashed()) {
            throw new TaxConfigurationException("Tax class {$taxClass->code} for {$sourceLabel} is deleted.");
        }

        if ($taxClass->status !== TaxClass::STATUS_ACTIVE) {
            throw new TaxConfigurationException("Tax class {$taxClass->code} for {$sourceLabel} is not active.");
        }

        if (! $context->businessCountryId) {
            throw new TaxConfigurationException('Merchant business country is required to resolve tax.');
        }

        if ((int) $taxClass->country_id !== (int) $context->businessCountryId) {
            throw new TaxConfigurationException("Tax class {$taxClass->code} does not match the merchant business country.");
        }

        return $taxClass;
    }
}
