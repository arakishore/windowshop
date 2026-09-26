<?php

namespace App\Listeners;

use App\Events\CustomerRegistered;
use App\Events\MerchantAccountCreated;
use App\Events\MerchantLifecycleChanged;
use App\Events\StorefrontOrderPlaced;
use App\Models\MerchantProfile;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Services\Notification\AdditionalMerchantRecipientService;
use App\Services\Notification\AdminNotificationRecipientResolver;
use App\Services\Notification\NotificationManager;

class DispatchBusinessNotifications
{
    public function __construct(
        private readonly NotificationManager $notifications,
        private readonly AdditionalMerchantRecipientService $additionalRecipients,
        private readonly AdminNotificationRecipientResolver $adminRecipients,
    ) {}

    public function customerRegistered(CustomerRegistered $event): void
    {
        $this->dispatchToDestinations('customer.registered', 'customer', $event->occurrenceId, [
            NotificationChannelName::EMAIL => [$event->user->email],
            NotificationChannelName::SMS => [$event->user->mobile],
            NotificationChannelName::WHATSAPP => [$event->user->mobile],
        ], recipientId: (int) $event->user->getKey(), context: ['customer_name' => $event->user->name]);
    }

    public function merchantAccountCreated(MerchantAccountCreated $event): void
    {
        $this->merchantLifecycle('merchant.account_created', $event->merchant, $event->occurrenceId);
    }

    public function merchantLifecycleChanged(MerchantLifecycleChanged $event): void
    {
        $this->merchantLifecycle($event->notificationKey, $event->merchant, $event->occurrenceId);
    }

    public function storefrontOrderPlaced(StorefrontOrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['shop', 'merchant.user']);
        $context = ['customer_name' => $order->customer_name, 'order_number' => $order->order_number, 'shop_name' => $order->shop?->name];

        $this->dispatchToDestinations('order.placed.customer', 'customer', $event->occurrenceId, [
            NotificationChannelName::EMAIL => [$order->customer_email],
            NotificationChannelName::SMS => [$order->customer_mobile],
            NotificationChannelName::WHATSAPP => [$order->customer_mobile],
        ], $order->customer_id, $order->shop_id, $order->merchant_id, 'order', $order->uuid, $context);

        $merchant = $order->merchant;
        if ($merchant instanceof MerchantProfile) {
            $destinations = $this->merchantDestinations($merchant, true);
            $this->dispatchToDestinations('order.new.merchant', 'merchant', $event->occurrenceId, $destinations, $merchant->user_id, $order->shop_id, $merchant->getKey(), 'order', $order->uuid, [
                ...$context, 'merchant_name' => $merchant->contact_person_name ?: $merchant->user?->name,
            ]);
        }

        foreach (NotificationChannelName::all() as $channel) {
            foreach ($this->adminRecipients->forChannel($channel) as $recipient) {
                $this->notifications->send(new NotificationMessage('order.new.admin', 'admin', $channel, $recipient['destination'], $recipient['id'], $order->shop_id, $order->merchant_id, 'order', $order->uuid, $event->occurrenceId, $context));
            }
        }
    }

    private function merchantLifecycle(string $key, MerchantProfile $merchant, string $occurrenceId): void
    {
        $merchant->loadMissing('user');
        $this->dispatchToDestinations($key, 'merchant', $occurrenceId, $this->merchantDestinations($merchant, false), $merchant->user_id, merchantId: (int) $merchant->getKey(), relatedType: 'merchant', relatedId: $merchant->uuid, context: [
            'merchant_name' => $merchant->contact_person_name ?: $merchant->user?->name,
        ]);
    }

    private function merchantDestinations(MerchantProfile $merchant, bool $includeAdditional): array
    {
        $merchant->loadMissing('user');
        $destinations = [
            NotificationChannelName::EMAIL => [$merchant->contact_email ?: $merchant->user?->email],
            NotificationChannelName::SMS => [$merchant->contact_mobile ?: $merchant->user?->mobile],
            NotificationChannelName::WHATSAPP => [$merchant->contact_mobile ?: $merchant->user?->mobile],
        ];

        if ($includeAdditional) {
            foreach (NotificationChannelName::all() as $channel) {
                $destinations[$channel] = [...$destinations[$channel], ...$this->additionalRecipients->get((int) $merchant->getKey(), $channel)];
            }
        }

        return $destinations;
    }

    private function dispatchToDestinations(string $key, string $recipientType, string $occurrenceId, array $destinations, ?int $recipientId = null, ?int $shopId = null, ?int $merchantId = null, ?string $relatedType = null, ?string $relatedId = null, array $context = []): void
    {
        foreach ($destinations as $channel => $values) {
            $normalized = collect($values)->filter()->map(fn ($value): string => trim((string) $value))->unique(fn (string $value): string => strtolower($value));
            foreach ($normalized as $destination) {
                $this->notifications->send(new NotificationMessage($key, $recipientType, $channel, $destination, $recipientId, $shopId, $merchantId, $relatedType, $relatedId, $occurrenceId, $context));
            }
        }
    }
}
