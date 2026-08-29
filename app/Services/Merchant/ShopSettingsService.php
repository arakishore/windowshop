<?php

namespace App\Services\Merchant;

use App\Models\ShopSetting;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ShopSettingsService
{
    public function get(int $shopId, string $group, string $key, mixed $default = null): mixed
    {
        $setting = ShopSetting::query()
            ->where('shop_id', $shopId)
            ->where('group', $group)
            ->where('setting_key', $key)
            ->first();

        return $setting ? $this->castValue($setting->setting_value, $setting->setting_type) : $default;
    }

    public function set(int $shopId, string $group, string $key, mixed $value): ShopSetting
    {
        return $this->setTyped($shopId, $group, $key, $value, $this->typeForValue($value));
    }

    public function setTyped(int $shopId, string $group, string $key, mixed $value, string $type): ShopSetting
    {
        $this->validate($group, $key, $value, $type);

        return ShopSetting::query()->updateOrCreate(
            [
                'shop_id' => $shopId,
                'group' => $group,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $this->prepareValue($value, $type),
                'setting_type' => $type,
            ],
        );
    }

    public function has(int $shopId, string $group, string $key): bool
    {
        return ShopSetting::query()
            ->where('shop_id', $shopId)
            ->where('group', $group)
            ->where('setting_key', $key)
            ->exists();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(int $shopId, ?string $group = null): Collection
    {
        return ShopSetting::query()
            ->where('shop_id', $shopId)
            ->when($group, fn ($query) => $query->where('group', $group))
            ->orderBy('group')
            ->orderBy('setting_key')
            ->get()
            ->mapWithKeys(function (ShopSetting $setting) use ($group): array {
                $key = $group ? $setting->setting_key : $setting->group.'.'.$setting->setting_key;

                return [$key => $this->castValue($setting->setting_value, $setting->setting_type)];
            });
    }

    public function castSetting(ShopSetting $setting): mixed
    {
        return $this->castValue($setting->setting_value, $setting->setting_type);
    }

    public function validate(string $group, string $key, mixed $value, ?string $type = null): void
    {
        if ($type !== null && ! in_array($type, $this->supportedTypes(), true)) {
            throw new InvalidArgumentException('Unsupported shop setting type.');
        }

        if ($group === 'fulfillment' && $key === 'delivery_scope' && ! in_array($value, ['local_only', 'nationwide'], true)) {
            throw new InvalidArgumentException('Delivery scope must be local_only or nationwide.');
        }

        if ($group === 'returns' && in_array($key, ['refund_window_days', 'exchange_window_days'], true) && (int) $value < 0) {
            throw new InvalidArgumentException('Return and exchange window days must be zero or greater.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function supportedTypes(): array
    {
        return [
            ShopSetting::TYPE_BOOLEAN,
            ShopSetting::TYPE_INTEGER,
            ShopSetting::TYPE_DECIMAL,
            ShopSetting::TYPE_STRING,
            ShopSetting::TYPE_JSON,
        ];
    }

    private function typeForValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => ShopSetting::TYPE_BOOLEAN,
            is_int($value) => ShopSetting::TYPE_INTEGER,
            is_float($value) => ShopSetting::TYPE_DECIMAL,
            is_array($value) => ShopSetting::TYPE_JSON,
            default => ShopSetting::TYPE_STRING,
        };
    }

    private function prepareValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            ShopSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            ShopSetting::TYPE_INTEGER => (string) (int) $value,
            ShopSetting::TYPE_DECIMAL => (string) (float) $value,
            ShopSetting::TYPE_JSON => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            ShopSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            ShopSetting::TYPE_INTEGER => (int) $value,
            ShopSetting::TYPE_DECIMAL => (float) $value,
            ShopSetting::TYPE_JSON => json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
