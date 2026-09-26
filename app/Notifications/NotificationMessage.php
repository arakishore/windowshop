<?php

namespace App\Notifications;

final readonly class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $key,
        public string $recipientType,
        public string $channel,
        public ?string $destination = null,
        public ?int $recipientId = null,
        public ?int $shopId = null,
        public ?int $merchantId = null,
        public ?string $relatedType = null,
        public ?string $relatedId = null,
        public array $context = [],
        public array $metadata = [],
    ) {}
}
