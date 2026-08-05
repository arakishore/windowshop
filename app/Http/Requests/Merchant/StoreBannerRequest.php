<?php

namespace App\Http\Requests\Merchant;

use App\Enums\BannerPosition;
use App\Http\Requests\Concerns\ValidatesBannerRequest;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Foundation\Http\FormRequest;
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
        return $this->bannerRules(true);
    }

    protected function prepareForValidation(): void
    {
        $context = app(MerchantShopContextService::class);
        $merchant = $this->user() ? $context->activeMerchantForUser($this->user()) : null;
        $shop = $merchant ? $context->resolveActiveShop($context->activeShops($merchant), $this->session()->get('active_shop_id')) : null;

        $this->attributes->set('merchant_id', $merchant?->getKey());
        $this->attributes->set('shop_id', $shop?->getKey());
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateBannerBusinessRules(
            $validator,
            BannerPosition::SCOPE_MERCHANT,
            (int) $this->attributes->get('merchant_id'),
            (int) $this->attributes->get('shop_id'),
        );
    }
}
