<?php

namespace App\Http\Requests\Admin;

use App\Enums\MerchantBusinessType;
use App\Enums\MerchantStatus;
use App\Enums\MerchantVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Unique|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['required', 'string', 'max:50', Rule::in(MerchantBusinessType::values())],
            'gst_number' => ['nullable', 'string', 'max:30', 'unique:merchant_profiles,gst_number'],
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
