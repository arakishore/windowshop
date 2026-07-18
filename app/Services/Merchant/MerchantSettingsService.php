<?php

namespace App\Services\Merchant;

use App\Models\MerchantSetting;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MerchantSettingsService
{
    public function get(int $merchantId, string $group, string $key, mixed $default = null): mixed
    {
        $setting = MerchantSetting::query()
            ->where('merchant_id', $merchantId)
            ->where('group', $group)
            ->where('setting_key', $key)
            ->first();

        return $setting ? $this->castValue($setting->setting_value, $setting->setting_type) : $default;
    }

    public function set(int $merchantId, string $group, string $key, mixed $value): MerchantSetting
    {
        return $this->setTyped($merchantId, $group, $key, $value, $this->typeForValue($value));
    }

    public function setTyped(int $merchantId, string $group, string $key, mixed $value, string $type): MerchantSetting
    {
        $this->validate($group, $key, $value, $type);

        return MerchantSetting::query()->updateOrCreate(
            [
                'merchant_id' => $merchantId,
                'group' => $group,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $this->prepareValue($value, $type),
                'setting_type' => $type,
            ],
        );
    }

    public function has(int $merchantId, string $group, string $key): bool
    {
        return MerchantSetting::query()
            ->where('merchant_id', $merchantId)
            ->where('group', $group)
            ->where('setting_key', $key)
            ->exists();
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(int $merchantId, ?string $group = null): Collection
    {
        return MerchantSetting::query()
            ->where('merchant_id', $merchantId)
            ->when($group, fn ($query) => $query->where('group', $group))
            ->orderBy('group')
            ->orderBy('setting_key')
            ->get()
            ->mapWithKeys(function (MerchantSetting $setting) use ($group): array {
                $key = $group ? $setting->setting_key : $setting->group.'.'.$setting->setting_key;

                return [$key => $this->castValue($setting->setting_value, $setting->setting_type)];
            });
    }

    public function validate(string $group, string $key, mixed $value, ?string $type = null): void
    {
        if ($type !== null && ! in_array($type, $this->supportedTypes(), true)) {
            throw new InvalidArgumentException('Unsupported merchant setting type.');
        }

        if ($group === 'pos' && $key === 'cash_rounding.method' && ! in_array($value, ['nearest', 'up', 'down'], true)) {
            throw new InvalidArgumentException('Cash rounding method must be nearest, up, or down.');
        }

        if ($group === 'pos' && $key === 'cash_rounding.apply_to' && ! $this->validCashRoundingApplyTo((string) $value)) {
            throw new InvalidArgumentException('Cash rounding apply_to contains an unsupported payment method.');
        }

        if ($group === 'pos' && $key === 'product.tile_size' && ! in_array($value, ['compact', 'comfortable', 'spacious'], true)) {
            throw new InvalidArgumentException('POS product tile size must be compact, comfortable, or spacious.');
        }
    }

    private function validCashRoundingApplyTo(string $value): bool
    {
        if ($value === 'all') {
            return true;
        }

        $methods = array_filter(explode(',', $value));
        $supported = ['cash', 'upi', 'card'];

        return $methods !== [] && count(array_diff($methods, $supported)) === 0;
    }

    /**
     * @return array<int, string>
     */
    private function supportedTypes(): array
    {
        return [
            MerchantSetting::TYPE_BOOLEAN,
            MerchantSetting::TYPE_INTEGER,
            MerchantSetting::TYPE_DECIMAL,
            MerchantSetting::TYPE_STRING,
            MerchantSetting::TYPE_JSON,
        ];
    }

    private function typeForValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => MerchantSetting::TYPE_BOOLEAN,
            is_int($value) => MerchantSetting::TYPE_INTEGER,
            is_float($value) => MerchantSetting::TYPE_DECIMAL,
            is_array($value) => MerchantSetting::TYPE_JSON,
            default => MerchantSetting::TYPE_STRING,
        };
    }

    private function prepareValue(mixed $value, string $type): ?string
    {
        return match ($type) {
            MerchantSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            MerchantSetting::TYPE_INTEGER => (string) (int) $value,
            MerchantSetting::TYPE_DECIMAL => (string) (float) $value,
            MerchantSetting::TYPE_JSON => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value === null ? null : (string) $value,
        };
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            MerchantSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            MerchantSetting::TYPE_INTEGER => (int) $value,
            MerchantSetting::TYPE_DECIMAL => (float) $value,
            MerchantSetting::TYPE_JSON => json_decode($value ?: 'null', true, 512, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
