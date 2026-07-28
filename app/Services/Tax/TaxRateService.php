<?php

namespace App\Services\Tax;

use App\Models\TaxClass;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxRateService
{
    public function __construct(private readonly TaxValidationService $validation)
    {
    }

    public function create(TaxClass $taxClass, array $data): TaxRate
    {
        $rate = new TaxRate([
            'tax_class_id' => $taxClass->getKey(),
            'name' => $data['name'],
            'total_rate' => $data['total_rate'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'status' => $data['status'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->validation->ensureTaxRateDatesDoNotOverlap($rate);
        $rate->save();

        return $rate;
    }

    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        $candidate = $taxRate->replicate();
        $candidate->forceFill([
            'name' => $data['name'],
            'total_rate' => $data['total_rate'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'status' => $data['status'],
        ]);

        $this->validation->ensureTaxRateDatesDoNotOverlap($candidate, $taxRate);
        $this->validation->ensureExistingComponentTotalMatches($taxRate, (string) $data['total_rate'], $data['status']);

        $taxRate->forceFill([
            'name' => $data['name'],
            'total_rate' => $data['total_rate'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return $taxRate;
    }

    public function delete(TaxRate $taxRate): void
    {
        DB::transaction(function () use ($taxRate): void {
            $taxRate->forceFill(['deleted_by' => Auth::id()])->save();
            $taxRate->delete();
        });
    }

    public function restore(TaxRate $taxRate): void
    {
        $candidate = $taxRate->replicate();
        $candidate->status = TaxRate::STATUS_INACTIVE;
        $this->validation->ensureTaxRateDatesDoNotOverlap($candidate, $taxRate);

        DB::transaction(function () use ($taxRate): void {
            $taxRate->restore();
            $taxRate->forceFill([
                'deleted_by' => null,
                'status' => TaxRate::STATUS_INACTIVE,
                'updated_by' => Auth::id(),
            ])->save();
        });
    }
}
