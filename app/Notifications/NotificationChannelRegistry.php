<?php

namespace App\Notifications;

use App\Notifications\Contracts\NotificationChannel;
use InvalidArgumentException;

class NotificationChannelRegistry
{
    /** @var array<string, NotificationChannel> */
    private array $channels = [];

    /** @param iterable<NotificationChannel> $channels */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->register($channel);
        }
    }

    public function register(NotificationChannel $channel): void
    {
        NotificationChannelName::assertSupported($channel->name());
        $this->channels[$channel->name()] = $channel;
    }

    public function get(string $name): NotificationChannel
    {
        NotificationChannelName::assertSupported($name);

        return $this->channels[$name]
            ?? throw new InvalidArgumentException("Notification channel [{$name}] is not registered.");
    }
}
