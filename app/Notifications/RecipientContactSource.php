<?php

namespace App\Notifications;

final class RecipientContactSource
{
    public const ADMIN = 'admin';

    public const CURRENT_ACCOUNT = 'current_account';

    public const MERCHANT_PRIMARY = 'merchant_primary';

    public const MERCHANT_PRIMARY_PLUS_ADDITIONAL = 'merchant_primary_plus_additional';

    public const ORDER_SNAPSHOT = 'order_snapshot';

    private function __construct() {}
}
