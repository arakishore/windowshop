<?php

namespace App\Support\Validation;

use App\Models\MerchantCustomer;
use Illuminate\Validation\Rule;

class MerchantCustomerRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(int $merchantId, ?int $ignoreCustomerId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'mobile_country_code' => ['nullable', 'string', 'max:10'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'is_business_customer' => ['nullable', 'boolean'],
            'company_name' => ['nullable', 'required_if:is_business_customer,1', 'string', 'max:150'],
            'gst_number' => ['nullable', 'required_if:is_business_customer,1', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in([MerchantCustomer::STATUS_ACTIVE, MerchantCustomer::STATUS_INACTIVE])],
            'mobile_normalized' => [
                'required',
                'string',
                'max:30',
                Rule::unique('merchant_customers', 'mobile_normalized')
                    ->where('merchant_id', $merchantId)
                    ->ignore($ignoreCustomerId),
            ],
        ];
    }
}
