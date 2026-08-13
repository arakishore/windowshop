<?php

namespace App\Http\Requests\Concerns;

use App\Models\PostalCodeRestriction;
use Illuminate\Validation\Rule;

trait ValidatesPostalCodeRestrictionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function restrictionRules(): array
    {
        return [
            'postal_code' => ['required', 'string', 'max:20', Rule::exists('postal_codes', 'postal_code')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in([PostalCodeRestriction::STATUS_ACTIVE, PostalCodeRestriction::STATUS_INACTIVE])],
        ];
    }

    /**
     * @return array{postal_code: string, reason: ?string, starts_at: mixed, ends_at: mixed, status: string}
     */
    public function normalizedRestrictionData(): array
    {
        $data = $this->validated();
        $reason = trim((string) ($data['reason'] ?? ''));

        return [
            'postal_code' => trim((string) $data['postal_code']),
            'reason' => $reason === '' ? null : $reason,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'status' => $data['status'],
        ];
    }
}
