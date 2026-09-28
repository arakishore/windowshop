<?php

namespace App\Notifications;

use InvalidArgumentException;

final class NotificationChannelName
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::EMAIL, self::SMS, self::WHATSAPP];
    }

    public static function assertSupported(string $channel): void
    {
        if (! in_array($channel, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported notification channel [{$channel}].");
        }
    }
}
