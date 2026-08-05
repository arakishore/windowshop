<?php

namespace App\Enums;

enum BannerTemplateAvailability: string
{
    case ADMIN = 'admin';
    case MERCHANT = 'merchant';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin Only',
            self::MERCHANT => 'Merchant Only',
            self::BOTH => 'Admin & Merchant',
        };
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
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $availability): array => [$availability->value => $availability->label()])
            ->all();
    }
}
