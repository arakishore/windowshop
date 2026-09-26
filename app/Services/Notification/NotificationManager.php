<?php

namespace App\Services\Notification;

use App\Notifications\DeliveryResult;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationChannelRegistry;
use App\Notifications\NotificationMessage;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class NotificationManager
{
    public function __construct(
        private readonly NotificationChannelRegistry $channels,
        private readonly NotificationPreferenceResolver $preferences,
        private readonly NotificationDeliveryLogger $logger,
    ) {}

    public function send(NotificationMessage $message): DeliveryResult
    {
        NotificationChannelName::assertSupported($message->channel);

        if (DB::transactionLevel() > 0) {
            throw new LogicException('Notifications must be delivered only after the database transaction commits.');
        }

        if (! $this->preferences->enabled($message)) {
            $result = DeliveryResult::skipped();
            $this->logger->record($message, $result);

            return $result;
        }

        try {
            $result = $this->channels->get($message->channel)->send($message);
        } catch (Throwable $exception) {
            $result = DeliveryResult::failed($exception->getMessage());
        }

        $this->logger->record($message, $result);

        return $result;
    }

    public function afterCommit(NotificationMessage $message): void
    {
        // Business integrations should use this boundary; send() retains the
        // transaction guard as a final defence against pre-commit delivery.
        DB::afterCommit(fn () => $this->send($message));
    }
}
