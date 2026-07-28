<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\TaxRateComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRateComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:9999.9999', 'decimal:0,4'],
            'jurisdiction_type' => [
                'nullable',
                Rule::in([
                    TaxRateComponent::JURISDICTION_CENTRAL,
                    TaxRateComponent::JURISDICTION_STATE,
                    TaxRateComponent::JURISDICTION_INTEGRATED,
                    TaxRateComponent::JURISDICTION_CESS,
                    TaxRateComponent::JURISDICTION_LOCAL,
                ]),
            ],
            'priority' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
