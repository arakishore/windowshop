<?php

namespace App\Services\Notification;

use App\Notifications\NotificationEventDefinition;
use Illuminate\Support\Collection;

class NotificationEventCatalogue
{
    public function find(string $key): ?NotificationEventDefinition
    {
        $definition = config('notification_events.events', [])[$key] ?? null;

        if (! is_array($definition)) {
            return null;
        }

        return new NotificationEventDefinition(
            $key,
            $definition['label'],
            $definition['audience'],
            $definition['category'],
            $definition['description'],
            $definition['channels'],
            $definition['template_key'] ?: $key,
            $definition['variables'] ?? [],
            $definition['contact_source'],
            $definition['branding'],
            $definition['preference_scope'] ?? 'shop_merchant',
            $definition['policy'] ?? [],
        );
    }

    /** @return Collection<string, NotificationEventDefinition> */
    public function all(): Collection
    {
        return collect(array_keys(config('notification_events.events', [])))
            ->mapWithKeys(fn (string $key): array => [$key => $this->find($key)]);
    }
}
