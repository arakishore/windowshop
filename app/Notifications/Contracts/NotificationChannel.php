<?php

namespace App\Notifications\Contracts;

use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;

interface NotificationChannel
{
    public function name(): string;

    public function send(NotificationMessage $message): DeliveryResult;
}
