<?php

namespace App\Services\Notification;

use App\Models\NotificationDeliveryLog;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;

class NotificationDeliveryLogger
{
    public function record(NotificationMessage $message, DeliveryResult $result): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::query()->create([
            'notification_key' => $message->key,
            'recipient_type' => $message->recipientType,
            'recipient_id' => $message->recipientId,
            'shop_id' => $message->shopId,
            'merchant_id' => $message->merchantId,
            'related_type' => $message->relatedType,
            'related_id' => $message->relatedId,
            'channel' => $message->channel,
            'destination' => $message->destination,
            'provider_mode' => $result->providerMode,
            'provider' => $result->provider,
            'status' => $result->status,
            'error_summary' => $result->error ? mb_substr($result->error, 0, 1000) : null,
            'attempted_at' => now(),
            'sent_at' => $result->status === DeliveryResult::SENT ? now() : null,
            'metadata' => [...$message->metadata, ...$result->metadata],
        ]);
    }
}
