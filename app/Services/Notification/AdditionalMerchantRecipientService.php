<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Services\Merchant\MerchantSettingsService;
use InvalidArgumentException;

class AdditionalMerchantRecipientService
{
    public function __construct(private readonly MerchantSettingsService $settings) {}

    /** @param array<int, string> $recipients */
    public function set(int $merchantId, string $channel, array $recipients): array
    {
        NotificationChannelName::assertSupported($channel);
        $normalized = collect($recipients)
            ->map(fn ($value): string => $this->normalize($channel, (string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->settings->set($merchantId, 'notifications', "additional_recipients.{$channel}", $normalized);

        return $normalized;
    }

    /** @return array<int, string> */
    public function get(int $merchantId, string $channel): array
    {
        NotificationChannelName::assertSupported($channel);
        $value = $this->settings->get($merchantId, 'notifications', "additional_recipients.{$channel}", []);

        return is_array($value) ? array_values($value) : [];
    }

    private function normalize(string $channel, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($channel === NotificationChannelName::EMAIL) {
            $value = strtolower($value);
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Additional email recipient is invalid.');
            }

            return $value;
        }

        $value = preg_replace('/[^0-9+]/', '', $value) ?? '';
        if (! preg_match('/^\+?[1-9]\d{7,14}$/', $value)) {
            throw new InvalidArgumentException('Additional phone recipient is invalid.');
        }

        return $value;
    }
}
