<?php

namespace App\Http\Requests\Admin;

use App\Enums\MerchantBusinessType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantVerificationStatus;
use App\Models\MerchantProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Unique|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        /** @var MerchantProfile $merchant */
        $merchant = $this->route('merchant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($merchant->user_id)],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($merchant->user_id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['required', 'string', 'max:50', Rule::in(MerchantBusinessType::values())],
            'gst_number' => ['nullable', 'string', 'max:30', Rule::unique('merchant_profiles', 'gst_number')->ignore($merchant->getKey())],
            'has_shop_license' => ['nullable', 'boolean'],
            'has_fssai' => ['nullable', 'boolean'],
            'contact_person_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:20'],
            'alternate_mobile' => ['nullable', 'string', 'max:20'],
            'verification_status' => ['required', 'string', Rule::in(MerchantVerificationStatus::values())],
            'status' => ['required', 'string', Rule::in(MerchantStatus::values())],
            'admin_note' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'required_if:verification_status,rejected', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
