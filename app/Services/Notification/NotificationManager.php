<?php

namespace App\Services\Notification;

use App\Jobs\DeliverNotificationEmail;
use App\Models\NotificationDeliveryLog;
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

        $claim = $message->occurrenceId === null ? null : $this->logger->claim($message);
        if ($message->occurrenceId !== null && $claim === null) {
            return DeliveryResult::skipped();
        }

        if (! $this->preferences->enabled($message)) {
            $result = DeliveryResult::skipped();
            $claim ? $this->logger->complete($claim, $message, $result) : $this->logger->record($message, $result);

            return $result;
        }

        try {
            $result = $this->channels->get($message->channel)->send($message);
        } catch (Throwable $exception) {
            $result = DeliveryResult::failed($exception->getMessage());
        }

        $claim ? $this->logger->complete($claim, $message, $result) : $this->logger->record($message, $result);

        return $result;
    }

    public function queueEmail(NotificationMessage $message): ?NotificationDeliveryLog
    {
        if ($message->channel !== NotificationChannelName::EMAIL) {
            throw new LogicException('Only email notifications can use the email queue.');
        }

        $claim = $this->logger->claimQueued($message);
        if (! $claim instanceof NotificationDeliveryLog) {
            return null;
        }

        DeliverNotificationEmail::dispatch($message, (int) $claim->getKey())->afterCommit();

        return $claim;
    }

    public function sendQueuedEmail(NotificationMessage $message, int $deliveryLogId, bool $retrying = false): DeliveryResult
    {
        if ($message->channel !== NotificationChannelName::EMAIL) {
            throw new LogicException('Queued notification is not an email.');
        }

        $claim = $this->logger->beginQueuedAttempt($deliveryLogId, $retrying);
        if (! $claim instanceof NotificationDeliveryLog) {
            return DeliveryResult::skipped();
        }

        if (! $this->preferences->enabled($message)) {
            $result = DeliveryResult::skipped();
            $this->logger->complete($claim, $message, $result);

            return $result;
        }

        try {
            $result = $this->channels->get($message->channel)->send($message);
        } catch (Throwable $exception) {
            $result = DeliveryResult::failed($exception->getMessage());
        }

        $this->logger->complete($claim, $message, $result);

        return $result;
    }

    public function afterCommit(NotificationMessage $message): void
    {
        // Business integrations should use this boundary; send() retains the
        // transaction guard as a final defence against pre-commit delivery.
        DB::afterCommit(fn () => $this->send($message));
    }
}
