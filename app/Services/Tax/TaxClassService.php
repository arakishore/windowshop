<?php

namespace App\Services\Tax;

use App\Models\TaxClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxClassService
{
    public function __construct(private readonly TaxValidationService $validation)
    {
    }

    public function create(array $data): TaxClass
    {
        $this->validation->ensureTaxClassCodeIsAvailable((int) $data['country_id'], strtoupper($data['code']));
        $actorId = Auth::id();

        return TaxClass::query()->create([
            'country_id' => (int) $data['country_id'],
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $this->nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    public function update(TaxClass $taxClass, array $data): TaxClass
    {
        $this->validation->ensureTaxClassCodeIsAvailable((int) $data['country_id'], strtoupper($data['code']), $taxClass);

        $taxClass->forceFill([
            'country_id' => (int) $data['country_id'],
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $this->nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return $taxClass;
    }

    public function delete(TaxClass $taxClass): void
    {
        if ($taxClass->rates()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'tax_class' => 'This tax class cannot be deleted because it still has active tax rates. Inactivate the rates first.',
            ]);
        }

        DB::transaction(function () use ($taxClass): void {
            $taxClass->forceFill(['deleted_by' => Auth::id()])->save();
            $taxClass->delete();
        });
    }

    public function restore(TaxClass $taxClass): void
    {
        DB::transaction(function () use ($taxClass): void {
            $taxClass->restore();
            $taxClass->forceFill([
                'deleted_by' => null,
                'status' => TaxClass::STATUS_INACTIVE,
                'updated_by' => Auth::id(),
            ])->save();
        });
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
