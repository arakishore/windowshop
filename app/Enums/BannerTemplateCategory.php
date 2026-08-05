<?php

namespace App\Enums;

enum BannerTemplateCategory: string
{
    case GENERAL = 'general';
    case FESTIVAL = 'festival';
    case SEASONAL = 'seasonal';
    case FASHION = 'fashion';
    case ELECTRONICS = 'electronics';
    case GROCERY = 'grocery';
    case SERVICES = 'services';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::FESTIVAL => 'Festival',
            self::SEASONAL => 'Seasonal',
            self::FASHION => 'Fashion',
            self::ELECTRONICS => 'Electronics',
            self::GROCERY => 'Grocery',
            self::SERVICES => 'Services',
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
            ->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])
            ->all();
    }
}
