<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Merchant\ShopSettingsService;
use App\Services\System\SystemSettingService;
use InvalidArgumentException;

class NotificationPreferenceResolver
{
    public function __construct(
        private readonly MerchantSettingsService $merchantSettings,
        private readonly ShopSettingsService $shopSettings,
        private readonly SystemSettingService $systemSettings,
        private readonly NotificationEventCatalogue $catalogue,
    ) {}

    public function enabled(NotificationMessage $message): bool
    {
        NotificationChannelName::assertSupported($message->channel);
        $definition = $this->catalogue->find($message->key);

        if ($definition === null || ! $definition->supports($message->channel)) {
            return false;
        }

        if ($definition->mandatory($message->channel)) {
            return true;
        }

        $key = $this->key($message->key, $message->channel);

        if ($definition->preferenceScope === 'global') {
            return $this->defaultEnabled($message->key, $message->channel);
        }

        if ($message->shopId !== null && $this->shopSettings->has($message->shopId, 'notifications', $key)) {
            return (bool) $this->shopSettings->get($message->shopId, 'notifications', $key);
        }

        if ($message->merchantId !== null && $this->merchantSettings->has($message->merchantId, 'notifications', $key)) {
            return (bool) $this->merchantSettings->get($message->merchantId, 'notifications', $key);
        }

        return $this->defaultEnabled($message->key, $message->channel);
    }

    public function setForMerchant(int $merchantId, string $eventKey, string $channel, bool $enabled): void
    {
        $this->assertConfigurable($eventKey, $channel, $enabled, 'shop_merchant');
        $this->merchantSettings->set($merchantId, 'notifications', $this->key($eventKey, $channel), $enabled);
    }

    public function setForShop(int $shopId, string $eventKey, string $channel, bool $enabled): void
    {
        $this->assertConfigurable($eventKey, $channel, $enabled, 'shop_merchant');
        $this->shopSettings->set($shopId, 'notifications', $this->key($eventKey, $channel), $enabled);
    }

    public function clearForShop(int $shopId, string $eventKey, string $channel): void
    {
        $this->assertConfigurable($eventKey, $channel, true, 'shop_merchant');
        $this->shopSettings->delete($shopId, 'notifications', $this->key($eventKey, $channel));
    }

    public function shopOverride(int $shopId, string $eventKey, string $channel): ?bool
    {
        $this->assertConfigurable($eventKey, $channel, true, 'shop_merchant');
        $key = $this->key($eventKey, $channel);

        return $this->shopSettings->has($shopId, 'notifications', $key)
            ? (bool) $this->shopSettings->get($shopId, 'notifications', $key)
            : null;
    }

    public function setGlobal(string $eventKey, string $channel, bool $enabled): void
    {
        $this->assertConfigurable($eventKey, $channel, $enabled, 'global');
        $this->systemSettings->setNotificationPreference($eventKey, $channel, $enabled);
    }

    public function setDefault(string $eventKey, string $channel, bool $enabled): void
    {
        $this->assertConfigurable($eventKey, $channel, $enabled, 'shop_merchant');
        if ($this->catalogue->find($eventKey)?->mandatory($channel)) {
            throw new InvalidArgumentException('Mandatory notification defaults cannot be changed.');
        }
        $this->systemSettings->setNotificationPreference($eventKey, $channel, $enabled);
    }

    public function defaultEnabled(string $eventKey, string $channel): bool
    {
        NotificationChannelName::assertSupported($channel);
        $definition = $this->catalogue->find($eventKey);
        if ($definition === null || ! $definition->supports($channel)) {
            return false;
        }
        if ($definition->mandatory($channel)) {
            return true;
        }

        return $this->systemSettings->notificationPreference(
            $eventKey,
            $channel,
            $definition->defaultEnabled($channel),
        );
    }

    private function assertConfigurable(string $eventKey, string $channel, bool $enabled, string $scope): void
    {
        NotificationChannelName::assertSupported($channel);
        $definition = $this->catalogue->find($eventKey);

        if ($definition === null || ! $definition->supports($channel) || $definition->preferenceScope !== $scope) {
            throw new InvalidArgumentException('Unknown or unsupported notification event preference.');
        }

        if (! $enabled && $definition->mandatory($channel)) {
            throw new InvalidArgumentException('Mandatory notification events cannot be disabled.');
        }
    }

    private function key(string $eventKey, string $channel): string
    {
        return "events.{$eventKey}.{$channel}.enabled";
    }
}
