<?php

namespace App\Enums;

enum UserRegistrationSource: string
{
    case WEB = 'web';
    case MOBILE_APP = 'mobile_app';
    case POS = 'pos';
    case MERCHANT = 'merchant';
    case ADMIN = 'admin';
    case IMPORT = 'import';
    case API = 'api';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'WindowShop Website',
            self::MOBILE_APP => 'WindowShop Mobile App',
            self::POS => 'Merchant POS',
            self::MERCHANT => 'Merchant Panel',
            self::ADMIN => 'Admin',
            self::IMPORT => 'Import',
            self::API => 'API',
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
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => $options + [$case->value => $case->label()],
            [],
        );
    }
}
