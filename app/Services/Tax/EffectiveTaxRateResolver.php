<?php

namespace App\Services\Tax;

use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Services\Tax\Data\EffectiveTaxRateResult;
use App\Services\Tax\Data\TaxComponentAmount;
use App\Services\Tax\Data\TaxResolutionResult;
use App\Services\Tax\Exceptions\OverlappingTaxRatesException;
use App\Services\Tax\Exceptions\TaxComponentMismatchException;
use App\Services\Tax\Exceptions\TaxRateNotFoundException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class EffectiveTaxRateResolver
{
    public function resolve(TaxResolutionResult|TaxClass $resolutionOrClass, CarbonInterface $effectiveAt): ?EffectiveTaxRateResult
    {
        if ($resolutionOrClass instanceof TaxResolutionResult && ! $resolutionOrClass->taxClassId) {
            return null;
        }

        $taxClass = $resolutionOrClass instanceof TaxResolutionResult
            ? TaxClass::query()->findOrFail($resolutionOrClass->taxClassId)
            : $resolutionOrClass;

        $rates = $taxClass->rates()
            ->active()
            ->effectiveOn($effectiveAt)
            ->with(['components' => fn ($query) => $query->ordered()])
            ->orderBy('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();

        if ($rates->isEmpty()) {
            throw new TaxRateNotFoundException("No active effective tax rate found for tax class {$taxClass->code} on {$effectiveAt->toDateString()}.");
        }

        if ($rates->count() > 1) {
            throw new OverlappingTaxRatesException("Multiple active effective tax rates found for tax class {$taxClass->code} on {$effectiveAt->toDateString()}.");
        }

        /** @var TaxRate $rate */
        $rate = $rates->first();
        $this->assertComponentRatesMatch($rate, $taxClass);

        return new EffectiveTaxRateResult(
            taxRateId: (int) $rate->getKey(),
            taxRateName: $rate->name,
            totalRate: (string) $rate->total_rate,
            effectiveFrom: $rate->effective_from?->toDateString(),
            effectiveTo: $rate->effective_to?->toDateString(),
            effectiveAt: $effectiveAt,
            components: $rate->components
                ->map(fn ($component): TaxComponentAmount => new TaxComponentAmount(
                    componentId: (int) $component->getKey(),
                    code: $component->code,
                    name: $component->name,
                    rate: (string) $component->rate,
                    amount: '0.00',
                    jurisdictionType: $component->jurisdiction_type,
                ))
                ->values()
                ->all(),
        );
    }

    private function assertComponentRatesMatch(TaxRate $rate, TaxClass $taxClass): void
    {
        /** @var Collection<int, \App\Models\TaxRateComponent> $components */
        $components = $rate->components;
        $componentTotal = $components->sum(fn ($component): int => $this->rateUnits((string) $component->rate));

        if ($componentTotal !== $this->rateUnits((string) $rate->total_rate)) {
            throw new TaxComponentMismatchException("Tax components for {$taxClass->code} must total {$rate->total_rate}%.");
        }
    }

    private function rateUnits(string $rate): int
    {
        $rate = trim($rate);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $rate)) {
            throw new TaxComponentMismatchException("Invalid tax rate value {$rate}.");
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $fraction = str_pad($fraction, 5, '0');
        $units = ((int) $whole * 10000) + (int) substr($fraction, 0, 4);

        return ((int) $fraction[4] >= 5) ? $units + 1 : $units;
    }
}
