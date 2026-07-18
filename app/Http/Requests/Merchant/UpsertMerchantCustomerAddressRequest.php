<?php

namespace App\Http\Requests\Merchant;

use App\Models\MerchantCustomerAddress;
use App\Services\Shared\MobileNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertMerchantCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $mobile = app(MobileNumberNormalizer::class)->normalize(
            (string) $this->input('recipient_mobile', ''),
            $this->filled('recipient_mobile_country_code') ? (string) $this->input('recipient_mobile_country_code') : null,
        );

        $this->merge([
            'recipient_mobile_country_code' => $mobile['country_code'],
            'recipient_mobile' => $mobile['mobile'],
            'recipient_mobile_normalized' => $mobile['mobile_normalized'],
            'is_default_shipping' => $this->boolean('is_default_shipping'),
            'is_default_billing' => $this->boolean('is_default_billing'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_mobile_country_code' => ['nullable', 'string', 'max:10'],
            'recipient_mobile' => ['required', 'string', 'max:30'],
            'recipient_mobile_normalized' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:190'],
            'address_line_2' => ['nullable', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'country_id' => ['nullable', 'integer', 'exists:loc_countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:loc_states,id'],
            'city_id' => ['nullable', 'integer', 'exists:loc_cities,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default_shipping' => ['boolean'],
            'is_default_billing' => ['boolean'],
            'status' => ['required', Rule::in([MerchantCustomerAddress::STATUS_ACTIVE, MerchantCustomerAddress::STATUS_INACTIVE])],
        ];
    }
}
