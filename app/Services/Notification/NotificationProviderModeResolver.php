<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Notifications\ProviderMode;
use App\Services\Merchant\MerchantSettingsService;

class NotificationProviderModeResolver
{
    public function __construct(private readonly MerchantSettingsService $settings) {}

    public function resolve(string $channel, ?int $merchantId): string
    {
        NotificationChannelName::assertSupported($channel);

        if ($channel === NotificationChannelName::EMAIL) {
            return ProviderMode::WINDOWSHOP;
        }

        if ($merchantId === null) {
            return ProviderMode::DISABLED;
        }

        $mode = (string) $this->settings->get($merchantId, 'notifications', "{$channel}.provider_mode", ProviderMode::DISABLED);
        ProviderMode::assertSupported($mode);

        return $mode;
    }

    public function set(int $merchantId, string $channel, string $mode): void
    {
        NotificationChannelName::assertSupported($channel);
        if ($channel === NotificationChannelName::EMAIL) {
            throw new \InvalidArgumentException('Email is centrally provided by WindowShop.');
        }
        ProviderMode::assertSupported($mode);
        $this->settings->set($merchantId, 'notifications', "{$channel}.provider_mode", $mode);
    }
}
