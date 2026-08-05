<?php

namespace App\Enums;

enum BannerLinkType: string
{
    case NONE = 'none';
    case PRODUCT = 'product';
    case CATEGORY = 'category';
    case BRAND = 'brand';
    case SHOP = 'shop';
    case PROMOTION = 'promotion';
    case CUSTOM_URL = 'custom_url';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::PRODUCT => 'Product',
            self::CATEGORY => 'Category',
            self::BRAND => 'Brand',
            self::SHOP => 'Shop',
            self::PROMOTION => 'Promotion',
            self::CUSTOM_URL => 'Custom URL',
        };
    }

    public function requiresTarget(): bool
    {
        return $this !== self::NONE;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(bool $includeDeferred = false): array
    {
        return collect(self::cases())
            ->reject(fn (self $type): bool => $type === self::PROMOTION && ! $includeDeferred)
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
