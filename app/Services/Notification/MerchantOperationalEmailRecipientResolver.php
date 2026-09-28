<?php

namespace App\Services\Notification;

use App\Models\Shop;
use App\Services\Merchant\ShopSettingsService;

class MerchantOperationalEmailRecipientResolver
{
    private const GROUP = 'notifications';

    private const KEYS = [
        'additional_to' => 'email.additional_to',
        'cc' => 'email.cc',
        'bcc' => 'email.bcc',
    ];

    public function __construct(
        private readonly ShopSettingsService $settings,
        private readonly AdditionalMerchantRecipientService $legacyRecipients,
    ) {}

    /** @return array{primary: string, additional_to: array<int, string>, to: array<int, string>, cc: array<int, string>, bcc: array<int, string>} */
    public function resolve(Shop $shop): array
    {
        $shop->loadMissing('merchant.user');
        $primary = $this->normalize($shop->merchant?->contact_email ?: $shop->merchant?->user?->email);
        $additional = $this->configured($shop, 'additional_to');

        if (! $this->settings->has((int) $shop->getKey(), self::GROUP, self::KEYS['additional_to'])) {
            $additional = $this->normalizeMany($this->legacyRecipients->get((int) $shop->merchant_id, 'email'));
        }

        $used = array_filter([$primary]);
        $additional = $this->without($additional, $used);
        $used = [...$used, ...$additional];
        $cc = $this->without($this->configured($shop, 'cc'), $used);
        $used = [...$used, ...$cc];
        $bcc = $this->without($this->configured($shop, 'bcc'), $used);

        return [
            'primary' => $primary,
            'additional_to' => $additional,
            'to' => array_values(array_filter([$primary, ...$additional])),
            'cc' => $cc,
            'bcc' => $bcc,
        ];
    }

    /** @param array<string, array<int, string>> $groups */
    public function save(Shop $shop, array $groups): array
    {
        foreach (self::KEYS as $group => $key) {
            $this->settings->set((int) $shop->getKey(), self::GROUP, $key, $this->normalizeMany($groups[$group] ?? []));
        }

        return $this->resolve($shop);
    }

    private function configured(Shop $shop, string $group): array
    {
        $value = $this->settings->get((int) $shop->getKey(), self::GROUP, self::KEYS[$group], []);

        return $this->normalizeMany(is_array($value) ? $value : []);
    }

    /** @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function normalizeMany(array $values): array
    {
        return collect($values)->map(fn ($value): string => $this->normalize($value))->filter()->unique()->values()->all();
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /** @param array<int, string> $values
     * @param  array<int, string>  $excluded
     * @return array<int, string>
     */
    private function without(array $values, array $excluded): array
    {
        return array_values(array_filter($values, fn (string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL)
            && ! in_array($value, $excluded, true)));
    }
}
