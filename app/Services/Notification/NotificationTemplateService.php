<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;

class NotificationTemplateService
{
    public function __construct(private readonly NotificationEventCatalogue $catalogue) {}

    public function findActive(string $eventKey, string $channel): ?NotificationTemplate
    {
        $event = $this->catalogue->find($eventKey);

        if ($event === null || ! $event->supports($channel)) {
            return null;
        }

        return NotificationTemplate::query()
            ->where('event_key', $event->templateKey)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}
