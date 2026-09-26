<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Services\Admin\AdminSettingsService;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Merchant\ShopSettingsService;
use InvalidArgumentException;

class NotificationPreferenceResolver
{
    public function __construct(
        private readonly MerchantSettingsService $merchantSettings,
        private readonly ShopSettingsService $shopSettings,
        private readonly AdminSettingsService $adminSettings,
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
            return $this->adminSettings->has('notifications', $key)
                ? (bool) $this->adminSettings->get('notifications', $key)
                : $definition->defaultEnabled($message->channel);
        }

        if ($message->shopId !== null && $this->shopSettings->has($message->shopId, 'notifications', $key)) {
            return (bool) $this->shopSettings->get($message->shopId, 'notifications', $key);
        }

        if ($message->merchantId !== null && $this->merchantSettings->has($message->merchantId, 'notifications', $key)) {
            return (bool) $this->merchantSettings->get($message->merchantId, 'notifications', $key);
        }

        return $definition->defaultEnabled($message->channel);
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

    public function setGlobal(string $eventKey, string $channel, bool $enabled): void
    {
        $this->assertConfigurable($eventKey, $channel, $enabled, 'global');
        $this->adminSettings->set('notifications', $this->key($eventKey, $channel), $enabled);
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
