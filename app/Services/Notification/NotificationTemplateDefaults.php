<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationEventDefinition;

class NotificationTemplateDefaults
{
    public function for(NotificationEventDefinition $event, string $channel): array
    {
        $recipient = match ($event->audience) {
            'merchant' => '{{ merchant_name }}',
            'customer' => '{{ customer_name }}',
            default => 'there',
        };
        $brand = $event->branding === 'shop' ? '{{ shop_name }}' : '{{ marketplace_name }}';
        $body = "Hello {$recipient},\n\n{$event->description}";

        if ($channel === NotificationChannelName::EMAIL) {
            $body .= "\n\nPowered by {{ marketplace_name }}";
        }

        return [
            'event_key' => $event->templateKey,
            'channel' => $channel,
            'subject' => $channel === NotificationChannelName::EMAIL ? "{$brand}: {$event->label}" : null,
            'body' => $body,
            'is_active' => true,
            'variables' => $event->variables,
            'metadata' => ['branding' => $event->branding, 'seeded' => true],
        ];
    }
}
