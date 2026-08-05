<?php

namespace App\Enums;

enum BannerPosition: string
{
    case HOMEPAGE_HERO = 'homepage_hero';
    case HOMEPAGE_MIDDLE = 'homepage_middle';
    case HOMEPAGE_BOTTOM = 'homepage_bottom';
    case CATEGORY_TOP = 'category_top';
    case STORE_HERO = 'store_hero';
    case STORE_MIDDLE = 'store_middle';

    public const SCOPE_ADMIN = 'admin';

    public const SCOPE_MERCHANT = 'merchant';

    public function scope(): string
    {
        return match ($this) {
            self::HOMEPAGE_HERO,
            self::HOMEPAGE_MIDDLE,
            self::HOMEPAGE_BOTTOM,
            self::CATEGORY_TOP => self::SCOPE_ADMIN,
            self::STORE_HERO,
            self::STORE_MIDDLE => self::SCOPE_MERCHANT,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::HOMEPAGE_HERO => 'Homepage Hero',
            self::HOMEPAGE_MIDDLE => 'Homepage Middle',
            self::HOMEPAGE_BOTTOM => 'Homepage Bottom',
            self::CATEGORY_TOP => 'Category Top',
            self::STORE_HERO => 'Store Hero',
            self::STORE_MIDDLE => 'Store Middle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HOMEPAGE_HERO => 'Main slider on marketplace homepage.',
            self::HOMEPAGE_MIDDLE => 'Between marketplace categories and product sections.',
            self::HOMEPAGE_BOTTOM => 'Near the bottom of the marketplace homepage.',
            self::CATEGORY_TOP => 'Top banner on category pages.',
            self::STORE_HERO => 'Top banner on merchant store.',
            self::STORE_MIDDLE => 'Middle promotional banner on merchant store.',
        };
    }

    public function maxBanners(): int
    {
        return match ($this) {
            self::HOMEPAGE_HERO => 5,
            self::HOMEPAGE_MIDDLE,
            self::HOMEPAGE_BOTTOM,
            self::CATEGORY_TOP,
            self::STORE_MIDDLE => 3,
            self::STORE_HERO => 5,
        };
    }

    public function recommendedDimensions(): array
    {
        return match ($this) {
            self::HOMEPAGE_HERO => ['desktop' => '1920 x 600', 'mobile' => '800 x 1000'],
            self::STORE_HERO => ['desktop' => '1600 x 500', 'mobile' => '800 x 900'],
            default => ['desktop' => '1600 x 500', 'mobile' => '800 x 900'],
        };
    }

    public function isAdmin(): bool
    {
        return $this->scope() === self::SCOPE_ADMIN;
    }

    public function isMerchant(): bool
    {
        return $this->scope() === self::SCOPE_MERCHANT;
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
    public static function options(?string $scope = null): array
    {
        return collect(self::cases())
            ->when($scope, fn ($positions) => $positions->filter(fn (self $position): bool => $position->scope() === $scope))
            ->mapWithKeys(fn (self $position): array => [$position->value => $position->label()])
            ->all();
    }
}
