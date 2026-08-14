<?php

namespace App\Services\System;

use App\Models\SystemSetting;

class SystemSettingService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::query()
            ->where('key', $key)
            ->where('status', SystemSetting::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if ($setting === null) {
            return $default;
        }

        return $this->cast($setting->value, (string) $setting->value_type, $default);
    }

    public function merchantBannerLimitPerShop(): int
    {
        $value = $this->get('storefront_banner.max_per_shop', 3);

        if (! is_int($value) && ! ctype_digit((string) $value)) {
            return 3;
        }

        $limit = (int) $value;

        return $limit >= 1 && $limit <= 10 ? $limit : 3;
    }

    public function marketplaceName(): string
    {
        $value = trim((string) $this->get('marketplace_name', 'WindowShop'));

        return $value === '' ? 'WindowShop' : $value;
    }

    public function globalProductDisclaimer(): string
    {
        $value = trim((string) $this->get(
            'storefront.product_disclaimer.global',
            'Product images, prices, availability and details are provided by shops or suppliers and may vary. Please verify key details with the shop before purchase.',
        ));

        return $value === ''
            ? 'Product images, prices, availability and details are provided by shops or suppliers and may vary. Please verify key details with the shop before purchase.'
            : $value;
    }

    private function cast(?string $value, string $type, mixed $default): mixed
    {
        return match ($type) {
            SystemSetting::TYPE_INTEGER => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default,
            SystemSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default,
            SystemSetting::TYPE_JSON,
            SystemSetting::TYPE_ARRAY => $this->decodeJson($value, $default),
            SystemSetting::TYPE_TEXT,
            SystemSetting::TYPE_STRING => $value,
            default => $value,
        };
    }

    private function decodeJson(?string $value, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}
