<?php

namespace App\Services\Tax;

use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxRateComponentService
{
    public function __construct(private readonly TaxValidationService $validation)
    {
    }

    public function create(TaxRate $taxRate, array $data): TaxRateComponent
    {
        $this->validation->ensureComponentCodeIsAvailable($taxRate->getKey(), strtoupper($data['code']));

        $component = new TaxRateComponent([
            'tax_rate_id' => $taxRate->getKey(),
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'rate' => $data['rate'],
            'jurisdiction_type' => $data['jurisdiction_type'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->validation->ensureComponentTotalMatches($taxRate, $component);
        $component->save();

        return $component;
    }

    public function update(TaxRateComponent $component, array $data): TaxRateComponent
    {
        $this->validation->ensureComponentCodeIsAvailable($component->tax_rate_id, strtoupper($data['code']), $component);

        $candidate = $component->replicate();
        $candidate->forceFill([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'rate' => $data['rate'],
            'jurisdiction_type' => $data['jurisdiction_type'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
        ]);

        $this->validation->ensureComponentTotalMatches($component->taxRate, $candidate, $component);

        $component->forceFill([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'rate' => $data['rate'],
            'jurisdiction_type' => $data['jurisdiction_type'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'updated_by' => Auth::id(),
        ])->save();

        return $component;
    }

    public function delete(TaxRateComponent $component): void
    {
        $taxRate = $component->taxRate;

        if ($taxRate->status === TaxRate::STATUS_ACTIVE) {
            $remainingTotal = TaxRateComponent::query()
                ->where('tax_rate_id', $taxRate->getKey())
                ->whereKeyNot($component->getKey())
                ->sum('rate');

            if ((int) round(((float) $remainingTotal) * 10000) !== (int) round(((float) $taxRate->total_rate) * 10000)) {
                throw ValidationException::withMessages([
                    'rate' => 'This component cannot be deleted because the remaining active component total would no longer match the tax rate total.',
                ]);
            }
        }

        DB::transaction(function () use ($component): void {
            $component->forceFill(['deleted_by' => Auth::id()])->save();
            $component->delete();
        });
    }

    public function restore(TaxRateComponent $component): void
    {
        $this->validation->ensureComponentTotalMatches($component->taxRate, $component);

        DB::transaction(function () use ($component): void {
            $component->restore();
            $component->forceFill([
                'deleted_by' => null,
                'updated_by' => Auth::id(),
            ])->save();
        });
    }
}
