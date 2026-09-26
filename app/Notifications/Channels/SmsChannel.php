<?php

namespace App\Notifications\Channels;

use App\Notifications\Contracts\NotificationChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Notifications\ProviderMode;
use App\Services\Notification\NotificationProviderModeResolver;

class SmsChannel implements NotificationChannel
{
    public function __construct(private readonly NotificationProviderModeResolver $modes) {}

    public function name(): string
    {
        return NotificationChannelName::SMS;
    }

    public function send(NotificationMessage $message): DeliveryResult
    {
        $mode = $this->modes->resolve($this->name(), $message->merchantId);

        return $mode === ProviderMode::DISABLED
            ? DeliveryResult::skipped($mode)
            : DeliveryResult::notConfigured($mode);
    }
}
