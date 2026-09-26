<?php

namespace App\Notifications;

final readonly class DeliveryResult
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const NOT_CONFIGURED = 'not_configured';

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $status,
        public ?string $providerMode = null,
        public ?string $provider = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    public static function sent(?string $mode = null, ?string $provider = null, array $metadata = []): self
    {
        return new self(self::SENT, $mode, $provider, null, $metadata);
    }

    public static function failed(string $error, ?string $mode = null, ?string $provider = null): self
    {
        return new self(self::FAILED, $mode, $provider, $error);
    }

    public static function skipped(?string $mode = null): self
    {
        return new self(self::SKIPPED, $mode);
    }

    public static function notConfigured(?string $mode = null): self
    {
        return new self(self::NOT_CONFIGURED, $mode);
    }
}
