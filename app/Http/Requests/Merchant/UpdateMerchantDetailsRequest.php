<?php

namespace App\Http\Requests\Merchant;

use App\Enums\MerchantBusinessType;
use App\Enums\MerchantVerificationStatus;
use App\Models\MerchantProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantDetailsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In|\Illuminate\Validation\Rules\Unique>>
     */
    public function rules(): array
    {
        $merchant = $this->merchant();
        $locked = $merchant?->verification_status === MerchantVerificationStatus::APPROVED->value;

        return [
            'business_name' => ['required', 'string', 'max:150'],
            'contact_person_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:20'],
            'legal_name' => [$locked ? 'prohibited' : 'nullable', 'string', 'max:150'],
            'business_type' => [$locked ? 'prohibited' : 'nullable', 'string', 'max:50', Rule::in(MerchantBusinessType::values())],
            'gst_number' => [
                $locked ? 'prohibited' : 'nullable',
                'string',
                'max:30',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',
                Rule::unique('merchant_profiles', 'gst_number')->ignore($merchant?->getKey()),
            ],
            'has_shop_license' => [$locked ? 'prohibited' : 'nullable', 'boolean'],
            'has_fssai' => [$locked ? 'prohibited' : 'nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gst_number.regex' => 'The GST number format is invalid.',
            '*.prohibited' => 'This field is locked after merchant verification.',
        ];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    private function merchant(): ?MerchantProfile
    {
        $merchantId = $this->session()->get('merchant_id');

        if ($merchantId === null) {
            return $this->user()?->merchantProfile;
        }

        return MerchantProfile::query()
            ->whereKey($merchantId)
            ->where('user_id', $this->user()?->getKey())
            ->first();
    }
}
