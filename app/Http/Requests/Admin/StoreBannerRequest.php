<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerPosition;
use App\Http\Requests\Concerns\ValidatesBannerRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBannerRequest extends FormRequest
{
    use ValidatesBannerRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->bannerRules(true),
            'owner_type' => ['required', Rule::in(['marketplace', 'merchant'])],
            'merchant_id' => ['nullable', 'required_if:owner_type,merchant', 'integer', Rule::exists('merchant_profiles', 'id')->whereNull('deleted_at')],
            'shop_id' => ['nullable', 'required_if:owner_type,merchant', 'integer', Rule::exists('shops', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $ownerType = (string) $this->input('owner_type', 'marketplace');
        $scope = $ownerType === 'merchant' ? BannerPosition::SCOPE_MERCHANT : BannerPosition::SCOPE_ADMIN;
        $merchantId = $ownerType === 'merchant' ? (int) $this->input('merchant_id') : null;
        $shopId = $ownerType === 'merchant' ? (int) $this->input('shop_id') : null;

        $this->validateBannerBusinessRules($validator, $scope, $merchantId, $shopId);
    }

    public function ownerData(): array
    {
        if ($this->input('owner_type') !== 'merchant') {
            return ['merchant_id' => null, 'shop_id' => null];
        }

        return [
            'merchant_id' => (int) $this->input('merchant_id'),
            'shop_id' => (int) $this->input('shop_id'),
        ];
    }
}
