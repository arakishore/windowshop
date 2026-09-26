<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\NotificationChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Notifications\ProviderMode;

class EmailChannel implements NotificationChannel
{
    public function name(): string
    {
        return NotificationChannelName::EMAIL;
    }

    public function send(NotificationMessage $message): DeliveryResult
    {
        return DeliveryResult::notConfigured(ProviderMode::WINDOWSHOP);
    }
}
