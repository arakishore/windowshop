<?php

namespace App\Enums;

enum MerchantBusinessType: string
{
    case INDIVIDUAL = 'individual';
    case PROPRIETORSHIP = 'proprietorship';
    case PARTNERSHIP = 'partnership';
    case LLP = 'llp';
    case PVT_LTD = 'pvt_ltd';
    case PUBLIC_LTD = 'public_ltd';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::PROPRIETORSHIP => 'Proprietorship',
            self::PARTNERSHIP => 'Partnership',
            self::LLP => 'LLP',
            self::PVT_LTD => 'Private Limited',
            self::PUBLIC_LTD => 'Public Limited',
            self::OTHER => 'Other',
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
