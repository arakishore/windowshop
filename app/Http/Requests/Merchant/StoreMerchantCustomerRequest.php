<?php

namespace App\Http\Requests\Merchant;

use App\Models\MerchantProfile;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Shared\MobileNumberNormalizer;
use App\Support\Validation\MerchantCustomerRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $mobile = app(MobileNumberNormalizer::class)->normalize(
            (string) $this->input('mobile', ''),
            $this->filled('mobile_country_code') ? (string) $this->input('mobile_country_code') : null,
        );

        $this->merge([
            'mobile_country_code' => $mobile['country_code'],
            'mobile' => $mobile['mobile'],
            'mobile_normalized' => $mobile['mobile_normalized'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return MerchantCustomerRules::rules($this->merchant()->getKey());
    }

    public function merchant(): MerchantProfile
    {
        $merchant = app(MerchantShopContextService::class)->activeMerchantForUser($this->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        return $merchant;
    }
}
