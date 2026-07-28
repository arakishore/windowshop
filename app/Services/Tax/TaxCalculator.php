<?php

namespace App\Services\Tax;

use App\Services\Tax\Data\EffectiveTaxRateResult;
use App\Services\Tax\Data\TaxCalculationResult;
use App\Services\Tax\Data\TaxComponentAmount;
use App\Services\Tax\Exceptions\TaxCalculationInputException;

class TaxCalculator
{
    public const PRICE_MODE_INCLUSIVE = 'inclusive';
    public const PRICE_MODE_EXCLUSIVE = 'exclusive';

    public function calculateLine(
        float|string|int $unitPrice,
        float|string|int $quantity,
        bool $pricesIncludeTax,
        ?EffectiveTaxRateResult $effectiveRate,
        float|string|int $discountAmount = 0,
    ): TaxCalculationResult {
        $priceMode = $pricesIncludeTax ? self::PRICE_MODE_INCLUSIVE : self::PRICE_MODE_EXCLUSIVE;
        $unitPriceCents = $this->moneyUnits($unitPrice, 'unit price');
        $quantityUnits = $this->quantityUnits($quantity);
        $subtotalCents = $this->roundDiv($unitPriceCents * $quantityUnits, 10000);
        $discountCents = min($this->moneyUnits($discountAmount, 'discount amount'), $subtotalCents);
        $afterDiscountCents = max(0, $subtotalCents - $discountCents);

        if (! $effectiveRate) {
            return new TaxCalculationResult(
                taxEnabled: false,
                priceMode: $priceMode,
                unitPrice: $this->formatUnits($unitPriceCents, 2),
                quantity: $this->formatQuantity($quantityUnits),
                lineSubtotal: $this->formatUnits($subtotalCents, 2),
                discountAmount: $this->formatUnits($discountCents, 2),
                taxableAmount: $this->formatUnits($afterDiscountCents, 2),
                taxAmount: '0.00',
                lineTotal: $this->formatUnits($afterDiscountCents, 2),
                totalRate: null,
                componentAmounts: [],
            );
        }

        $rateUnits = $this->rateUnits($effectiveRate->totalRate);

        if ($pricesIncludeTax && $rateUnits > 0) {
            $lineTotalCents = $afterDiscountCents;
            $taxableCents = $this->roundDiv($afterDiscountCents * 1000000, 1000000 + $rateUnits);
            $taxCents = $lineTotalCents - $taxableCents;
        } else {
            $taxableCents = $afterDiscountCents;
            $taxCents = $this->roundDiv($taxableCents * $rateUnits, 1000000);
            $lineTotalCents = $taxableCents + $taxCents;
        }

        return new TaxCalculationResult(
            taxEnabled: true,
            priceMode: $priceMode,
            unitPrice: $this->formatUnits($unitPriceCents, 2),
            quantity: $this->formatQuantity($quantityUnits),
            lineSubtotal: $this->formatUnits($subtotalCents, 2),
            discountAmount: $this->formatUnits($discountCents, 2),
            taxableAmount: $this->formatUnits($taxableCents, 2),
            taxAmount: $this->formatUnits($taxCents, 2),
            lineTotal: $this->formatUnits($lineTotalCents, 2),
            totalRate: $effectiveRate->totalRate,
            componentAmounts: $this->componentAmounts($effectiveRate, $taxableCents, $taxCents),
        );
    }

    /**
     * @return array<int, TaxComponentAmount>
     */
    private function componentAmounts(EffectiveTaxRateResult $effectiveRate, int $taxableCents, int $taxCents): array
    {
        $components = $effectiveRate->components;
        $amounts = collect($components)
            ->map(fn (TaxComponentAmount $component): int => $this->roundDiv($taxableCents * $this->rateUnits($component->rate), 1000000))
            ->all();
        $remainder = $taxCents - array_sum($amounts);

        if ($remainder !== 0 && count($amounts) > 0) {
            $amounts[array_key_last($amounts)] += $remainder;
        }

        return collect($components)
            ->map(fn (TaxComponentAmount $component, int $index): TaxComponentAmount => new TaxComponentAmount(
                componentId: $component->componentId,
                code: $component->code,
                name: $component->name,
                rate: $component->rate,
                amount: $this->formatUnits($amounts[$index], 2),
                jurisdictionType: $component->jurisdictionType,
            ))
            ->values()
            ->all();
    }

    private function moneyUnits(float|string|int $value, string $label): int
    {
        return $this->decimalUnits($value, 2, $label, allowZero: true);
    }

    private function quantityUnits(float|string|int $value): int
    {
        return $this->decimalUnits($value, 4, 'quantity', allowZero: false);
    }

    private function rateUnits(float|string|int $value): int
    {
        return $this->decimalUnits($value, 4, 'tax rate', allowZero: true);
    }

    private function decimalUnits(float|string|int $value, int $scale, string $label, bool $allowZero): int
    {
        $value = trim((string) $value);

        if (! preg_match('/^\+?\d+(?:\.\d+)?$/', $value)) {
            throw new TaxCalculationInputException("Invalid {$label}.");
        }

        $value = ltrim($value, '+');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $units = ((int) $whole * (10 ** $scale)) + (int) substr($fraction, 0, $scale);

        if ((int) $fraction[$scale] >= 5) {
            $units++;
        }

        if ($units < 0 || (! $allowZero && $units <= 0)) {
            throw new TaxCalculationInputException("Invalid {$label}.");
        }

        return $units;
    }

    private function roundDiv(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function formatUnits(int $units, int $scale): string
    {
        $whole = intdiv($units, 10 ** $scale);
        $fraction = str_pad((string) ($units % (10 ** $scale)), $scale, '0', STR_PAD_LEFT);

        return "{$whole}.{$fraction}";
    }

    private function formatQuantity(int $units): string
    {
        return rtrim(rtrim($this->formatUnits($units, 4), '0'), '.') ?: '0';
    }
}
