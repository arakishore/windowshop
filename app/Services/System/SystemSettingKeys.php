<?php

namespace App\Services\System;

final class SystemSettingKeys
{
    public const DEFAULT_CURRENCY = 'default_currency';

    public const DEFAULT_TIMEZONE = 'default_timezone';

    public const REGIONAL_DATE_FORMAT = 'regional.date_format';

    public const REGIONAL_TIME_FORMAT = 'regional.time_format';

    public const REGIONAL_FINANCIAL_YEAR_START_MONTH = 'regional.financial_year_start_month';

    public const CURRENCY_SYMBOL = 'currency.symbol';

    public const CURRENCY_DECIMAL_PLACES = 'currency.decimal_places';

    public const CURRENCY_THOUSANDS_SEPARATOR = 'currency.thousands_separator';

    public const CURRENCY_DECIMAL_SEPARATOR = 'currency.decimal_separator';

    public const CURRENCY_SYMBOL_POSITION = 'currency.symbol_position';

    public const STOREFRONT_BANNER_MAX_PER_SHOP = 'storefront_banner.max_per_shop';

    public const EMAIL_PREFIX = 'notifications.email.';

    public const NOTIFICATION_PREFERENCE_PREFIX = 'notifications.events.';

    public const EMAIL_SMTP_PASSWORD = self::EMAIL_PREFIX.'smtp.password';

    /** @var array<string, string> */
    public const LEGACY_TO_CANONICAL = [
        'regional.timezone' => self::DEFAULT_TIMEZONE,
        'regional.date_format' => self::REGIONAL_DATE_FORMAT,
        'regional.time_format' => self::REGIONAL_TIME_FORMAT,
        'regional.financial_year_start_month' => self::REGIONAL_FINANCIAL_YEAR_START_MONTH,
        'currency.base_currency' => self::DEFAULT_CURRENCY,
        'currency.symbol' => self::CURRENCY_SYMBOL,
        'currency.decimal_places' => self::CURRENCY_DECIMAL_PLACES,
        'currency.thousands_separator' => self::CURRENCY_THOUSANDS_SEPARATOR,
        'currency.decimal_separator' => self::CURRENCY_DECIMAL_SEPARATOR,
        'currency.symbol_position' => self::CURRENCY_SYMBOL_POSITION,
        'notifications.email.enabled' => self::EMAIL_PREFIX.'enabled',
        'notifications.email.transport' => self::EMAIL_PREFIX.'transport',
        'notifications.email.smtp.host' => self::EMAIL_PREFIX.'smtp.host',
        'notifications.email.smtp.port' => self::EMAIL_PREFIX.'smtp.port',
        'notifications.email.smtp.encryption' => self::EMAIL_PREFIX.'smtp.encryption',
        'notifications.email.smtp.username' => self::EMAIL_PREFIX.'smtp.username',
        'notifications.email.smtp.password' => self::EMAIL_SMTP_PASSWORD,
        'notifications.email.from_name' => self::EMAIL_PREFIX.'from_name',
        'notifications.email.from_email' => self::EMAIL_PREFIX.'from_email',
        'notifications.email.reply_to' => self::EMAIL_PREFIX.'reply_to',
        'notifications.email.admin_notification_email' => self::EMAIL_PREFIX.'admin_notification_email',
        'notifications.email.branding.logo_path' => self::EMAIL_PREFIX.'branding.logo_path',
        'notifications.email.branding.show_name' => self::EMAIL_PREFIX.'branding.show_name',
        'notifications.email.footer.show' => self::EMAIL_PREFIX.'footer.show',
        'notifications.email.footer.benefit_1' => self::EMAIL_PREFIX.'footer.benefit_1',
        'notifications.email.footer.benefit_2' => self::EMAIL_PREFIX.'footer.benefit_2',
        'notifications.email.footer.benefit_3' => self::EMAIL_PREFIX.'footer.benefit_3',
        'notifications.email.footer.social.facebook' => self::EMAIL_PREFIX.'footer.social.facebook',
        'notifications.email.footer.social.instagram' => self::EMAIL_PREFIX.'footer.social.instagram',
        'notifications.email.footer.social.youtube' => self::EMAIL_PREFIX.'footer.social.youtube',
        'notifications.email.footer.social.twitter' => self::EMAIL_PREFIX.'footer.social.twitter',
        'notifications.email.footer.social.linkedin' => self::EMAIL_PREFIX.'footer.social.linkedin',
        'notifications.email.footer.apps.google_play' => self::EMAIL_PREFIX.'footer.apps.google_play',
        'notifications.email.footer.apps.app_store' => self::EMAIL_PREFIX.'footer.apps.app_store',
        'notifications.email.footer.show_powered_by' => self::EMAIL_PREFIX.'footer.show_powered_by',
        'notifications.email.footer.powered_by_text' => self::EMAIL_PREFIX.'footer.powered_by_text',
    ];

    public static function canonicalKeyForLegacy(string $group, string $key): ?string
    {
        $legacyKey = $group.'.'.$key;

        if (isset(self::LEGACY_TO_CANONICAL[$legacyKey])) {
            return self::LEGACY_TO_CANONICAL[$legacyKey];
        }

        return $group === 'notifications' && str_starts_with($key, 'events.')
            ? 'notifications.'.$key
            : null;
    }

    public static function notificationPreference(string $eventKey, string $channel): string
    {
        return self::NOTIFICATION_PREFERENCE_PREFIX.$eventKey.'.'.$channel.'.enabled';
    }

    public static function email(string $key): string
    {
        return self::EMAIL_PREFIX.$key;
    }
}
