<?php

namespace App\Enums;

enum BannerSourceType: string
{
    case TEMPLATE = 'template';
    case CUSTOM_UPLOAD = 'custom_upload';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
