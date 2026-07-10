<?php

namespace App\Http\Requests\Admin;

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
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:50', Rule::in($this->businessTypes())],
            'gst_number' => ['nullable', 'string', 'max:30', 'unique:merchant_profiles,gst_number'],
            'pan_number' => ['nullable', 'string', 'max:20', 'unique:merchant_profiles,pan_number'],
            'contact_person_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_mobile' => ['nullable', 'string', 'max:20'],
            'alternate_mobile' => ['nullable', 'string', 'max:20'],
            'website_url' => ['nullable', 'string', 'url', 'max:255'],
            'verification_status' => ['required', 'string', Rule::in($this->verificationStatuses())],
            'status' => ['required', 'string', Rule::in($this->accountStatuses())],
            'admin_note' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'required_if:verification_status,rejected', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    private function businessTypes(): array
    {
        return ['individual', 'proprietorship', 'partnership', 'llp', 'pvt_ltd', 'public_ltd', 'other'];
    }

    /**
     * @return array<int, string>
     */
    private function verificationStatuses(): array
    {
        return ['pending', 'submitted', 'approved', 'rejected', 'suspended'];
    }

    /**
     * @return array<int, string>
     */
    private function accountStatuses(): array
    {
        return ['active', 'inactive', 'suspended'];
    }
}
