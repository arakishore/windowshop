<?php

namespace App\Jobs;

use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;
use App\Services\Notification\NotificationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class DeliverNotificationEmail implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly NotificationMessage $message,
        public readonly int $deliveryLogId,
    ) {
        $this->onQueue('emails');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotificationManager $notifications): void
    {
        $result = $notifications->sendQueuedEmail($this->message, $this->deliveryLogId, $this->attempts() > 1);

        if ($result->status === DeliveryResult::FAILED) {
            throw new RuntimeException($result->error ?: 'Queued email delivery failed.');
        }
    }
}
