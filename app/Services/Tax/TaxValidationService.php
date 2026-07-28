<?php

namespace App\Services\Tax;

use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use Illuminate\Validation\ValidationException;

class TaxValidationService
{
    public function ensureTaxClassCodeIsAvailable(int $countryId, string $code, ?TaxClass $ignore = null): void
    {
        $match = TaxClass::withTrashed()
            ->where('country_id', $countryId)
            ->where('code', $code)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->first();

        if (! $match) {
            return;
        }

        $message = $match->trashed()
            ? 'A matching tax class exists in Trash. Restore the existing tax class instead of creating a duplicate.'
            : 'A tax class with this code already exists for the selected country.';

        throw ValidationException::withMessages(['code' => $message]);
    }

    public function ensureTaxRateDatesDoNotOverlap(TaxRate $candidate, ?TaxRate $ignore = null): void
    {
        if ($candidate->status !== TaxRate::STATUS_ACTIVE) {
            return;
        }

        $from = $candidate->effective_from?->toDateString() ?? (string) $candidate->effective_from;
        $to = $candidate->effective_to?->toDateString();

        $overlap = TaxRate::query()
            ->where('tax_class_id', $candidate->tax_class_id)
            ->where('total_rate', $candidate->total_rate)
            ->where('status', TaxRate::STATUS_ACTIVE)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->whereDate('effective_from', '<=', $to ?? '9999-12-31')
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_from' => 'Active tax rates for the same tax class and total rate cannot have overlapping effective periods.',
            ]);
        }
    }

    public function ensureComponentCodeIsAvailable(int $taxRateId, string $code, ?TaxRateComponent $ignore = null): void
    {
        $match = TaxRateComponent::withTrashed()
            ->where('tax_rate_id', $taxRateId)
            ->where('code', $code)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->first();

        if (! $match) {
            return;
        }

        $message = $match->trashed()
            ? 'A matching tax component exists in Trash. Restore the existing component instead of creating a duplicate.'
            : 'A tax component with this code already exists for this tax rate.';

        throw ValidationException::withMessages(['code' => $message]);
    }

    public function ensureComponentTotalMatches(TaxRate $taxRate, ?TaxRateComponent $candidate = null, ?TaxRateComponent $ignore = null): void
    {
        if ($taxRate->status !== TaxRate::STATUS_ACTIVE) {
            return;
        }

        $sum = (string) TaxRateComponent::query()
            ->where('tax_rate_id', $taxRate->getKey())
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->sum('rate');

        if ($candidate) {
            $sum = number_format(((float) $sum) + ((float) $candidate->rate), 4, '.', '');
        }

        if ($this->rateToUnits($sum) !== $this->rateToUnits((string) $taxRate->total_rate)) {
            throw ValidationException::withMessages([
                'rate' => "Tax component rates must total {$taxRate->total_rate}. Current total would be ".number_format((float) $sum, 4, '.', '').'.',
            ]);
        }
    }

    public function ensureExistingComponentTotalMatches(TaxRate $taxRate, string $totalRate, string $status): void
    {
        if ($status !== TaxRate::STATUS_ACTIVE) {
            return;
        }

        if (! TaxRateComponent::query()->where('tax_rate_id', $taxRate->getKey())->exists()) {
            return;
        }

        $sum = (string) TaxRateComponent::query()
            ->where('tax_rate_id', $taxRate->getKey())
            ->sum('rate');

        if ($this->rateToUnits($sum) !== $this->rateToUnits($totalRate)) {
            throw ValidationException::withMessages([
                'total_rate' => 'Total rate must match the sum of existing active component rates.',
            ]);
        }
    }

    private function rateToUnits(string $rate): int
    {
        return (int) round(((float) $rate) * 10000);
    }
}
