<?php

namespace App\Services\Admin;

use App\Models\AdminSetting;
use App\Support\CurrencyCatalog;
use App\Support\TimezoneCatalog;
use DateTimeZone;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AdminSettingsService
{
    public function __construct(
        private readonly TimezoneCatalog $timezones,
        private readonly CurrencyCatalog $currencies,
    ) {
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = AdminSetting::query()
            ->where('group', $group)
            ->where('setting_key', $key)
            ->first();

        return $setting ? $this->castValue($setting->setting_value, $setting->setting_type) : $default;
    }

    public function set(string $group, string $key, mixed $value): AdminSetting
    {
        return $this->setTyped($group, $key, $value, $this->typeForValue($value));
    }

    public function setTyped(string $group, string $key, mixed $value, string $type): AdminSetting
    {
        $this->validate($group, $key, $value, $type);

        return AdminSetting::query()->updateOrCreate(
            ['group' => $group, 'setting_key' => $key],
            ['setting_value' => $this->prepareValue($value, $type), 'setting_type' => $type],
        );
    }

    public function has(string $group, string $key): bool
    {
        return AdminSetting::query()
            ->where('group', $group)
            ->where('setting_key', $key)
            ->exists();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(?string $group = null): Collection
    {
        return AdminSetting::query()
            ->when($group, fn ($query) => $query->where('group', $group))
            ->orderBy('group')
            ->orderBy('setting_key')
            ->get()
            ->mapWithKeys(function (AdminSetting $setting) use ($group): array {
                $key = $group ? $setting->setting_key : $setting->group.'.'.$setting->setting_key;

                return [$key => $this->castValue($setting->setting_value, $setting->setting_type)];
            });
    }

    public function currencyConfig(): array
    {
        return [
            'currency' => $this->get('currency', 'base_currency', 'INR'),
            'symbol' => $this->get('currency', 'symbol', '₹'),
            'decimal_places' => $this->get('currency', 'decimal_places', 2),
            'thousands_separator' => $this->get('currency', 'thousands_separator', ','),
            'decimal_separator' => $this->get('currency', 'decimal_separator', '.'),
            'symbol_position' => $this->get('currency', 'symbol_position', 'before'),
        ];
    }

    public function validate(string $group, string $key, mixed $value, ?string $type = null): void
    {
        if ($type !== null && ! in_array($type, $this->supportedTypes(), true)) {
            throw new InvalidArgumentException('Unsupported admin setting type.');
        }

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

    private function supportedTypes(): array
    {
        return [
            AdminSetting::TYPE_BOOLEAN,
            AdminSetting::TYPE_DECIMAL,
            AdminSetting::TYPE_INTEGER,
            AdminSetting::TYPE_JSON,
            AdminSetting::TYPE_STRING,
        ];
    }

    private function typeForValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => AdminSetting::TYPE_BOOLEAN,
            is_int($value) => AdminSetting::TYPE_INTEGER,
            is_float($value) => AdminSetting::TYPE_DECIMAL,
            is_array($value) => AdminSetting::TYPE_JSON,
            default => AdminSetting::TYPE_STRING,
        };
    }

    private function prepareValue(mixed $value, string $type): ?string
    {
        return match ($type) {
            AdminSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            AdminSetting::TYPE_INTEGER => (string) (int) $value,
            AdminSetting::TYPE_DECIMAL => (string) (float) $value,
            AdminSetting::TYPE_JSON => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value === null ? null : (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            AdminSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            AdminSetting::TYPE_INTEGER => (int) $value,
            AdminSetting::TYPE_DECIMAL => (float) $value,
            AdminSetting::TYPE_JSON => json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
