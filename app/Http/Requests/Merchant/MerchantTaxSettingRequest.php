<?php

namespace App\Http\Requests\Merchant;

use App\Models\TaxClass;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantTaxSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_enabled' => ['required', 'boolean'],
            'default_tax_class_id' => [
                Rule::requiredIf($this->boolean('tax_enabled')),
                'nullable',
                'integer',
                Rule::exists('tax_classes', 'id')
                    ->where(fn ($query) => $query
                        ->whereNull('deleted_at')
                        ->where('status', TaxClass::STATUS_ACTIVE)
                        ->when($this->merchantCountryId(), fn ($query, int $countryId) => $query->where('country_id', $countryId))),
            ],
            'prices_include_tax' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_tax_class_id.required' => 'Choose a default tax class when tax is enabled.',
            'default_tax_class_id.exists' => 'Choose an active default tax class for the merchant business country.',
        ];
    }

    private function merchantCountryId(): ?int
    {
        $merchant = app(MerchantShopContextService::class)->activeMerchantForUser($this->user());
        $businessCountryId = $merchant?->businessAddress()->value('country_id');

        return $businessCountryId ? (int) $businessCountryId : null;
    }
}
