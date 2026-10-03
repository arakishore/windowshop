<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Services\Admin\AdminSettingsService;
use App\Support\CurrencyCatalog;
use App\Support\TimezoneCatalog;
use DateTimeZone;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class SystemSettingService
{
    private const EMAIL_PASSWORD_PAYLOAD_PREFIX = 'canonical-smtp:v1:';

    public function __construct(
        private readonly AdminSettingsService $legacySettings,
        private readonly TimezoneCatalog $timezones,
        private readonly CurrencyCatalog $currencies,
    ) {}

    public function has(string $key): bool
    {
        return $this->query()->where('key', $key)->exists();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->setting($key);

        if ($setting === null || $this->encrypted($setting)) {
            return $default;
        }

        return $this->cast($setting->value, (string) $setting->value_type, $default);
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        return $value === null ? null : (string) $value;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function boolean(string $key, ?bool $default = null): ?bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return array<mixed> */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /** @return Collection<string, mixed> */
    public function group(string $slug): Collection
    {
        return $this->query()
            ->whereHas('group', fn (Builder $query) => $query
                ->where('slug', $slug)
                ->where('status', SystemSetting::STATUS_ACTIVE)
                ->whereNull('deleted_at'))
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $this->encrypted($setting)
                    ? (filled($setting->value) ? 'Configured' : null)
                    : $this->cast($setting->value, (string) $setting->value_type, null),
            ]);
    }

    /** @param array<string, mixed> $attributes */
    public function set(string $key, mixed $value, ?string $type = null, array $attributes = []): SystemSetting
    {
        $type ??= $this->typeForValue($value);

        if ($type === SystemSetting::TYPE_ENCRYPTED || ($attributes['is_encrypted'] ?? false)) {
            return $this->setEncrypted($key, $value === null ? null : (string) $value, $attributes);
        }

        $this->assertType($type);

        return $this->persist($key, $this->prepare($value, $type), $type, [
            ...$attributes,
            'is_encrypted' => false,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function upsert(string $key, mixed $value, ?string $type = null, array $attributes = []): SystemSetting
    {
        return $this->set($key, $value, $type, $attributes);
    }

    /**
     * A blank value preserves an already-configured secret. A missing blank
     * secret may still be created so its protected metadata can be seeded.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setEncrypted(string $key, ?string $plaintext, array $attributes = []): SystemSetting
    {
        $existing = SystemSetting::withTrashed()->where('key', $key)->first();
        $preserve = trim((string) $plaintext) === '' && $existing !== null && filled($existing->value);
        $encrypted = $preserve ? $existing->value : (filled($plaintext) ? Crypt::encryptString($plaintext) : null);

        return $this->persist($key, $encrypted, SystemSetting::TYPE_ENCRYPTED, [
            ...$attributes,
            'is_public' => false,
            'is_encrypted' => true,
        ]);
    }

    public function secret(string $key, ?string $default = null): ?string
    {
        $setting = $this->setting($key);

        if ($setting === null || ! $this->encrypted($setting) || blank($setting->value)) {
            return $default;
        }

        try {
            return Crypt::decryptString((string) $setting->value);
        } catch (DecryptException) {
            return $default;
        }
    }

    public function secretConfigured(string $key): bool
    {
        $setting = $this->setting($key);

        return $setting !== null && $this->encrypted($setting) && filled($setting->value);
    }

    /** @return array{currency: mixed, symbol: mixed, decimal_places: mixed, thousands_separator: mixed, decimal_separator: mixed, symbol_position: mixed} */
    public function currencyConfig(): array
    {
        return [
            'currency' => $this->canonicalOrLegacy(SystemSettingKeys::DEFAULT_CURRENCY, 'currency', 'base_currency', 'INR'),
            'symbol' => $this->canonicalOrLegacy(SystemSettingKeys::CURRENCY_SYMBOL, 'currency', 'symbol', '₹'),
            'decimal_places' => $this->canonicalOrLegacy(SystemSettingKeys::CURRENCY_DECIMAL_PLACES, 'currency', 'decimal_places', 2),
            'thousands_separator' => $this->canonicalOrLegacy(SystemSettingKeys::CURRENCY_THOUSANDS_SEPARATOR, 'currency', 'thousands_separator', ','),
            'decimal_separator' => $this->canonicalOrLegacy(SystemSettingKeys::CURRENCY_DECIMAL_SEPARATOR, 'currency', 'decimal_separator', '.'),
            'symbol_position' => $this->canonicalOrLegacy(SystemSettingKeys::CURRENCY_SYMBOL_POSITION, 'currency', 'symbol_position', 'before'),
        ];
    }

    /** @return array{timezone: mixed, date_format: mixed, time_format: mixed, financial_year_start_month: mixed} */
    public function regionalConfig(): array
    {
        return [
            'timezone' => $this->canonicalOrLegacy(SystemSettingKeys::DEFAULT_TIMEZONE, 'regional', 'timezone', 'Asia/Kolkata'),
            'date_format' => $this->canonicalOrLegacy(SystemSettingKeys::REGIONAL_DATE_FORMAT, 'regional', 'date_format', 'd-m-Y'),
            'time_format' => $this->canonicalOrLegacy(SystemSettingKeys::REGIONAL_TIME_FORMAT, 'regional', 'time_format', 'h:i A'),
            'financial_year_start_month' => $this->canonicalOrLegacy(SystemSettingKeys::REGIONAL_FINANCIAL_YEAR_START_MONTH, 'regional', 'financial_year_start_month', 4),
        ];
    }

    /** @return array<string, array<string, array{value: mixed, type: string}>> */
    public function regionalCurrencyDefaults(): array
    {
        return [
            'regional' => [
                'timezone' => ['value' => 'Asia/Kolkata', 'type' => SystemSetting::TYPE_STRING],
                'date_format' => ['value' => 'd-m-Y', 'type' => SystemSetting::TYPE_STRING],
                'time_format' => ['value' => 'h:i A', 'type' => SystemSetting::TYPE_STRING],
                'financial_year_start_month' => ['value' => 4, 'type' => SystemSetting::TYPE_INTEGER],
            ],
            'currency' => [
                'base_currency' => ['value' => 'INR', 'type' => SystemSetting::TYPE_STRING],
                'symbol' => ['value' => '₹', 'type' => SystemSetting::TYPE_STRING],
                'decimal_places' => ['value' => 2, 'type' => SystemSetting::TYPE_INTEGER],
                'thousands_separator' => ['value' => ',', 'type' => SystemSetting::TYPE_STRING],
                'decimal_separator' => ['value' => '.', 'type' => SystemSetting::TYPE_STRING],
                'symbol_position' => ['value' => 'before', 'type' => SystemSetting::TYPE_STRING],
            ],
        ];
    }

    /** @return Collection<string, mixed> */
    public function regionalCurrencyValues(): Collection
    {
        $regional = $this->regionalConfig();
        $currency = $this->currencyConfig();

        return collect([
            'regional.timezone' => $regional['timezone'],
            'regional.date_format' => $regional['date_format'],
            'regional.time_format' => $regional['time_format'],
            'regional.financial_year_start_month' => $regional['financial_year_start_month'],
            'currency.base_currency' => $currency['currency'],
            'currency.symbol' => $currency['symbol'],
            'currency.decimal_places' => $currency['decimal_places'],
            'currency.thousands_separator' => $currency['thousands_separator'],
            'currency.decimal_separator' => $currency['decimal_separator'],
            'currency.symbol_position' => $currency['symbol_position'],
        ]);
    }

    public function setRegionalCurrency(string $group, string $key, mixed $value): SystemSetting
    {
        $canonicalKey = SystemSettingKeys::canonicalKeyForLegacy($group, $key);
        $definition = $this->regionalCurrencyDefaults()[$group][$key] ?? null;

        if ($canonicalKey === null || $definition === null) {
            throw new InvalidArgumentException('Unknown regional or currency setting.');
        }

        $this->validateRegionalCurrency($group, $key, $value);
        $localization = SystemSettingGroup::query()->firstOrCreate(
            ['slug' => 'localization'],
            ['name' => 'Localization', 'sort_order' => 20, 'status' => SystemSetting::STATUS_ACTIVE],
        );

        return $this->set($canonicalKey, $value, $definition['type'], [
            'group_id' => $localization->getKey(),
        ]);
    }

    public function notificationPreference(string $eventKey, string $channel, bool $default): bool
    {
        $legacyKey = "events.{$eventKey}.{$channel}.enabled";
        $value = $this->canonicalOrLegacy(
            SystemSettingKeys::notificationPreference($eventKey, $channel),
            'notifications',
            $legacyKey,
            $default,
        );

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function setNotificationPreference(string $eventKey, string $channel, bool $enabled): SystemSetting
    {
        $notifications = SystemSettingGroup::query()->firstOrCreate(
            ['slug' => 'notifications'],
            ['name' => 'Notifications', 'sort_order' => 40, 'status' => SystemSetting::STATUS_ACTIVE],
        );

        return $this->set(
            SystemSettingKeys::notificationPreference($eventKey, $channel),
            $enabled,
            SystemSetting::TYPE_BOOLEAN,
            ['group_id' => $notifications->getKey()],
        );
    }

    public function emailSetting(string $key, mixed $default = null): mixed
    {
        if ($key === 'smtp.password') {
            return $default;
        }

        return $this->canonicalOrLegacy(
            SystemSettingKeys::email($key),
            'notifications.email',
            $key,
            $default,
        );
    }

    public function setEmailSetting(string $key, mixed $value, string $type = SystemSetting::TYPE_STRING): SystemSetting
    {
        if ($key === 'smtp.password' || $type === SystemSetting::TYPE_ENCRYPTED) {
            throw new InvalidArgumentException('Use the protected email password API for SMTP credentials.');
        }

        return $this->set(SystemSettingKeys::email($key), $value, $type, [
            'group_id' => $this->emailGroup()->getKey(),
        ]);
    }

    public function setEmailPassword(?string $plaintext): SystemSetting
    {
        $payload = filled($plaintext)
            ? self::EMAIL_PASSWORD_PAYLOAD_PREFIX.$plaintext
            : $plaintext;

        return $this->setEncrypted(SystemSettingKeys::EMAIL_SMTP_PASSWORD, $payload, [
            'group_id' => $this->emailGroup()->getKey(),
        ]);
    }

    public function emailPassword(): ?string
    {
        $payload = $this->secret(SystemSettingKeys::EMAIL_SMTP_PASSWORD);

        if (! is_string($payload) || ! str_starts_with($payload, self::EMAIL_PASSWORD_PAYLOAD_PREFIX)) {
            return null;
        }

        $password = substr($payload, strlen(self::EMAIL_PASSWORD_PAYLOAD_PREFIX));

        return $password === '' ? null : $password;
    }

    public function emailPasswordConfigured(): bool
    {
        return $this->emailPassword() !== null;
    }

    public function merchantBannerLimitPerShop(): int
    {
        $value = $this->get(SystemSettingKeys::STOREFRONT_BANNER_MAX_PER_SHOP, 3);

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
        $default = 'Product images, prices, availability and details are provided by shops or suppliers and may vary. Please verify key details with the shop before purchase.';
        $value = trim((string) $this->get('storefront.product_disclaimer.global', $default));

        return $value === '' ? $default : $value;
    }

    private function setting(string $key): ?SystemSetting
    {
        // Always read current canonical state. The former singleton-local cache
        // cached misses and stale models within the same request.
        return $this->query()->where('key', $key)->first();
    }

    private function canonicalOrLegacy(string $canonicalKey, string $legacyGroup, string $legacyKey, mixed $default): mixed
    {
        $setting = SystemSetting::withTrashed()->where('key', $canonicalKey)->first();

        if ($setting !== null) {
            if ($setting->trashed() || $setting->status !== SystemSetting::STATUS_ACTIVE || $this->encrypted($setting)) {
                return $default;
            }

            return $this->cast($setting->value, (string) $setting->value_type, $default);
        }

        return $this->legacySettings->get($legacyGroup, $legacyKey, $default);
    }

    private function validateRegionalCurrency(string $group, string $key, mixed $value): void
    {
        if ($group === 'regional' && $key === 'timezone' && ! $this->timezones->has((string) $value) && ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Timezone must be a valid PHP timezone.');
        }

        if ($group === 'regional' && $key === 'date_format' && ! in_array($value, ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd M Y'], true)) {
            throw new InvalidArgumentException('Date format is not supported.');
        }

        if ($group === 'regional' && $key === 'time_format' && ! in_array($value, ['h:i A', 'H:i'], true)) {
            throw new InvalidArgumentException('Time format is not supported.');
        }

        if ($group === 'regional' && $key === 'financial_year_start_month' && ((int) $value < 1 || (int) $value > 12)) {
            throw new InvalidArgumentException('Financial year start month must be between 1 and 12.');
        }

        if ($group === 'currency' && $key === 'base_currency' && ! $this->currencies->has((string) $value)) {
            throw new InvalidArgumentException('Base currency is not supported.');
        }

        if ($group === 'currency' && $key === 'decimal_places' && ((int) $value < 0 || (int) $value > 4)) {
            throw new InvalidArgumentException('Decimal places must be between 0 and 4.');
        }

        if ($group === 'currency' && $key === 'symbol_position' && ! in_array($value, ['before', 'after'], true)) {
            throw new InvalidArgumentException('Symbol position must be before or after.');
        }
    }

    private function emailGroup(): SystemSettingGroup
    {
        return SystemSettingGroup::query()->firstOrCreate(
            ['slug' => 'email'],
            ['name' => 'Email', 'sort_order' => 30, 'status' => SystemSetting::STATUS_ACTIVE],
        );
    }

    /** @return Builder<SystemSetting> */
    private function query(): Builder
    {
        return SystemSetting::query()
            ->where('status', SystemSetting::STATUS_ACTIVE)
            ->whereNull('deleted_at');
    }

    /** @param array<string, mixed> $attributes */
    private function persist(string $key, ?string $value, string $type, array $attributes): SystemSetting
    {
        $setting = SystemSetting::withTrashed()->firstOrNew(['key' => $key]);
        $setting->forceFill([
            ...$attributes,
            'key' => $key,
            'label' => $attributes['label'] ?? ($setting->label ?: str($key)->replace(['.', '_'], ' ')->headline()->toString()),
            'value' => $value,
            'value_type' => $type,
            'is_public' => (bool) ($attributes['is_public'] ?? $setting->is_public ?? false),
            'is_encrypted' => (bool) ($attributes['is_encrypted'] ?? false),
            'sort_order' => (int) ($attributes['sort_order'] ?? $setting->sort_order ?? 0),
            'status' => $attributes['status'] ?? $setting->status ?? SystemSetting::STATUS_ACTIVE,
            'deleted_at' => $attributes['deleted_at'] ?? null,
        ])->save();

        return $setting->fresh();
    }

    private function encrypted(SystemSetting $setting): bool
    {
        return $setting->is_encrypted || $setting->value_type === SystemSetting::TYPE_ENCRYPTED;
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
            default => $default,
        };
    }

    private function typeForValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => SystemSetting::TYPE_BOOLEAN,
            is_int($value) => SystemSetting::TYPE_INTEGER,
            is_array($value) => SystemSetting::TYPE_JSON,
            default => SystemSetting::TYPE_STRING,
        };
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, SystemSetting::valueTypes(), true)) {
            throw new InvalidArgumentException('Unsupported system setting type.');
        }
    }

    private function prepare(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            SystemSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            SystemSetting::TYPE_INTEGER => (string) (int) $value,
            SystemSetting::TYPE_JSON,
            SystemSetting::TYPE_ARRAY => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
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
