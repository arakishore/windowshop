<?php

namespace App\Services\Notification;

use App\Models\NotificationDeliveryLog;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;
use Illuminate\Database\QueryException;

class NotificationDeliveryLogger
{
    public function claim(NotificationMessage $message): ?NotificationDeliveryLog
    {
        $key = $this->deliveryKey($message);

        if ($key === null) {
            return null;
        }

        try {
            return NotificationDeliveryLog::query()->create([
                ...$this->attributes($message),
                'delivery_key' => $key,
                'status' => 'processing',
                'attempted_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (NotificationDeliveryLog::query()->where('delivery_key', $key)->exists()) {
                return null;
            }

            throw $exception;
        }
    }

    public function record(NotificationMessage $message, DeliveryResult $result): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::query()->create([
            ...$this->attributes($message),
            'delivery_key' => $this->deliveryKey($message),
            ...$this->resultAttributes($message, $result),
        ]);
    }

    public function complete(NotificationDeliveryLog $log, NotificationMessage $message, DeliveryResult $result): NotificationDeliveryLog
    {
        $log->forceFill($this->resultAttributes($message, $result))->save();

        return $log;
    }

    private function attributes(NotificationMessage $message): array
    {
        return [
            'notification_key' => $message->key,
            'recipient_type' => $message->recipientType,
            'recipient_id' => $message->recipientId,
            'shop_id' => $message->shopId,
            'merchant_id' => $message->merchantId,
            'related_type' => $message->relatedType,
            'related_id' => $message->relatedId,
            'channel' => $message->channel,
            'destination' => $message->destination,
            'metadata' => $message->metadata,
        ];
    }

    private function resultAttributes(NotificationMessage $message, DeliveryResult $result): array
    {
        return [
            'provider_mode' => $result->providerMode,
            'provider' => $result->provider,
            'status' => $result->status,
            'error_summary' => $result->error ? mb_substr($result->error, 0, 1000) : null,
            'attempted_at' => now(),
            'sent_at' => $result->status === DeliveryResult::SENT ? now() : null,
            'metadata' => [...$message->metadata, ...$result->metadata],
        ];
    }

    private function deliveryKey(NotificationMessage $message): ?string
    {
        if ($message->occurrenceId === null) {
            return null;
        }

        return hash('sha256', implode('|', [
            $message->occurrenceId,
            $message->key,
            $message->recipientType,
            strtolower(trim((string) $message->destination)),
            $message->channel,
        ]));
    }
}
