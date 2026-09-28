<?php

namespace App\Notifications;

use InvalidArgumentException;

final class ProviderMode
{
    public const DISABLED = 'disabled';

    public const WINDOWSHOP = 'windowshop';

    public const MERCHANT = 'merchant';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::DISABLED, self::WINDOWSHOP, self::MERCHANT];
    }

    public static function assertSupported(string $mode): void
    {
        if (! in_array($mode, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported notification provider mode [{$mode}].");
        }
    }
}
