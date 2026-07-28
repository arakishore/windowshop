<?php

namespace App\Services\Merchant;

use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MerchantTaxSettingService
{
    public function defaultsFor(MerchantProfile $merchant): array
    {
        return [
            'tax_enabled' => false,
            'default_tax_class_id' => null,
            'prices_include_tax' => true,
        ];
    }

    public function firstOrDefault(MerchantProfile $merchant): MerchantTaxSetting
    {
        $setting = MerchantTaxSetting::withTrashed()
            ->where('merchant_id', $merchant->getKey())
            ->first();

        if ($setting instanceof MerchantTaxSetting) {
            return $setting;
        }

        return new MerchantTaxSetting($this->defaultsFor($merchant) + [
            'merchant_id' => $merchant->getKey(),
        ]);
    }

    public function save(MerchantProfile $merchant, array $data): MerchantTaxSetting
    {
        return DB::transaction(function () use ($merchant, $data): MerchantTaxSetting {
            $setting = MerchantTaxSetting::withTrashed()
                ->where('merchant_id', $merchant->getKey())
                ->first();
            $actorId = Auth::id();

            if (! $setting) {
                $setting = new MerchantTaxSetting([
                    'merchant_id' => $merchant->getKey(),
                    'created_by' => $actorId,
                ]);
            }

            if ($setting->trashed()) {
                $setting->restore();
            }

            $setting->forceFill([
                'merchant_id' => $merchant->getKey(),
                'tax_enabled' => (bool) $data['tax_enabled'],
                'default_tax_class_id' => isset($data['default_tax_class_id']) ? (int) $data['default_tax_class_id'] : null,
                'prices_include_tax' => (bool) $data['prices_include_tax'],
                'updated_by' => $actorId,
                'deleted_by' => null,
            ])->save();

            return $setting;
        });
    }

    public function delete(MerchantTaxSetting $setting): void
    {
        DB::transaction(function () use ($setting): void {
            $setting->forceFill(['deleted_by' => Auth::id()])->save();
            $setting->delete();
        });
    }

}
