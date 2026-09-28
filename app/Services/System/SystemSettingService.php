<?php

namespace App\Services\System;

use App\Models\SystemSetting;

class SystemSettingService
{
    /**
     * @var array<string, SystemSetting|null>
     */
    private array $settingCache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->settingCache)) {
            $this->settingCache[$key] = SystemSetting::query()
                ->where('key', $key)
                ->where('status', SystemSetting::STATUS_ACTIVE)
                ->whereNull('deleted_at')
                ->first();
        }

        $setting = $this->settingCache[$key];

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

    /** @return array{email: ?string, phone: ?string, phone_href: ?string, whatsapp_url: ?string, office_address: ?string, support_hours: ?string, whatsapp_hours: ?string, social_links: array<string, string>} */
    public function publicContact(): array
    {
        $email = $this->optional('contact.support_email');
        $email = $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
        $phone = $this->optional('contact.phone');
        $phoneNormalized = preg_replace('/[^0-9+]/', '', $phone ?? '') ?: '';
        $phoneHref = preg_match('/^\+?[0-9]{7,15}$/', $phoneNormalized) === 1 ? 'tel:'.$phoneNormalized : null;
        $whatsApp = preg_replace('/\D+/', '', $this->optional('contact.whatsapp') ?? '') ?: '';

        return [
            'email' => $email,
            'phone' => $phoneHref === null ? null : $phone,
            'phone_href' => $phoneHref,
            'whatsapp_url' => $whatsApp === '' ? null : 'https://wa.me/'.$whatsApp,
            'office_address' => $this->optional('contact.office_address'),
            'support_hours' => $this->optional('contact.support_hours'),
            'whatsapp_hours' => $this->optional('contact.whatsapp_hours'),
            'social_links' => $this->socialLinks(),
        ];
    }

    /** @return array<string, string> */
    public function socialLinks(): array
    {
        $links = [
            'Facebook' => $this->optional('social.facebook'),
            'Instagram' => $this->optional('social.instagram'),
            'X / Twitter' => $this->optional('social.twitter'),
            'YouTube' => $this->optional('social.youtube'),
            'LinkedIn' => $this->optional('social.linkedin'),
        ];

        return array_filter($links, fn (?string $url): bool => $url !== null
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true));
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

    private function optional(string $key): ?string
    {
        $value = trim((string) $this->get($key, ''));

        return $value === '' ? null : $value;
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
